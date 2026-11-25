<?php

use App\Support\Learning\CompletedVocabularyRun;
use App\Support\Learning\PracticeJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(true);
    }

    public function down(): void
    {
        $this->rewrite(false);
    }

    private function rewrite(bool $compact): void
    {
        $runs = DB::table('learning_runs')->where('status', 'completed')->whereIn('kind', ['review', 'vocabulary_test']);
        (clone $runs)->select('id')->chunkById(100, function ($ids) use ($runs, $compact) {
            DB::transaction(function () use ($ids, $runs, $compact) {
                $rows = (clone $runs)->whereIn('id', $ids->pluck('id'))->orderBy('id')
                    ->lockForUpdate()->get(['id', 'kind', 'snapshot', 'result']);
                foreach (['snapshot', 'result'] as $column) {
                    $changes = [];
                    $bytes = 0;
                    foreach ($rows as $row) {
                        $json = $this->convert($column, $row->$column, $row->kind, $compact);
                        if ($json === null) {
                            continue;
                        }
                        // A self-check list can contain full word cards. Bound each statement's payload.
                        if ($changes && $bytes + strlen($json) > 524288) {
                            $this->write($column, $changes);
                            $changes = [];
                            $bytes = 0;
                        }
                        $changes[$row->id] = $json;
                        $bytes += strlen($json);
                    }
                    if ($changes) {
                        $this->write($column, $changes);
                    }
                }
            }, 3);
        });
    }

    private function convert(string $column, ?string $json, string $kind, bool $compact): ?string
    {
        if ($json === null) {
            return null;
        }
        $before = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($before)) {
            return null;
        }
        $after = match ($column) {
            'snapshot' => $compact ? CompletedVocabularyRun::compactSnapshot($before, $kind) : CompletedVocabularyRun::expandSnapshot($before),
            'result' => $compact ? CompletedVocabularyRun::compactResult($before) : CompletedVocabularyRun::expandResult($before),
        };
        if ($before === $after) {
            return null;
        }
        // Associative PHP arrays cannot distinguish e.g. a legacy empty object from an empty list.
        // Leave such a row in its original format.
        if (json_decode($json) != json_decode(PracticeJson::encode($before))) {
            return null;
        }

        return PracticeJson::encode($after);
    }

    private function write(string $column, array $changes): void
    {
        $cases = [];
        $bindings = [];
        foreach ($changes as $id => $json) {
            $cases[] = 'WHEN ? THEN ?';
            array_push($bindings, $id, $json);
        }
        $ids = implode(',', array_fill(0, count($changes), '?'));
        DB::update("UPDATE learning_runs SET $column = CASE id ".implode(' ', $cases)." END WHERE id IN ($ids)",
            [...$bindings, ...array_keys($changes)]);
    }
};
