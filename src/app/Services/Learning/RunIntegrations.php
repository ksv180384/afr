<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;

class RunIntegrations
{
    public function accepted(LearningRun $run, LearningAttempt $attempt): void
    {
        if ($run->session_id === null || $run->import_meta !== null) {
            app(VocabularyProgressService::class)->accepted($run, $attempt);
        }

    }

    public function finishing(LearningRun $run, array $summary, \Carbon\CarbonImmutable $finishedAt): array
    {
        $vocabulary = app(VocabularyProgressService::class);
        if ($run->session_id !== null && $run->import_meta === null) {
            foreach ($run->attempts()->orderBy('sequence')->get() as $attempt) {
                $vocabulary->accepted($run, $attempt);
            }
        }

        return [...$vocabulary->finishing($run, $finishedAt),
            ...app(LevelPromotionService::class)->finishing($run), 'integrations_pending' => []];
    }
}
