<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;

final class LearningAccessService
{
    public function outline(): array
    {
        $curricula = \App\Models\Learning\LearningCurriculum::query()->where('status', 'published')->pluck('level')->map(fn ($level) => $level instanceof \BackedEnum ? $level->value : $level)->all();
        $units = LearningUnit::query()->whereNotNull('published_at')->whereNull('archived_at')
            ->orderBy('sort_order')->orderBy('external_id')->get();
        $sessions = LearningSession::query()->whereNull('archived_at')
            ->whereHas('activeVersion', fn ($query) => $query->where('status', 'published'))
            ->with(['activeVersion' => fn ($query) => $query->select('id', 'session_id',
                'payload->title as access_title', 'payload->prerequisite_ids as access_prerequisites')])
            ->orderBy('sort_order')->orderBy('external_id')->get()->groupBy('unit_id');

        return LearningLevel::query()->orderBy('sort_order')->get()->map(fn ($level) => [
            'id' => $level->code, 'title' => $level->title, 'path' => PracticeUrls::level($level->code),
            'published' => $level->published_at !== null, 'has_curriculum' => in_array($level->code, $curricula, true),
            'units' => $units->filter(fn ($unit) => $unit->getRawOriginal('level') === $level->code)->values()->map(fn ($unit) => [
                'id' => $unit->external_id, 'title' => $unit->title, 'path' => PracticeUrls::unit($unit),
                'sessions' => ($sessions[$unit->id] ?? collect())->map(fn ($session) => [
                    'id' => $session->external_id, 'title' => $session->activeVersion->access_title,
                    'path' => $session->slug ? PracticeUrls::unit($unit).'/'.$session->slug : null,
                    'prerequisite_ids' => json_decode($session->activeVersion->access_prerequisites, true) ?? [],
                ])->all(),
            ])->all(),
        ])->all();
    }

    public function completed(User $actor): array
    {
        return LearningRun::query()->where('learning_runs.user_id', $actor->id)->where('learning_runs.status', 'completed')
            ->whereIn('learning_runs.kind', ['daily', 'extra'])
            ->join('learning_session_versions', 'learning_session_versions.id', '=', 'learning_runs.session_version_id')
            ->join('learning_sessions', 'learning_sessions.id', '=', 'learning_session_versions.session_id')
            ->distinct()->pluck('learning_sessions.external_id')->all();
    }

    public function states(array $outline, array $completed, string $profileLevel = 'PRE_A1'): array
    {
        $done = array_fill_keys($completed, true);
        $base = array_search($profileLevel, array_column($outline, 'id'), true);
        $base = $base === false ? 0 : $base;
        foreach (array_slice($outline, 0, $base) as $earlier) {
            foreach ($earlier['units'] as $unit) {
                foreach ($unit['sessions'] as $session) {
                    $done[$session['id']] = true;
                }
            }
        }
        $states = ['levels' => [], 'units' => [], 'sessions' => []];
        $prefix = true;
        $reason = '';
        foreach ($outline as $index => $level) {
            $lower = $index < $base;
            if ($index === $base) {
                $prefix = true;
                $reason = '';
            }
            $levelGate = ($lower || $prefix) && $level['published'];
            $levelReason = $reason;
            $levelIds = [];
            if (! $level['published'] || $level['units'] === []) {
                $prefix = false;
                $reason = $reason ?: 'Материалы предыдущего уровня ещё готовятся.';
            }
            foreach ($level['units'] as $unit) {
                $unitGate = ($lower || $prefix) && $level['published'];
                $unitReason = $reason;
                $unitIds = array_column($unit['sessions'], 'id');
                $levelIds = [...$levelIds, ...$unitIds];
                if ($unitIds === []) {
                    $prefix = false;
                    $reason = $reason ?: 'Занятия предыдущей темы ещё готовятся.';
                }
                foreach ($unit['sessions'] as $session) {
                    $finished = in_array($session['id'], $completed, true);
                    $missing = array_values(array_filter($session['prerequisite_ids'], fn ($id) => ! isset($done[$id])));
                    $allowed = $finished || ($lower && $level['published']) || ($prefix && $missing === []);
                    $message = $reason ?: ($missing !== [] ? 'Сначала завершите предыдущее занятие '.$missing[0].'.' : '');
                    $states['sessions'][$session['id']] = $this->status($allowed, $finished, $message, $session['path'], $lower);
                    if (! $finished && ! $lower) {
                        $prefix = false;
                        $reason = $reason ?: 'Сначала завершите занятие «'.$session['title'].'» ('.$session['id'].').';
                    }
                }
                $any = array_intersect($unitIds, $completed) !== [];
                $finished = $unitIds !== [] && array_diff($unitIds, $completed) === [];
                $states['units'][$unit['id']] = $this->status($unitGate || $any, $finished, $unitReason, $unit['path'], $lower);
            }
            $any = array_intersect($levelIds, $completed) !== [];
            $finished = $levelIds !== [] && collect($level['units'])->every(fn ($unit) => $unit['sessions'] !== []) && array_diff($levelIds, $completed) === [];
            $hasLessons = $level['published'] && $levelIds !== [];
            $states['levels'][$level['id']] = $this->status($hasLessons && ($levelGate || $any), $finished, $levelReason, $level['path'], $lower);
            if ($states['levels'][$level['id']]['locked']) {
                $states['levels'][$level['id']]['reason'] = $hasLessons ? 'Сначала завершите предыдущий уровень.' : 'Занятия ещё не опубликованы.';
            }
        }

        return $states;
    }

