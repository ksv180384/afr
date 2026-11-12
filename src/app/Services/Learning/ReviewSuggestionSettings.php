<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\LearningProfile;
use App\Models\User\User;
use App\Support\Learning\PracticeTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class ReviewSuggestionSettings
{
    public function available(): bool
    {
        return Schema::hasColumns('learning_profiles', ['review_suggestions_enabled', 'review_suggestions_snoozed_until']);
    }

    public function view(?LearningProfile $profile): array
    {
        $available = $this->available();
        $enabled = $available && ($profile?->review_suggestions_enabled ?? true);
        $until = $available ? $profile?->review_suggestions_snoozed_until : null;

        return ['available' => $available, 'enabled' => (bool) $enabled,
            'snoozed_until' => $until?->utc()->toIso8601String(),
            'can_suggest' => $enabled && (! $until || $until->lessThanOrEqualTo(PracticeTime::now()))];
    }

    public function apply(LearningProfile $profile, array $input): void
    {
        if ($input['action'] === 'toggle') {
            // Re-enabling never silently cancels an active refusal for today.
            $profile->review_suggestions_enabled = $input['enabled'];
        } else {
            $next = PracticeTime::now()->setTimezone(PracticeTime::timezone($profile->timezone))->addDay()->startOfDay()->utc();
            // A timezone change must not shorten an already accepted refusal.
            $profile->review_suggestions_snoozed_until = $profile->review_suggestions_snoozed_until?->greaterThan($next)
                ? $profile->review_suggestions_snoozed_until : $next;
        }
        $profile->revision++;
        $profile->save();
    }

    /** Called under the sync batch's actor lock, before any level/run mutations. */
    public function mergeGuestSnooze(User $actor, ?array $input): bool
    {
        if (! $input || ! $this->available()) {
            return false;
        }
        $profile = LearningProfile::where('user_id', $actor->id)->first();
        if (($profile?->revision ?? 1) !== $input['expected_revision']) {
            return false;
        }
        $current = $this->view($profile);
        if (! $current['enabled'] || ($current['snoozed_until'] && CarbonImmutable::parse($current['snoozed_until'])->isAfter(PracticeTime::now()))) {
            return false;
        }
        $until = CarbonImmutable::parse($input['until'])->utc();
        $limit = PracticeTime::now()->setTimezone(PracticeTime::timezone($input['timezone']))->addDay()->startOfDay()->utc();
        if (! $until->isAfter(PracticeTime::now()) || $until->isAfter($limit)) {
            return false;
        }
        $profile ??= LearningProfile::create(['user_id' => $actor->id, 'current_level' => AvailableLevels::first(),
            'timezone' => PracticeTime::DEFAULT_TIMEZONE, 'revision' => 1]);
        $profile->update(['review_suggestions_snoozed_until' => $until, 'revision' => $profile->revision + 1]);

        return true;
    }
}
