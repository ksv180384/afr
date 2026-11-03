<?php

namespace App\Services\Learning;

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningCurriculumEnrollment;
use App\Models\Learning\LearningLevelEvent;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningRunOperation;
use App\Models\Learning\LearningSession;
use App\Models\User\User;
use App\Support\Learning\PracticeTime;
use Carbon\CarbonImmutable;

final class LevelPromotionService
{
    public function __construct(private readonly ObjectiveProgressService $objectives, private readonly SessionResultCalculator $calculator) {}

    private function policy(?LearningCurriculumEnrollment $enrollment, ?LearningCurriculum $curriculum): ?array
    {
        $version = $enrollment?->promotion_policy_version ?? $curriculum?->promotion_policy_version;
        $rules = $enrollment?->promotion_policy_snapshot ?? $curriculum?->promotion_policy_snapshot;
        if ($version !== 1 || ! is_array($rules)) {
            return null;
        }
        foreach (['required_objectives_percent', 'checkpoint_score_percent'] as $key) {
            if (! is_int($rules[$key] ?? null) || $rules[$key] < 1 || $rules[$key] > 100) {
                return null;
            }
        }
        foreach (['distinct_tasks_per_objective', 'distinct_runs_per_objective', 'distinct_checkpoint_sessions', 'distinct_local_checkpoint_days', 'min_distinct_skills'] as $key) {
            if (($rules[$key] ?? null) !== 2) {
                return null;
            }
        }
        if (($rules['required_skill'] ?? null) !== 'vocabulary' || ($rules['allow_technical_skips'] ?? null) !== false
            || ($rules['requires_published_curriculum'] ?? null) !== true || ($rules['requires_published_checkpoints'] ?? null) !== true) {
            return null;
        }

        return $rules;
    }

    private function ready(?LearningCurriculum $curriculum): bool
    {
        if (! $curriculum?->published_at || ! $curriculum->required_objective_ids || count(array_unique($curriculum->checkpoint_session_ids)) < 2) {
            return false;
        }
        if (\App\Models\Learning\LearningObjective::query()->where('level', $curriculum->getRawOriginal('level'))
            ->whereIn('stable_id', $curriculum->required_objective_ids)->count() !== count($curriculum->required_objective_ids)) {
            return false;
        }
        $sessions = LearningSession::query()->where('level', $curriculum->getRawOriginal('level'))
            ->whereIn('external_id', $curriculum->checkpoint_session_ids)->whereNull('archived_at')->with('activeVersion', 'unit', 'levelDefinition')->get();

        return $sessions->count() === count($curriculum->checkpoint_session_ids) && $sessions->every(
            fn ($session) => $session->unit?->published_at !== null && $session->unit?->archived_at === null && $session->levelDefinition?->published_at !== null
                && $session->activeVersion?->status->value === 'published'
                && ($session->activeVersion->payload['kind'] ?? null) === 'checkpoint'
        );
    }

    private function checkpoints(User $actor, ?LearningCurriculumEnrollment $enrollment, ?array $current): array
    {
        if (! $enrollment) {
            return [];
        }
        $records = LearningRun::query()->where('user_id', $actor->id)->where('status', 'completed')
            ->whereNotNull('session_version_id')->get(['id', 'result'])->map(fn ($run) => $run->result['checkpoint'] ?? null)
            ->filter(fn ($receipt) => ($receipt['qualified'] ?? false) && ($receipt['enrollment_id'] ?? null) === $enrollment->id)->values()->all();
        if ($current && $current['qualified']) {
            $records[] = $current;
        }

        return $records;
    }

    private function pair(array $records): ?array
    {
        foreach ($records as $i => $first) {
            foreach (array_slice($records, $i + 1) as $second) {
                $skills = array_values(array_unique([...$first['skills'], ...$second['skills']]));
                if ($first['session_id'] !== $second['session_id'] && $first['local_date'] !== $second['local_date']
                    && in_array('vocabulary', $skills, true) && count($skills) >= 2) {
                    return [$first, $second];
                }
            }
        }

        return null;
    }

