<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;

final class LessonCorrections
{
    public static function positions(LearningRun $run, iterable $presentations): array
    {
        if (! in_array($run->kind, ['daily', 'extra'], true)) {
            return [];
        }
        $positions = [];
        foreach ($presentations as $presentation) {
            $first = $presentation->firstAttempt?->result;
            if (($first['correct'] ?? null) === false && ! $first['technical_skip']) {
                $positions[] = $presentation->position;
            }
        }

        return $positions;
    }

    public static function attempt(LearningRun $run): ?LearningAttempt
    {
        $id = $run->corrections['attempt_id'] ?? null;

        return $id ? $run->attempts()->find($id) : null;
    }

    public static function canFinish(array $corrections, ?bool $correct): bool
    {
        return $corrections['started'] && $corrections['index'] === count($corrections['positions']) - 1
            && empty($corrections['remaining']) && $correct === true;
    }

    public static function advance(array $corrections, ?bool $correct): array
    {
        if (! $corrections['started']) {
            $corrections['started'] = true;

            return $corrections;
        }
        if ($correct === null) {
            AnswerChecker::invalid('Сначала ответьте на текущее задание.');
        }
        $positions = $corrections['positions'];
        $remaining = $corrections['remaining'] ?? [];
        if (! $correct) {
            $remaining[] = $positions[$corrections['index']];
        }
        if (isset($positions[$corrections['index'] + 1])) {
            $corrections['index']++;
            $corrections['remaining'] = $remaining;
            $corrections['attempt_id'] = null;

            return $corrections;
        }
        if ($remaining === []) {
            AnswerChecker::invalid('Все ошибки исправлены. Завершите занятие.');
        }

        return [
            'positions' => $remaining, 'index' => 0, 'started' => false, 'attempt_id' => null, 'remaining' => [],
            'round' => ($corrections['round'] ?? 1) + 1, 'initial_total' => $corrections['initial_total'] ?? count($positions),
            'fixed_last_round' => count($positions) - count($remaining),
        ];
    }
}
