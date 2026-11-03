<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Enums\Learning\RunStatus;
use App\Models\Learning\DailyAssignment;
use App\Models\Learning\LearningActivityDay;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningLevelEvent;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningRunOperation;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use App\Support\Learning\PracticeTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccountRunService
{
    public function __construct(private readonly AccountRunView $view, private readonly RunAnswerService $answers, private readonly RunIntegrations $integrations, private readonly SessionContentCache $content) {}

    public function owned(User $actor, string $id, bool $lock = false): LearningRun
    {
        $query = LearningRun::query()->where('user_id', $actor->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->find($id) ?? throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Прохождение не найдено.');
    }

    public function load(User $actor, string $id): array
    {
        return DB::transaction(fn () => $this->view->make($actor, $this->owned($actor, $id, true)));
    }

    public function profile(User $actor): array
    {
        $profile = LearningProfile::query()->where('user_id', $actor->id)->first();

        return ['owner_user_id' => $actor->id, 'current_level' => $profile?->getRawOriginal('current_level') ?? 'PRE_A1',
            'timezone' => $profile?->timezone ?? PracticeTime::DEFAULT_TIMEZONE, 'revision' => $profile?->revision ?? 1, 'persisted' => $profile !== null];
    }

    public function updateTimezone(User $actor, string $timezone): void
    {
        PracticeTime::timezone($timezone);
        DB::transaction(function () use ($actor, $timezone): void {
            User::query()->lockForUpdate()->findOrFail($actor->id);
            $profile = LearningProfile::firstOrCreate(['user_id' => $actor->id], ['current_level' => 'PRE_A1', 'timezone' => $timezone, 'revision' => 1]);
            if ($profile->timezone !== $timezone) {
                $profile->update(['timezone' => $timezone, 'revision' => $profile->revision + 1]);
            }
        }, 3);
    }

    private function completed(User $actor): array
    {
        $versions = LearningRun::query()->where('user_id', $actor->id)->where('status', 'completed')->whereIn('kind', ['daily', 'extra'])
            ->whereNotNull('session_version_id')->distinct()->pluck('session_version_id');

        return LearningSessionVersion::query()->with('session:id,external_id')->whereIn('id', $versions)->get(['id', 'session_id'])
            ->map(fn ($version) => $version->session->external_id)->unique()->values()->all();
    }

    private function available(LearningSession $session): bool
    {
        $session->loadMissing('activeVersion', 'unit', 'levelDefinition');

        return $session->archived_at === null && $session->activeVersion?->status->value === 'published'
            && $session->unit->published_at !== null && $session->unit->archived_at === null && $session->levelDefinition->published_at !== null;
    }

    private function candidate(User $actor, ?array $access = null): ?LearningSession
    {
        $completed = $this->completed($actor);
        $access ??= app(LearningAccessService::class)->states(app(LearningAccessService::class)->outline(), $completed, $this->profile($actor)['current_level']);
        $allowed = array_keys(array_filter($access['sessions'], fn ($state) => ! $state['locked'] && ! $state['completed'] && ! $state['review_only']));
        $query = LearningSession::query()->select('learning_sessions.*')->join('learning_units', 'learning_units.id', '=', 'learning_sessions.unit_id')
            ->whereIn('learning_sessions.external_id', $allowed)->whereNull('learning_sessions.archived_at')->whereNotNull('learning_sessions.active_version_id')
            ->whereNotIn('learning_sessions.external_id', $completed)->with('activeVersion', 'unit', 'levelDefinition')
            ->orderBy('learning_units.sort_order')->orderBy('learning_sessions.sort_order')->orderBy('learning_sessions.external_id');
        foreach ($query->lazy(100) as $session) {
            if ($this->available($session)) {
                return $session;
            }
        }

        return null;
    }

    public function today(User $actor): array
    {
        $profile = $this->profile($actor);
        $date = PracticeTime::localDate(PracticeTime::now(), $profile['timezone']);
        $assignment = DailyAssignment::query()->where('user_id', $actor->id)->orderByDesc('id')->first();
        $selected = $assignment?->selected_run_id ? $this->owned($actor, $assignment->selected_run_id) : null;
        $show = $selected && ($selected->status !== RunStatus::Completed || $assignment->local_date->format('Y-m-d') === $date);
        $access = app(LearningAccessService::class)->forUser($actor);
        $candidate = $this->candidate($actor, $access);
        $progress = app(LevelPromotionService::class)->progress($actor);
        if (isset($progress['available_checkpoints'])) {
            $progress['available_checkpoints'] = array_values(array_filter($progress['available_checkpoints'], fn ($item) => isset($access['sessions'][$item['id']]) && ! $access['sessions'][$item['id']]['locked']));
        }
        $selected?->loadMissing('sessionVersion.session.unit');

        return ['progress' => $progress, 'profile' => $profile, 'local_date' => $date, 'assignment' => $show ? ['id' => $assignment->id,
            'local_date' => $assignment->local_date->format('Y-m-d'), 'revision' => $assignment->revision, 'selected_run_id' => $selected->id] : null,
            'run' => $show ? ['id' => $selected->id, 'status' => $selected->status->value, 'level' => $selected->snapshot['level'] ?? null, 'theme' => $selected->sessionVersion?->session?->unit?->title, 'duration_minutes' => $selected->snapshot['duration_minutes'] ?? 3, 'title' => $selected->snapshot['title'] ?? 'Повторение'] : null,
            'candidate' => $candidate ? ['id' => $candidate->external_id, 'level' => $candidate->getRawOriginal('level'), 'theme' => $candidate->unit->title, 'duration_minutes' => $candidate->activeVersion->payload['duration_minutes'], 'title' => $candidate->activeVersion->payload['title']] : null,
            'activity_today' => LearningActivityDay::query()->where('user_id', $actor->id)->where('local_date', $date)->exists(),
            'exhausted' => $candidate === null];
    }

    private function envelope(LearningRunOperation $operation): array
    {
        return [...$operation->receipt, 'accepted_event_id' => $operation->client_event_id, 'record_sequence' => $operation->id];
    }

    private function operation(User $actor, string $name, array $input, ?string $runId, \Closure $apply): array
    {
        if (! is_string($input['client_event_id'] ?? null) || ! Str::isUuid($input['client_event_id'])
            || ! is_int($input['expected_revision'] ?? null) || $input['expected_revision'] < 1) {
            AnswerChecker::invalid('Некорректный конверт события.');
        }
        $hash = CanonicalJson::hash(['operation' => $name, 'run_id' => $runId, 'input' => $input]);

        return DB::transaction(function () use ($actor, $name, $input, $runId, $apply, $hash) {
            if (! User::query()->lockForUpdate()->find($actor->id)) {
                throw new AnswerCheckingException(PracticeErrorCode::Unauthenticated, 'Войдите в аккаунт.');
            }
            $run = $runId ? $this->owned($actor, $runId, true) : null;
            $previous = LearningRunOperation::query()->where('user_id', $actor->id)->where('client_event_id', $input['client_event_id'])->first();
            if ($previous) {
                if (! hash_equals($previous->payload_hash, $hash)) {
                    throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Этот ID уже принят с другим содержимым.');
                }

                return $this->envelope($previous);
            }
            if (! in_array($name, ['answer', 'help'], true) && LearningAttempt::query()->where('user_id', $actor->id)->where('client_event_id', $input['client_event_id'])->exists()) {
                throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Этот ID уже использован для ответа.');
            }
            $receipt = $apply($run);
            $record = LearningRunOperation::create(['user_id' => $actor->id, 'client_event_id' => $input['client_event_id'],
                'run_id' => $receipt['state']['run_id'] ?? $runId, 'operation' => $name, 'payload_hash' => $hash, 'receipt' => $receipt, 'received_at' => PracticeTime::now()]);

            return $this->envelope($record);
        }, 3);
    }

    public function profileUpdate(User $actor, array $input): array
    {
        return $this->operation($actor, 'profile', $input, null, function () use ($actor, $input) {
            PracticeTime::timezone($input['timezone']);
            $profile = LearningProfile::firstOrCreate(['user_id' => $actor->id], ['current_level' => 'PRE_A1', 'timezone' => 'UTC', 'revision' => 1]);
            $this->revision($profile->revision, $input);
            $attributes = ['current_level' => $input['current_level'], 'timezone' => $input['timezone'], 'revision' => $profile->revision + 1];
            if ($profile->getRawOriginal('current_level') !== $input['current_level']) {
                $event = LearningLevelEvent::create(['user_id' => $actor->id, 'old_level' => $profile->getRawOriginal('current_level'), 'new_level' => $input['current_level'],
                    'source' => 'manual', 'source_event_id' => $input['client_event_id'], 'reason' => ['profile_revision' => $attributes['revision']], 'occurred_at' => PracticeTime::now()]);
                $attributes['last_manual_level_event_id'] = $event->id;
            }
            $profile->update($attributes);

            return ['profile' => $this->profile($actor)];
        });
    }

    private function revision(int $revision, array $input): void
    {
        if ($input['expected_revision'] !== $revision) {
            throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Состояние изменено. Загрузите актуальные данные.');
        }
    }

    public function start(User $actor, array $input): array
    {
        return $this->operation($actor, 'start', $input, null, function () use ($actor, $input) {
            $kind = $input['kind'];
            if (! in_array($kind, ['daily', 'extra', 'review', 'vocabulary_test'], true)) {
                AnswerChecker::invalid('Неизвестный вид прохождения.');
            }
            if (($kind !== 'daily' && ! empty($input['replace_assignment'])) || ($kind !== 'extra' && ! empty($input['session_id']))
                || (! in_array($kind, ['review', 'vocabulary_test'], true) && (isset($input['word_ids']) || isset($input['continue_run_id']) || isset($input['filter']) || isset($input['source_session'])))) {
                AnswerChecker::invalid('Поля не соответствуют виду прохождения.');
            }
            $timezone = $input['timezone'] ?? PracticeTime::DEFAULT_TIMEZONE;
            PracticeTime::timezone($timezone);
            $profile = LearningProfile::firstOrCreate(['user_id' => $actor->id], ['current_level' => 'PRE_A1', 'timezone' => $timezone, 'revision' => 1]);
            $this->revision($profile->revision, $input);
            $date = PracticeTime::localDate(PracticeTime::now(), $profile->timezone);
            $latest = $kind === 'daily' ? DailyAssignment::query()->where('user_id', $actor->id)->orderByDesc('id')->first() : null;
            $selected = $latest?->selected_run_id ? $this->owned($actor, $latest->selected_run_id, true) : null;
            $replace = (bool) ($input['replace_assignment'] ?? false);
            if ($selected && $replace && $selected->status !== RunStatus::Completed && ($selected->snapshot['level'] ?? null) === $profile->getRawOriginal('current_level')) {
                AnswerChecker::invalid('Начатое назначение можно отложить для другого уровня. Для повтора используйте дополнительную практику.');
            }
            if ($selected && ! $replace && ($selected->status !== RunStatus::Completed || $latest->local_date->format('Y-m-d') === $date)) {
                return ['state' => $this->view->make($actor, $selected)];
            }
            $version = null;
            if (in_array($kind, ['daily', 'extra'], true)) {
                $session = $kind === 'daily' ? $this->candidate($actor)
                    : LearningSession::query()->where('external_id', $input['session_id'] ?? '')->first();
                if (! $session) {
                    throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Подходящих опубликованных занятий пока нет.');
                }
                $session = LearningSession::query()->lockForUpdate()->findOrFail($session->id);
                $access = app(LearningAccessService::class)->forUser($actor)['sessions'][$session->external_id] ?? ['locked' => true, 'reason' => 'Занятие пока недоступно.'];
                if ($access['locked']) {
                    throw new AnswerCheckingException(PracticeErrorCode::ValidationFailed, $access['reason']);
                }
                $completed = $this->completed($actor);
                if (! $this->available($session)) {
                    $missing = array_diff($session->activeVersion?->payload['prerequisite_ids'] ?? [], $completed);
                    if ($missing !== []) {
                        $previous = LearningSession::query()->with('activeVersion')->where('external_id', reset($missing))->first();
                        $title = $previous?->activeVersion?->payload['title'] ?? reset($missing);
                        throw new AnswerCheckingException(PracticeErrorCode::ValidationFailed, 'Сначала завершите предыдущее занятие «'.$title.'» ('.reset($missing).'). После этого станет доступно выбранное занятие.');
                    }
                    throw new AnswerCheckingException(PracticeErrorCode::ValidationFailed, 'Занятие сейчас недоступно. Выберите другое в практике.');
                }
                $version = $session->activeVersion;
                $snapshot = $this->content->payload($version);
                $items = array_map(fn ($task) => PresentationSnapshot::forTask($snapshot, $task['id']), $snapshot['tasks']);
            } else {
                [$snapshot, $items] = $this->reviewSnapshot($actor, $profile, $input);
            }
            app(ObjectiveProgressService::class)->pin($actor, $snapshot['level']);
            $run = LearningRun::create(['user_id' => $actor->id, 'kind' => $kind, 'status' => 'in_progress', 'session_version_id' => $version?->id,
                'snapshot' => $snapshot, ...CheckingPolicy::forVersion(config('daily_practice.checking.current_version'))->runAttributes(),
                'timezone' => $profile->timezone, 'local_date' => $date, 'started_at' => PracticeTime::now(), 'revision' => 1, 'cursor_position' => 1, 'introductions_seen' => []]);
            foreach ($items as $index => $item) {
                $run->presentations()->create(['user_id' => $actor->id, 'task_id' => $item['task']['id'], 'position' => $index + 1,
                    'snapshot' => $item, 'choice_order' => array_column($item['task']['choices'] ?? $item['task']['tokens'] ?? [], 'id'), 'status' => 'pending', 'help_used' => false]);
            }
            if ($kind === 'daily') {
                if ($selected && $selected->status === RunStatus::InProgress) {
                    $selected->update(['status' => 'paused', 'paused_at' => PracticeTime::now(), 'revision' => $selected->revision + 1]);
                }
                $assignment = DailyAssignment::firstOrCreate(['user_id' => $actor->id, 'local_date' => $date], ['revision' => 1]);
                $assignment->update(['selected_run_id' => $run->id, 'revision' => $assignment->revision + 1]);
            }

            return ['state' => $this->view->make($actor, $run)];
        });
    }

    private function reviewSnapshot(User $actor, LearningProfile $profile, array $input): array
    {
        return app(VocabularyTestService::class)->snapshot($actor, $input, $profile->getRawOriginal('current_level'));
    }

    public function event(User $actor, string $runId, string $name, array $input): array
    {
        return $this->operation($actor, $name, $input, $runId, function (LearningRun $run) use ($actor, $name, $input) {
            $previous = LearningAttempt::query()->where('user_id', $actor->id)->where('client_event_id', $input['client_event_id'])->first();
            if (! $previous) {
                $this->revision($run->revision, $input);
                $current = $run->presentations()->where('position', $run->cursor_position)->firstOrFail();
                if ($current->id !== $input['presentation_id']) {
                    throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Это не текущий шаг прохождения.');
                }
            }
            if (($name === 'answer') !== ($input['event_type'] === 'answer')) {
                AnswerChecker::invalid('Неверный endpoint события.');
            }
            $receipt = $this->answers->accept($actor, $run->id, $input);
            if (! $previous) {
                $this->integrations->accepted($run->fresh(), LearningAttempt::findOrFail($receipt['attempt_id']));
            }

            return ['state' => $this->view->make($actor, $run->fresh()), 'attempt' => $receipt];
        });
    }

    public function command(User $actor, string $runId, string $name, array $input): array
    {
        return $this->operation($actor, $name, $input, $runId, function (LearningRun $run) use ($actor, $name, $input) {
            if ($name === 'finish' && $run->status === RunStatus::Completed) {
                return ['state' => $this->view->make($actor, $run)];
            }
            $this->revision($run->revision, $input);
            if ($name === 'finish') {
                if ($run->status !== RunStatus::InProgress) {
                    throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Сначала продолжите прохождение.');
                }
                $summary = $this->answers->result($actor, $run->id);
                if (! $summary['all_steps_processed']) {
                    AnswerChecker::invalid('Сначала обработайте все шаги.');
                }
                $finishedAt = PracticeTime::now();
                $result = [...$summary, ...$this->integrations->finishing($run, $summary, $finishedAt)];
                $run->update(['status' => 'completed', 'finished_at' => $finishedAt, 'result' => $result, 'revision' => $run->revision + 1]);
                LearningActivityDay::firstOrCreate(['user_id' => $actor->id, 'local_date' => PracticeTime::localDate($finishedAt, $run->timezone)], ['qualifying_run_id' => $run->id]);
            } elseif (in_array($name, ['pause', 'resume'], true)) {
                if ($run->status === RunStatus::Completed) {
                    throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Завершённое прохождение неизменно.');
                }
                $status = $name === 'pause' ? 'paused' : 'in_progress';
                if ($run->status->value !== $status) {
                    $run->update(['status' => $status, 'paused_at' => $name === 'pause' ? PracticeTime::now() : null, 'revision' => $run->revision + 1]);
                }
            } elseif (in_array($name, ['advance', 'introduction'], true)) {
                if ($run->status !== RunStatus::InProgress) {
                    throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Прохождение сейчас не принимает переходы.');
                }
                $current = $run->presentations()->with('firstAttempt')->where('position', $run->cursor_position)->firstOrFail();
                if ($current->id !== $input['presentation_id']) {
                    throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Шаг уже изменён.');
                }
                if ($name === 'advance') {
                    if (! $current->firstAttempt || $run->cursor_position >= $run->presentations()->count()) {
                        AnswerChecker::invalid('Сначала обработайте шаг; последний шаг завершайте отдельным finish.');
                    }
                    $run->update(['cursor_position' => $run->cursor_position + 1, 'revision' => $run->revision + 1]);
                } else {
                    $seen = array_values(array_unique([...($run->introductions_seen ?? []), $current->id]));
                    $run->update(['introductions_seen' => $seen, 'revision' => $run->revision + 1]);
                }
            } else {
                AnswerChecker::invalid('Неизвестная операция прохождения.');
            }

            return ['state' => $this->view->make($actor, $run->fresh())];
        });
    }

    public function history(User $actor)
    {
        return LearningRun::query()->where('user_id', $actor->id)->select(['id', 'import_meta', 'kind', 'status', 'session_version_id', 'timezone', 'local_date', 'started_at', 'finished_at', 'result', 'revision'])
            ->selectSub(LearningRunOperation::query()->selectRaw('MIN(id)')->whereColumn('learning_run_operations.run_id', 'learning_runs.id')->where('operation', 'start'), 'record_sequence')
            ->orderByDesc('record_sequence')->orderByDesc('id')->paginate(20);
    }
}