    public function progress(User $actor, ?array $current = null): array
    {
        $profile = LearningProfile::query()->where('user_id', $actor->id)->first();
        $level = $profile?->getRawOriginal('current_level') ?? 'PRE_A1';
        $enrollment = $this->objectives->current($actor, $level);
        $curriculum = $enrollment?->curriculum ?? $this->objectives->published($level);
        $policy = $this->policy($enrollment, $curriculum);
        $goals = $this->objectives->goals($enrollment, $curriculum);
        $mastered = count(array_filter($goals, fn ($goal) => $goal['mastered']));
        $records = $this->checkpoints($actor, $enrollment, $current);
        $pair = $this->pair($records);
        $next = LearningLevel::from($level)->next()?->value;
        $enabled = $next !== null && $policy !== null && $this->ready($curriculum);
        $latest = LearningLevelEvent::query()->where('user_id', $actor->id)->orderByDesc('occurred_at')->orderByDesc('id')->first();

        return ['level' => $level, 'next_level' => $next, 'enabled' => $enabled,
            'completion_route' => ! $curriculum ? app(LearningAccessService::class)->completionProgress($actor, $level) : null,
            'disabled_reason' => $enabled ? null : ($next === null ? 'C2 — последняя ступень. Следующего уровня нет.'
                : ($curriculum && ! $policy ? 'Политика этой программы пока не поддерживается.' : 'Для уровня пока нет полной опубликованной программы и двух доступных контролей. Уровень можно менять вручную.')),
            'enrollment_id' => $enrollment?->id, 'curriculum_id' => $curriculum?->id, 'curriculum_version' => $curriculum?->version,
            'policy_version' => $enrollment?->promotion_policy_version ?? $curriculum?->promotion_policy_version,
            'available_checkpoints' => $curriculum ? LearningSession::query()->where('level', $level)->whereIn('external_id', $curriculum->checkpoint_session_ids)
                ->whereNull('archived_at')->whereHas('unit', fn ($query) => $query->whereNotNull('published_at')->whereNull('archived_at'))
                ->whereHas('levelDefinition', fn ($query) => $query->whereNotNull('published_at'))
                ->whereHas('activeVersion', fn ($query) => $query->where('status', 'published'))->with('activeVersion')->get()
                ->filter(fn ($session) => ($session->activeVersion->payload['kind'] ?? null) === 'checkpoint')
                ->map(fn ($session) => ['id' => $session->external_id, 'title' => $session->activeVersion->payload['title']])->values()->all() : [],
            'goals' => $goals, 'mastered' => $mastered, 'required' => count($goals),
            'required_to_master' => $policy && count($goals) ? (int) ceil(count($goals) * $policy['required_objectives_percent'] / 100) : 0,
            'goals_met' => $policy && count($goals) > 0 && $mastered * 100 >= count($goals) * $policy['required_objectives_percent'],
            'checkpoint_count' => count(array_unique(array_column($records, 'session_id'))),
            'checkpoint_days' => count(array_unique(array_column($records, 'local_date'))),
            'checkpoints_met' => $pair !== null, 'qualifying_checkpoints' => $pair ?? [], 'checkpoints' => $records,
            'policy' => $policy, 'manual_guard' => $profile?->last_manual_level_event_id !== null,
            'completed_runs' => LearningRun::query()->where('user_id', $actor->id)->where('status', 'completed')->count(),
            'latest_level_event' => $latest ? ['id' => $latest->id, 'source' => $latest->source,
                'old_level' => $latest->getRawOriginal('old_level'), 'new_level' => $latest->getRawOriginal('new_level'),
                'reason' => $latest->reason, 'occurred_at' => $latest->occurred_at->toIso8601String()] : null];
    }

    private function afterManualChange(LearningRun $run, LearningProfile $profile): bool
    {
        if (! $profile->last_manual_level_event_id) {
            return true;
        }
        if ($run->import_meta !== null) {
            return false;
        }
        $manual = $profile->lastManualLevelEvent;
        $manualOperation = LearningRunOperation::query()->where('user_id', $profile->user_id)
            ->where('client_event_id', $manual->source_event_id)->value('id');
        $startOperation = LearningRunOperation::query()->where('user_id', $profile->user_id)
            ->where('run_id', $run->id)->where('operation', 'start')->min('id');

        return $manualOperation && $startOperation ? $startOperation > $manualOperation : $run->started_at->gt($manual->occurred_at);
    }

