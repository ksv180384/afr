<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Enums\Learning\RunStatus;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningPresentation;
use App\Models\Learning\LearningRun;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RunAnswerService
{
    public function __construct(private readonly PresentationEvaluator $evaluator, private readonly SessionResultCalculator $calculator) {}

    private function owner(?User $actor): int
    {
        if (! $actor) {
            throw new AnswerCheckingException(PracticeErrorCode::Unauthenticated, 'Войдите в аккаунт.');
        }

        return $actor->id;
    }

    private function pinned(LearningRun $run): void
    {
        $policy = AnswerChecker::policy($run->checking_policy_version);
        if (CanonicalJson::hash($run->checking_policy_snapshot) !== CanonicalJson::hash($policy->rules)) {
            throw new AnswerCheckingException(PracticeErrorCode::UnsupportedCheckingPolicy, 'Снимок правил run не поддерживается.');
        }
    }

    public function accept(?User $actor, string $runId, array $input): array
    {
        $ownerId = $this->owner($actor);
        $allowed = ['client_event_id', 'presentation_id', 'expected_revision', 'event_type'];
        foreach (['answer', 'help_kind', 'reason', 'client_occurred_at'] as $field) {
            if (array_key_exists($field, $input)) {
                $allowed[] = $field;
            }
        }
        AnswerChecker::keys($input, $allowed);
        if (! is_string($input['client_event_id']) || ! Str::isUuid($input['client_event_id'])
            || ! is_string($input['presentation_id']) || ! Str::isUuid($input['presentation_id'])
            || ! is_int($input['expected_revision']) || $input['expected_revision'] < 1) {
            AnswerChecker::invalid('Некорректный конверт учебного события.');
        }
        $occurredAt = null;
        if (array_key_exists('client_occurred_at', $input)) {
            $date = $input['client_occurred_at'];
            if (! is_string($date) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?(?:Z|[+-]\\d{2}:\\d{2})$/D', $date)) {
                AnswerChecker::invalid('Некорректное время клиентского события.');
            }
            $parsed = date_parse($date);
            if ($parsed['warning_count'] || $parsed['error_count']) {
                AnswerChecker::invalid('Некорректная календарная дата.');
            }
            $occurredAt = CarbonImmutable::parse($date)->utc();
        }
        $event = array_diff_key($input, array_flip(['client_event_id', 'expected_revision', 'client_occurred_at']));
        try {
            $hash = CanonicalJson::hash(['run_id' => $runId, 'event' => $input]);
        } catch (\JsonException) {
            AnswerChecker::invalid('Событие не является корректным JSON.');
        }
        try {
            return DB::transaction(function () use ($runId, $ownerId, $input, $event, $hash, $occurredAt): array {
                $run = LearningRun::query()->where('user_id', $ownerId)->lockForUpdate()->find($runId);
                if (! $run) {
                    throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Прохождение не найдено.');
                }
                $previous = LearningAttempt::query()->where('user_id', $ownerId)->where('client_event_id', $input['client_event_id'])->first();
                if ($previous) {
                    return $this->replay($previous, $hash);
                }
                $this->pinned($run);
                if ($run->status !== RunStatus::InProgress || $run->revision !== $input['expected_revision']) {
                    throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Прохождение изменилось; обновите его состояние.');
                }
                $presentation = $run->presentations()->with('firstAttempt')->where('user_id', $ownerId)->find($input['presentation_id']);
                if (! $presentation) {
                    throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Предъявление не найдено в этом прохождении.');
                }
                $this->bound($run, $presentation);
                $state = $this->state($presentation);
                $state = $this->evaluator->apply($presentation->snapshot, $state, $event, $run->checking_policy_version);
                $result = $state['last_result'];
                $revision = $run->revision + 1;
                $attempt = LearningAttempt::create([
                    'user_id' => $ownerId, 'run_id' => $runId, 'presentation_id' => $presentation->id,
                    'client_event_id' => $input['client_event_id'], 'event_type' => $input['event_type'],
                    'sequence' => ((int) $run->attempts()->max('sequence')) + 1,
                    'payload' => $input, 'payload_hash' => $hash, 'is_first_answer' => $result['is_first_answer'],
                    'received_at' => now('UTC'), 'client_occurred_at' => $occurredAt,
                    'result' => [...$result, 'run_revision' => $revision],
                ]);
                $presentation->update(['status' => $state['status'], 'help_used' => $state['help_used']]);
                $run->update(['revision' => $revision]);

                return $this->receipt($attempt);
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $existing = LearningAttempt::query()->where('user_id', $ownerId)->where('client_event_id', $input['client_event_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $hash);
                }
            }
            throw $exception;
        }
    }

    private function replay(LearningAttempt $attempt, string $hash): array
    {
        if (! hash_equals($attempt->payload_hash, $hash)) {
            throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Этот event ID уже принят с другим содержимым.');
        }

        return $this->receipt($attempt);
    }

    private function receipt(LearningAttempt $attempt): array
    {
        return ['attempt_id' => $attempt->id, 'client_event_id' => $attempt->client_event_id,
            'presentation_id' => $attempt->presentation_id, 'sequence' => $attempt->sequence, 'revision' => $attempt->result['run_revision'],
            'result' => array_diff_key($attempt->result, ['run_revision' => true])];
    }

    private function bound(LearningRun $run, LearningPresentation $presentation): void
    {
        if (($presentation->snapshot['task']['id'] ?? null) !== $presentation->task_id) {
            throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Идентификатор задания не совпадает со снимком предъявления.');
        }
        PresentationSnapshot::bound($run->snapshot, $presentation->position, $presentation->snapshot);
    }

    private function state(LearningPresentation $presentation): array
    {
        $first = $presentation->firstAttempt?->result;
        if ($first !== null) {
            unset($first['run_revision']);
        }

        return ['presentation_id' => $presentation->id, 'status' => $presentation->status, 'help_used' => $presentation->help_used,
            'first_result' => $first, 'last_result' => $first];
    }

    public function result(?User $actor, string $runId): array
    {
        $ownerId = $this->owner($actor);

        return DB::transaction(function () use ($ownerId, $runId): array {
            $run = LearningRun::query()->where('user_id', $ownerId)->lockForUpdate()->find($runId);
            if (! $run) {
                throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Прохождение не найдено.');
            }
            $this->pinned($run);
            $items = $run->snapshot['tasks'] ?? $run->snapshot['items'] ?? null;
            if (! is_array($items) || ! array_is_list($items)) {
                throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Снимок оцениваемых шагов недоступен.');
            }
            $states = [];
            foreach ($run->presentations()->with('firstAttempt')->get() as $presentation) {
                $this->bound($run, $presentation);
                $states[] = $this->state($presentation);
            }

            return $this->calculator->calculate($states, count($items), $run->checking_policy_version);
        });
    }
}
