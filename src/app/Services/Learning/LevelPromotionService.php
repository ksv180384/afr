<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\User\User;

final class LevelPromotionService
{
    public function progress(User $actor): array
    {
        $profile = LearningProfile::query()->where('user_id', $actor->id)->first();
        $level = $profile?->getRawOriginal('current_level') ?? AvailableLevels::first();
        $route = app(LearningAccessService::class)->completionProgress($actor, $level);

        return ['level' => $level, 'next_level' => $route['next_level'], 'completion_route' => $route,
            'completed_runs' => LearningRun::query()->where('user_id', $actor->id)->where('status', 'completed')->count(),
            'latest_level_event' => $profile?->last_level_change_automatic ? ['source' => 'automatic', 'new_level' => $level] : null];
    }

    private function afterManualChange(LearningRun $run, LearningProfile $profile): bool
    {
        return $profile->last_manual_level_changed_at === null
            || ($run->import_meta === null && $run->started_at->greaterThanOrEqualTo($profile->last_manual_level_changed_at));
    }

    public function finishing(LearningRun $run): array
    {
        $actor = $run->user;
        $profile = LearningProfile::query()->where('user_id', $actor->id)->first();
        $promotion = null;
        if ($profile && in_array($run->kind, ['daily', 'extra'], true) && $this->afterManualChange($run, $profile)) {
            $base = $profile->getRawOriginal('current_level');
            if ($run->lesson()?->getRawOriginal('level') === $base) {
                $route = app(LearningAccessService::class)->completionProgress($actor, $base, $run);
                if ($route['complete'] && $route['next_level']) {
                    $promotion = ['old_level' => $base, 'new_level' => $route['next_level'],
                        'reason' => ['method' => 'published_route_completion', 'completed' => $route['completed'], 'total' => $route['total']]];
                    $profile->update(['current_level' => $route['next_level'], 'revision' => $profile->revision + 1,
                        'last_level_change_automatic' => true]);
                }
            }
        }

        return ['promotion' => $promotion];
    }
}