    private function status(bool $allowed, bool $completed, string $reason, ?string $path, bool $reviewOnly = false): array
    {
        return ['locked' => ! $allowed, 'completed' => $completed, 'review_only' => $reviewOnly, 'reason' => $allowed ? '' : ($reason ?: 'Сначала завершите предыдущий материал.'), 'path' => $path];
    }

    public function forUser(User $actor): array
    {
        return $this->states($this->outline(), $this->completed($actor), $this->profileLevel($actor));
    }

    public function completionProgress(User $actor, string $level, ?LearningRun $finishing = null): array
    {
        $entry = collect($this->outline())->firstWhere('id', $level);
        $ids = $entry ? array_merge(...array_map(fn ($unit) => array_column($unit['sessions'], 'id'), $entry['units'])) : [];
        $completed = $this->completed($actor);
        if ($finishing && in_array($finishing->kind, ['daily', 'extra'], true) && ($finishing->snapshot['level'] ?? null) === $level) {
            $completed[] = $finishing->snapshot['id'];
        }
        $ready = $entry && $entry['published'] && $ids !== [] && collect($entry['units'])->every(fn ($unit) => $unit['sessions'] !== []);

        return ['total' => count($ids), 'completed' => count(array_intersect($ids, array_unique($completed))),
            'complete' => $ready && array_diff($ids, $completed) === [],
            'next_level' => \App\Enums\Learning\LearningLevel::from($level)->next()?->value];
    }

    public function profileLevel(User $actor): string
    {
        return LearningProfile::query()->where('user_id', $actor->id)->first()?->getRawOriginal('current_level') ?? 'PRE_A1';
    }

    public function decorate(array $catalog, array $states): array
    {
        $paths = [];
        foreach ($states as $group) {
            foreach ($group as $state) {
                if ($state['path']) {
                    $paths[$state['path']] = $state;
                }
            }
        }
        $catalog['access'] = $paths[$catalog['path']] ?? ['locked' => false, 'completed' => false, 'reason' => ''];
        $decorate = fn ($card) => [...$card, ...($paths[$card['path'] ?? ''] ?? ['locked' => false, 'completed' => false, 'reason' => '']), 'path' => $card['path']];
        // Retain the original path even when a card is locked; the UI renders plain text.
        foreach (['cards', 'related'] as $field) {
            $catalog[$field] = array_map($decorate, $catalog[$field]);
        }
        foreach (['previous', 'next'] as $field) {
            if ($catalog[$field]) {
                $catalog[$field] = $decorate($catalog[$field]);
            }
        }

        return $catalog;
    }
}
