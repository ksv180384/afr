<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;

class RunIntegrations
{
    public function accepted(LearningRun $run, LearningAttempt $attempt): void
    {
        app(VocabularyProgressService::class)->accepted($run, $attempt);
        app(ObjectiveProgressService::class)->accepted($run, $attempt);
    }

    public function finishing(LearningRun $run, array $summary, \Carbon\CarbonImmutable $finishedAt): array
    {
        return [...app(VocabularyProgressService::class)->finishing($run, $finishedAt),
            ...app(LevelPromotionService::class)->finishing($run, $summary, $finishedAt), 'integrations_pending' => []];
    }
}
