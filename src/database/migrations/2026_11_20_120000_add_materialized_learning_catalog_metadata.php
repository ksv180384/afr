<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = [
        'content_title' => 'payload->title',
        'content_duration' => 'payload->duration_minutes',
        'content_goal' => 'payload->objectives[0]->title',
        'content_prerequisites' => 'payload->prerequisite_ids',
        'public_summary' => 'payload->public_page->summary',
    ];

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        Schema::table('learning_session_versions', function (Blueprint $table) use ($sqlite) {
            // SQLite does not allow adding stored generated columns to an existing table.
            foreach (self::FIELDS as $column => $path) {
                $field = $table->text($column)->nullable();
                if ($sqlite) {
                    $field->virtualAsJson($path);
                } elseif ($column === 'public_summary') {
                    $field->storedAs("CASE WHEN JSON_TYPE(JSON_EXTRACT(payload, '$.public_page.summary')) <> 'NULL' THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.public_page.summary')) END");
                } else {
                    $field->storedAsJson($path);
                }
            }
            $count = $table->unsignedInteger('public_explanation_count')->nullable();
            if ($sqlite) {
                $count->virtualAs("JSON_ARRAY_LENGTH(payload, '$.public_page.explanation_blocks')");
            } else {
                $count->storedAs("JSON_LENGTH(payload, '$.public_page.explanation_blocks')");
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_session_versions', fn (Blueprint $table) => $table->dropColumn([
            ...array_keys(self::FIELDS), 'public_explanation_count',
        ]));
    }
};
