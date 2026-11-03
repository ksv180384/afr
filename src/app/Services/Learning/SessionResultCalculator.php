<?php

namespace App\Services\Learning;

final class SessionResultCalculator
{
    public function calculate(array $states, int $totalSteps, int $version): array
    {
        AnswerChecker::policy($version);
        if ($totalSteps < 0 || $totalSteps > 1000 || count($states) > $totalSteps) {
            throw new \InvalidArgumentException('Invalid number of assessed steps.');
        }
        $seen = [];
        $processed = 0;
        $earned = 0;
        $skips = 0;
        foreach ($states as $state) {
            if (isset($seen['#'.$state['presentation_id']])) {
                throw new \InvalidArgumentException('Duplicate presentation.');
            }
            $seen['#'.$state['presentation_id']] = true;
            $first = $state['first_result'];
            if ($first !== null) {
                if ($first['presentation_id'] !== $state['presentation_id'] || $first['checking_policy_version'] !== $version || ! $first['is_first_answer'] || ! in_array($first['score'], [0, 1], true)) {
                    throw new \InvalidArgumentException('Invalid first answer result.');
                }
                $processed++;
                $earned += $first['score'];
                $skips += $first['technical_skip'] ? 1 : 0;
            }
        }

        return ['checking_policy_version' => $version, 'total_steps' => $totalSteps, 'processed_steps' => $processed,
            'independent_correct_steps' => $earned, 'score_numerator' => $earned, 'score_denominator' => $totalSteps,
            'score_percent' => $totalSteps === 0 ? null : $earned * 100 / $totalSteps,
            'technical_skips' => $skips, 'all_steps_processed' => $totalSteps > 0 && $processed === $totalSteps];
    }

    public function meetsCheckpointThreshold(array $result, int $threshold): bool
    {
        if ($threshold < 0 || $threshold > 100) {
            throw new \InvalidArgumentException('Invalid score threshold.');
        }

        return $result['all_steps_processed'] && $result['technical_skips'] === 0
            && $threshold * $result['score_denominator'] <= $result['score_numerator'] * 100;
    }
}
