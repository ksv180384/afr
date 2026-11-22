<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_runs', function (Blueprint $table): void {
            $table->char('content_checksum', 64)->nullable();
            $table->index(['status', 'updated_at'], 'lr_unfinished_expiry_idx');
        });
        Schema::dropIfExists('learning_session_version_media');

        DB::table('learning_runs')->whereNotNull('session_version_id')->whereIn('status', ['in_progress', 'paused'])->delete();

        $reviews = app(\App\Services\Learning\VocabularyTestService::class);
        DB::table('learning_runs')->select('id', 'snapshot', 'result', 'import_meta', 'session_version_id')
            ->where('status', 'completed')->chunkById(250, function ($runs) use ($reviews): void {
                foreach ($runs as $run) {
                    $snapshot = json_decode($run->snapshot, true, 512, JSON_THROW_ON_ERROR);
                    $summary = $run->session_version_id === null ? $reviews->completionSnapshot($snapshot)
                        : array_intersect_key($snapshot, array_flip(['id', 'title', 'level', 'duration_minutes']))
                            + ['goal' => $snapshot['objectives'][0]['title'] ?? null];
                    $updates = ['snapshot' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
                    if ($run->import_meta !== null && $run->result !== null) {
                        $result = json_decode($run->result, true, 512, JSON_THROW_ON_ERROR);
                        $result['vocabulary_keys'] ??= array_column($snapshot['vocabulary'] ?? [], 'lexeme_key');
                        $updates['result'] = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                    DB::table('learning_runs')->where('id', $run->id)->update($updates);
                    $operations = DB::table('learning_run_operations')->where('run_id', $run->id);
                    (clone $operations)->where('operation', '!=', 'start')->delete();
                    $operations->where('operation', 'start')->update([
                        'receipt' => json_encode(['run_id' => $run->id], JSON_THROW_ON_ERROR),
                    ]);
                    if ($run->import_meta === null) {
                        DB::table('learning_presentations')->where('run_id', $run->id)->delete();
                    } else {
                        DB::table('learning_presentations')->where('run_id', $run->id)->update(['snapshot' => '[]']);
                        DB::table('learning_attempts')->where('run_id', $run->id)->update(['payload' => '[]',
                            'result' => DB::raw("json_remove(result, '$.feedback', '$.solution', '$.pairs', '$.objective_ids')")]);
                    }
                }
            });

        DB::table('learning_sessions')->select('id', 'active_version_id')->chunkById(250, function ($sessions): void {
            foreach ($sessions as $session) {
                $versions = DB::table('learning_session_versions')->where('session_id', $session->id)->orderByDesc('version')->pluck('id');
                $current = $session->active_version_id ?? $versions->first();
                foreach ($versions->reject(fn ($id) => (int) $id === (int) $current) as $version) {
                    DB::table('learning_runs')->where('session_version_id', $version)->update(['session_version_id' => $current]);
                    DB::table('learning_content_reports')->where('session_version_id', $version)->update(['session_version_id' => $current]);
                    DB::table('personal_vocabulary_sources')->where('session_version_id', $version)
                        ->chunkById(250, function ($sources) use ($current) {
                            $existing = DB::table('personal_vocabulary_sources')->where('session_version_id', $current)
                                ->whereIn('personal_word_id', $sources->pluck('personal_word_id'))->pluck('personal_word_id')->all();
                            foreach ($sources as $source) {
                                $reference = DB::table('personal_vocabulary_sources')->where('id', $source->id);
                                if (in_array($source->personal_word_id, $existing, true)) {
                                    $reference->delete();
                                } else {
                                    $reference->update(['session_version_id' => $current]);
                                }
                            }
                        });
                    DB::table('learning_session_versions')->where('id', $version)->delete();
                }
            }
        });
        Schema::table('learning_session_versions', function (Blueprint $table): void {
            $table->unique('session_id', 'lsv_current_content_uq');
        });

        foreach (['personal_vocabulary_sources', 'personal_vocabulary'] as $table) {
            DB::table($table)->whereNotNull('snapshot->review_items')->update([
                'snapshot' => DB::raw("json_remove(snapshot, '$.review_items')"),
            ]);
        }
    }

    public function down(): void
    {
        throw new LogicException('Deleted lesson content and unfinished runs cannot be restored by a schema rollback.');
    }
};