    public function finishing(LearningRun $run, array $summary, CarbonImmutable $finishedAt): array
    {
        $actor = $run->user;
        $level = $run->snapshot['level'] ?? null;
        $enrollment = $level && $run->session_version_id ? $this->objectives->pin($actor, $level) : null;
        $policy = $this->policy($enrollment, $enrollment?->curriculum);
        $checkpoint = null;
        if ($enrollment && $policy && ($run->snapshot['kind'] ?? null) === 'checkpoint'
            && $run->sessionVersion?->published_at && in_array($run->snapshot['id'], $enrollment->curriculum->checkpoint_session_ids, true)) {
            $context = app(GuestImportContext::class);
            $clockWarning = ($run->import_meta['clock_warning'] ?? false) || ($run->import_meta !== null && $context->episode !== null && $context->sourceAt === null);
            $time = app(GuestImportContext::class)->sourceAt ?? $finishedAt;
            $skills = $run->attempts()->where('is_first_answer', true)->get()->filter(
                fn ($attempt) => ($attempt->result['first_independent_success'] ?? false) && ! ($attempt->result['help_used'] ?? true)
            )->map(fn ($attempt) => $attempt->result['skill'] ?? null)->filter()->unique()->values()->all();
            $checkpoint = ['run_id' => $run->id, 'enrollment_id' => $enrollment->id, 'session_id' => $run->snapshot['id'],
                'session_version_id' => $run->session_version_id, 'local_date' => PracticeTime::localDate($time, $run->timezone),
                'timezone' => $run->timezone, 'skills' => $skills, 'numerator' => $summary['score_numerator'], 'denominator' => $summary['score_denominator'],
                'qualified' => ! $clockWarning && $this->calculator->meetsCheckpointThreshold($summary, $policy['checkpoint_score_percent'])];
        }
        $progress = $this->progress($actor, $checkpoint);
        $profile = LearningProfile::query()->where('user_id', $actor->id)->first();
        $event = null;
        if ($checkpoint && $checkpoint['qualified'] && $profile && $progress['enrollment_id'] === $enrollment->id
            && $progress['enabled'] && $progress['goals_met'] && $progress['checkpoints_met'] && $this->afterManualChange($run, $profile)) {
            $next = LearningLevel::from($profile->getRawOriginal('current_level'))->next();
            $reason = ['message' => 'Освоены '.$progress['mastered'].' из '.$progress['required'].' целей программы. Два разных контроля пройдены не ниже '.$policy['checkpoint_score_percent'].'% в разные дни; подтверждены словарь и другой навык.',
                'enrollment_id' => $enrollment->id, 'curriculum_id' => $enrollment->curriculum_version_id,
                'curriculum_version' => $enrollment->curriculum->version, 'promotion_policy_version' => $enrollment->promotion_policy_version,
                'mastered' => $progress['mastered'], 'required' => $progress['required'], 'checkpoints' => $progress['qualifying_checkpoints']];
            $event = LearningLevelEvent::create(['user_id' => $actor->id, 'old_level' => $profile->getRawOriginal('current_level'),
                'new_level' => $next->value, 'source' => 'automatic', 'source_event_id' => $run->id, 'reason' => $reason, 'occurred_at' => $finishedAt]);
            $profile->update(['current_level' => $next->value, 'revision' => $profile->revision + 1]);
        }

        // Published curricula keep their existing mastery policy. Routes without a curriculum advance on completion.
        if (! $event && $profile && ! $enrollment && ! $this->objectives->published($profile->getRawOriginal('current_level'))
            && in_array($run->kind, ['daily', 'extra'], true) && $this->afterManualChange($run, $profile)) {
            $base = $profile->getRawOriginal('current_level');
            $order = array_column(config('daily_practice.levels'), 'code');
            if (array_search($level, $order, true) !== false && array_search($level, $order, true) >= array_search($base, $order, true)) {
                $route = app(LearningAccessService::class)->completionProgress($actor, $base, $run);
                if ($route['complete'] && $route['next_level']) {
                    $event = LearningLevelEvent::create(['user_id' => $actor->id, 'old_level' => $base,
                        'new_level' => $route['next_level'], 'source' => 'automatic', 'source_event_id' => $run->id,
                        'reason' => ['method' => 'published_route_completion', 'message' => 'Завершены все опубликованные занятия уровня.',
                            'completed' => $route['completed'], 'total' => $route['total']], 'occurred_at' => $finishedAt]);
                    $profile->update(['current_level' => $route['next_level'], 'revision' => $profile->revision + 1]);
                }
            }
        }

        return ['objectives' => ['enrollment_id' => $enrollment?->id,
            'mastered' => count(array_filter($this->objectives->goals($enrollment, $enrollment?->curriculum), fn ($goal) => $goal['mastered'])),
            'required' => count($enrollment?->curriculum->required_objective_ids ?? [])], 'checkpoint' => $checkpoint,
            'promotion' => $event ? ['old_level' => $event->getRawOriginal('old_level'), 'new_level' => $event->getRawOriginal('new_level'), 'reason' => $event->reason] : null];
    }
}
