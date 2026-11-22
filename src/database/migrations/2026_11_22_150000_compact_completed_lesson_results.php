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
            $table->json('snapshot')->nullable()->change();
            $table->string('promoted_to_level', 16)->nullable();
        });

        $grammar = DB::connection()->getQueryGrammar();
        $promotion = $grammar->wrap('result->promotion->new_level');
        $vocabulary = "JSON_EXTRACT(result, '$.vocabulary_keys')";
        $fields = [
            'total_steps', 'independent_correct_steps', 'technical_skips', 'mistakes_count', 'words_added', 'words_existing',
        ];
        $counters = implode(', ', array_map(fn ($field) => "'$field', CAST(COALESCE(JSON_EXTRACT(result, '$.$field'), 0) AS DECIMAL(10,0))", $fields));
        DB::table('learning_runs')->whereNotNull('session_id')->select('id')->chunkById(2000, function ($rows) use ($promotion, $vocabulary, $counters): void {
            $runs = DB::table('learning_runs')->whereNotNull('session_id')->whereBetween('id', [$rows->first()->id, $rows->last()->id]);
            (clone $runs)->whereNotNull('result')->update([
                'promoted_to_level' => DB::raw($promotion),
                'import_meta' => DB::raw("CASE WHEN import_meta IS NOT NULL AND JSON_TYPE($vocabulary) = 'ARRAY'
                    THEN JSON_SET(import_meta, '$.vocabulary_keys', $vocabulary) ELSE import_meta END"),
            ]);
            $runs->update([
                'snapshot' => null,
                'content_checksum' => DB::raw('CASE WHEN status <> \'completed\' AND content_checksum IS NULL
                    THEN (SELECT checksum FROM learning_sessions WHERE id = learning_runs.session_id) ELSE content_checksum END'),
                'result' => DB::raw("CASE WHEN result IS NULL THEN NULL ELSE JSON_OBJECT($counters) END"),
            ]);
        });
    }

    public function down(): void
    {
        throw new LogicException('Removed lesson snapshots and result details cannot be restored from the compact rows.');
    }
};
