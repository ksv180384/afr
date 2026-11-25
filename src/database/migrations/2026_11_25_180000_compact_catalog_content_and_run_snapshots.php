<?php

use App\Models\Learning\LearningRun;
use App\Support\Learning\PracticeJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite('learning_catalog_revisions', 'content');
        $this->rewrite('learning_runs', 'snapshot', ['status', 'kind']);
    }

    private function rewrite(string $table, string $column, array $context = []): void
    {
        DB::table($table)->select('id')->whereNotNull($column)->chunkById(100, function ($ids) use ($table, $column, $context) {
            DB::transaction(function () use ($ids, $table, $column, $context) {
                // A run may have finished or been cancelled since its ID was selected.
                $rows = DB::table($table)->whereIn('id', $ids->pluck('id'))->whereNotNull($column)
                    ->orderBy('id')->lockForUpdate()->get(['id', $column, ...$context]);
                foreach ($rows as $row) {
                    $value = json_decode($row->$column, false, 512, JSON_THROW_ON_ERROR);
                    if (($row->status ?? null) === 'cancelled' && in_array($row->kind, ['review', 'vocabulary_test'], true)) {
                        $summary = LearningRun::cancelledReviewSnapshot($value->title ?? null);
                        $json = $summary === null ? null : PracticeJson::encode($summary);
                    } else {
                        $json = PracticeJson::encode($value);
                    }
                    if ($json !== $row->$column) {
                        DB::table($table)->where('id', $row->id)->update([$column => $json]);
                    }
                }
            }, 3);
        });
    }

    public function down(): void
    {
        // Both versions read plain JSON. Discarded questions from cancelled reviews cannot be reconstructed.
    }
};
