<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONTENT_FIELDS = [
        'schema_version', 'checksum', 'status', 'import_batch_id', 'created_by', 'reviewed_by', 'published_by',
        'editor_notes', 'review_checks', 'reviewed_at', 'published_at',
    ];

    private const METADATA_FIELDS = [
        'content_title' => 'payload->title',
        'content_duration' => 'payload->duration_minutes',
        'content_goal' => 'payload->objectives[0]->title',
        'content_prerequisites' => 'payload->prerequisite_ids',
        'public_summary' => 'payload->public_page->summary',
    ];

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        Schema::table('learning_sessions', function (Blueprint $table) use ($sqlite): void {
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedBigInteger('legacy_content_id')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignUuid('import_batch_id')->nullable()->constrained('session_import_batches')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('editor_notes')->nullable();
            $table->json('review_checks')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->index(['status', 'level'], 'ls_status_level_idx');

            foreach (self::METADATA_FIELDS as $column => $path) {
                $field = $table->text($column)->nullable();
                if ($sqlite) {
                    $field->virtualAsJson($path);
                } elseif ($column === 'public_summary') {
                    $field->storedAs("CASE WHEN JSON_TYPE(JSON_EXTRACT(payload, '$.public_page.summary')) <> 'NULL' THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.public_page.summary')) END");
                } else {
                    $field->storedAsJson($path);
                }
            }
            foreach (['task_count' => '$.tasks', 'public_explanation_count' => '$.public_page.explanation_blocks'] as $column => $path) {
                $count = $table->unsignedInteger($column)->nullable();
                if ($sqlite) {
                    $count->virtualAs("JSON_ARRAY_LENGTH(payload, '$path')");
                } else {
                    $count->storedAs("JSON_LENGTH(payload, '$path')");
                }
            }
        });

        $contentIds = [];
        DB::table('learning_session_versions')->select('id', 'session_id', 'payload', 'archived_at', ...self::CONTENT_FIELDS)
            ->chunkById(100, function ($rows) use (&$contentIds): void {
                foreach ($rows as $row) {
                    $contentIds[(int) $row->id] = (int) $row->session_id;
                    $values = array_intersect_key((array) $row, array_flip(self::CONTENT_FIELDS));
                    $values['legacy_content_id'] = $row->id;
                    $values['payload'] = json_encode(json_decode($row->payload, false, 512, JSON_THROW_ON_ERROR),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    $session = DB::table('learning_sessions')->where('id', $row->session_id);
                    if ($row->archived_at !== null && $session->value('archived_at') === null && $row->status === 'archived') {
                        $values['archived_at'] = $row->archived_at;
                    }
                    $session->update($values);
                }
            });

        $this->moveReferences('learning_runs', nullable: true);
        $this->moveReferences('personal_vocabulary_sources', nullable: false);
        $this->moveReferences('learning_content_reports', nullable: false);
        $this->moveImportReceipts($contentIds);

        Schema::table('learning_sessions', function (Blueprint $table): void {
            $table->dropForeign(['id', 'active_version_id']);
            $table->dropColumn('active_version_id');
        });
        Schema::drop('learning_session_versions');
    }

    private function moveReferences(string $name, bool $nullable): void
    {
        Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('session_id')->nullable());
        $session = DB::table('learning_session_versions')->select('session_id')
            ->whereColumn('learning_session_versions.id', $name.'.session_version_id');
        DB::table($name)->whereNotNull('session_version_id')->update(['session_id' => DB::raw('('.$session->toSql().')')]);

        Schema::table($name, function (Blueprint $table) use ($name, $nullable): void {
            $table->dropForeign(['session_version_id']);
            if ($name === 'learning_runs') {
                $table->dropUnique('lr_id_version_uq');
            } elseif ($name === 'personal_vocabulary_sources') {
                $table->dropUnique('pvs_source_uq');
            }
            $table->dropColumn('session_version_id');
            if (! $nullable) {
                $table->unsignedBigInteger('session_id')->change();
            }
            $table->foreign('session_id')->references('id')->on('learning_sessions')->restrictOnDelete();
            if ($name === 'personal_vocabulary_sources') {
                $table->unique(['personal_word_id', 'session_id'], 'pvs_word_session_uq');
            } else {
                $table->index('session_id');
            }
        });
    }

    private function moveImportReceipts(array $contentIds): void
    {
        DB::table('session_import_batches')->select('id', 'receipt', 'validation_report')->whereNotNull('receipt')
            ->chunkById(100, function ($batches) use ($contentIds): void {
                foreach ($batches as $batch) {
                    $receipt = json_decode($batch->receipt, true, 512, JSON_THROW_ON_ERROR);
                    $report = json_decode($batch->validation_report ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                    $ids = $receipt['version_ids'] ?? [];
                    $keys = array_keys($report['session_checksums'] ?? []);
                    $references = $contentIds;
                    if ($keys !== [] && count($ids) === count($keys) && array_diff($ids, array_keys($contentIds)) !== []) {
                        $sessions = DB::table('learning_sessions')->whereIn('external_id', $keys)->pluck('id', 'external_id');
                        foreach ($ids as $index => $id) {
                            $references[$id] ??= $sessions[$keys[$index]] ?? null;
                        }
                    }
                    foreach (['version_ids', 'created_version_ids', 'updated_version_ids', 'skipped_version_ids'] as $key) {
                        if (isset($receipt[$key])) {
                            $receipt[$key] = array_values(array_filter(array_map(fn ($id) => $references[$id] ?? null, $receipt[$key])));
                        }
                    }
                    DB::table('session_import_batches')->where('id', $batch->id)
                        ->update(['receipt' => json_encode($receipt, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    public function down(): void
    {
        throw new LogicException('The former content IDs cannot be reconstructed after consolidation.');
    }
};
