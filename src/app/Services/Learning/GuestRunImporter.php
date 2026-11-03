<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\DailyAssignment;
use App\Models\Learning\GuestEventMapping;
use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\LearningPresentation;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningRunOperation;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use App\Support\Learning\PracticeTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class GuestRunImporter
{
    public function __construct(private GuestSyncLedger $ledger, private AccountRunService $runs, private AccountRunView $view) {}

    private function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return null;
        }
        $parsed = date_parse($value);

        return $parsed['error_count'] || $parsed['warning_count'] ? null : CarbonImmutable::parse($value)->utc();
    }

    private function source(User $actor, array $bundle): array
    {
        if (in_array($bundle['kind'], ['daily', 'extra'], true)) {
            $version = LearningSessionVersion::find($bundle['session_version_id'] ?? null);
            if (! $version || ! $version->published_at) {
                throw new AnswerCheckingException(PracticeErrorCode::VersionConflict, 'Изученная версия не найдена или не была опубликована.');
            }
            if (CanonicalJson::hash($bundle['snapshot']) !== CanonicalJson::hash($version->payload)) {
                throw new AnswerCheckingException(PracticeErrorCode::VersionConflict, 'Снимок гостевого занятия отличается от изученной версии.');
            }
            $prerequisites = $version->payload['prerequisite_ids'] ?? [];
            $completed = LearningRun::query()->join('learning_session_versions', 'learning_runs.session_version_id', '=', 'learning_session_versions.id')
                ->join('learning_sessions', 'learning_session_versions.session_id', '=', 'learning_sessions.id')->where('learning_runs.user_id', $actor->id)
                ->where('learning_runs.status', 'completed')->whereIn('learning_sessions.external_id', $prerequisites)->distinct()->count('learning_sessions.external_id');
            if ($completed !== count(array_unique($prerequisites))) {
                throw new AnswerCheckingException(PracticeErrorCode::VersionConflict, 'Сначала перенесите завершённые предыдущие занятия.');
            }

            return [$version->id, $version->payload, array_map(fn ($task) => PresentationSnapshot::forTask($version->payload, $task['id']), $version->payload['tasks'])];
        }
        $snapshot = $bundle['snapshot'];
        $review = $snapshot['review_items'] ?? null;
        if (! is_array($review) || count($review) < 1 || count($review) > 10) {
            AnswerChecker::invalid('Неверный набор повторения.');
        }
        $keys = array_column($review, 'review_lexeme_key');
        if (count(array_unique($keys)) !== count($review)) {
            AnswerChecker::invalid('Слово повторяется в одном тесте.');
        }
        $words = PersonalVocabulary::where('user_id', $actor->id)->whereIn('lexeme_key', $keys)->with('sources')->get()->keyBy('lexeme_key');
        $items = [];
        $vocabulary = [];
        foreach ($review as $item) {
            $word = $words[$item['review_lexeme_key']] ?? null;
            if (! $word) {
                throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Источник гостевого повторения ещё не перенесён.');
            }
            $sources = $word->sources->isEmpty() ? [$word->snapshot] : $word->sources->pluck('snapshot')->all();
            $matched = null;
            foreach ($sources as $source) {
                foreach ($source['review_items'] ?? [] as $option) {
                    if (CanonicalJson::hash($option) === CanonicalJson::hash($item['task'] ?? [])) {
                        $matched = $source;
                    }
                }
            }
            if (! $matched) {
                throw new AnswerCheckingException(PracticeErrorCode::VersionConflict, 'Вопрос повторения отсутствует в изученных источниках.');
            }
            $matched['id'] = 'word-'.$word->id;
            $matched['lexeme_key'] = $word->lexeme_key;
            $items[] = PresentationSnapshot::forReview($item['task'], $word->lexeme_key);
            $vocabulary[] = $matched;
        }
        $remaining = PersonalVocabulary::where('user_id', $actor->id)->whereIn('lexeme_key', $snapshot['remaining_word_ids'] ?? [])->pluck('id')->all();

        return [null, ['id' => $bundle['guest_run_id'], 'title' => 'Тест изученных выражений', 'level' => $snapshot['level'] ?? 'PRE_A1', 'duration_minutes' => 3,
            'description' => 'Начато до входа.', 'items' => $items, 'vocabulary' => $vocabulary, 'introductions' => [], 'remaining_word_ids' => $remaining], $items];
    }

    public function import(User $actor, GuestSyncEpisode $episode, array $bundle): array
    {
        Validator::make($bundle, ['guest_run_id' => 'required|uuid', 'kind' => 'required|in:daily,extra,review,vocabulary_test', 'snapshot' => 'required|array',
            'timezone' => 'required|timezone:all', 'local_date' => 'required|string|max:10', 'started_at' => 'required|string|max:40',
            'checking_policy_version' => 'required|integer', 'presentation_ids' => 'required|array|min:1|max:1000', 'presentation_ids.*' => 'uuid|distinct',
            'events' => 'required|array|min:1|max:2000', 'events.*' => 'array', 'events.*.client_event_id' => 'required|uuid', 'events.*.sequence' => 'required|integer|min:1', 'events.*.action' => 'required|string|max:32', 'events.*.client_occurred_at' => 'sometimes|string|max:40'])->validate();
        foreach ($bundle['events'] as $event) {
            if (($event['run_id'] ?? null) !== $bundle['guest_run_id']) {
                AnswerChecker::invalid('Событие относится к другому прохождению.');
            }
        }
        $hash = CanonicalJson::hash(array_intersect_key($bundle, array_flip(['guest_run_id', 'kind', 'session_version_id', 'snapshot', 'timezone', 'local_date', 'started_at', 'checking_policy_version', 'presentation_ids'])));
        $mapping = $episode->mappings()->where('entity_type', 'run')->where('guest_entity_id', $bundle['guest_run_id'])->first();
        if ($mapping && $mapping->receipt['source_hash'] !== $hash) {
            throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Снимок уже перенесённого прохождения изменился.');
        }
        if (! $mapping) {
            $first = $bundle['events'][0];
            if (($first['action'] ?? '') !== 'start') {
                AnswerChecker::invalid('Отсутствует начало гостевого прохождения.');
            }
            if ($this->ledger->previous($episode, $first)) {
                throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Старт уже относится к другому прохождению.');
            }
            [$versionId,$snapshot,$items] = $this->source($actor, $bundle);
            if (count($items) !== count($bundle['presentation_ids'])) {
                AnswerChecker::invalid('Порядок показов не соответствует версии.');
            }
            $policy = AnswerChecker::policy($bundle['checking_policy_version']);
            $now = PracticeTime::now();
            $start = $this->time($bundle['started_at']);
            $previousTime = $start;
            $clockWarning = ! $start || $start > $now || ($start && PracticeTime::localDate($start, $bundle['timezone']) !== $bundle['local_date']);
            foreach ($bundle['events'] as $event) {
                $time = $this->time($event['client_occurred_at'] ?? null);
                if (! $time || $time > $now || ($previousTime && $time < $previousTime)) {
                    $clockWarning = true;
                } $previousTime = $time;
            }
            app(ObjectiveProgressService::class)->pin($actor, $snapshot['level']);
            $run = LearningRun::create(['user_id' => $actor->id, 'kind' => $bundle['kind'], 'status' => 'in_progress', 'session_version_id' => $versionId, 'snapshot' => $snapshot,
                ...$policy->runAttributes(), 'timezone' => $bundle['timezone'], 'local_date' => $clockWarning ? PracticeTime::localDate($now, $bundle['timezone']) : PracticeTime::localDate($start, $bundle['timezone']),
                'started_at' => $now, 'cursor_position' => 1, 'introductions_seen' => [], 'revision' => 1,
                'import_meta' => ['guest_id' => $episode->guest_id, 'episode_id' => $episode->id, 'guest_run_id' => $bundle['guest_run_id'], 'received_at' => $now->toIso8601String(),
                    'original_local_date' => $bundle['local_date'], 'client_started_at' => $bundle['started_at'], 'clock_warning' => $clockWarning]]);
            $presentations = [];
            foreach ($items as $index => $item) {
                $presentation = LearningPresentation::create(['user_id' => $actor->id, 'run_id' => $run->id, 'task_id' => $item['task']['id'], 'position' => $index + 1, 'snapshot' => $item,
                    'choice_order' => array_column($item['task']['choices'] ?? $item['task']['tokens'] ?? [], 'id'), 'status' => 'pending']);
                $presentations[$bundle['presentation_ids'][$index]] = $presentation->id;
            }
            $mapping = GuestEventMapping::create(['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $actor->id, 'entity_type' => 'run', 'guest_entity_id' => $bundle['guest_run_id'],
                'server_run_id' => $run->id, 'receipt' => ['source_hash' => $hash, 'presentation_map' => $presentations, 'guest_revision' => 1, 'server_revision' => 1, 'last_sequence' => $first['sequence'], 'clock_warning' => $clockWarning, 'last_client_time' => $first['client_occurred_at'] ?? null]]);
            $this->ledger->record($episode, $first, ['server_run_id' => $run->id]);
            LearningRunOperation::create(['user_id' => $actor->id, 'run_id' => $run->id, 'client_event_id' => $first['client_event_id'], 'operation' => 'start', 'payload_hash' => CanonicalJson::hash($first),
                'receipt' => ['state' => $this->view->make($actor, $run)], 'received_at' => $now]);
            if ($bundle['kind'] === 'daily' && ! $clockWarning && $run->local_date->format('Y-m-d') === PracticeTime::localDate($now, $bundle['timezone'])) {
                $profile = LearningProfile::where('user_id', $actor->id)->first();
                $date = PracticeTime::localDate($now, $profile?->timezone ?? $bundle['timezone']);
                if ($date === $run->local_date->format('Y-m-d')) {
                    $assignment = DailyAssignment::firstOrCreate(['user_id' => $actor->id, 'local_date' => $date], ['revision' => 1]);
                    if (! $assignment->selected_run_id) {
                        $assignment->update(['selected_run_id' => $run->id, 'revision' => $assignment->revision + 1]);
                    }
                }
            }
        }
        $receipt = $mapping->receipt;
        $accepted = [];
        foreach ($bundle['events'] as $event) {
            if ($this->ledger->previous($episode, $event)) {
                $accepted[] = $event['client_event_id'];

                continue;
            }
            if (($event['sequence'] ?? 0) <= $receipt['last_sequence'] || ($event['expected_revision'] ?? 0) !== $receipt['guest_revision']) {
                AnswerChecker::invalid('Нарушен порядок или ревизия гостевых событий.');
            }
            $run = $this->runs->owned($actor, $mapping->server_run_id, true);
            if ($run->revision !== $receipt['server_revision']) {
                throw new AnswerCheckingException(PracticeErrorCode::RevisionConflict, 'Аккаунтное прохождение уже изменено на сервере. События сохранены для согласования.');
            }
            $action = $event['action'];
            $input = ['client_event_id' => $event['client_event_id'], 'expected_revision' => $run->revision];
            if (isset($event['presentation_id'])) {
                $input['presentation_id'] = $receipt['presentation_map'][$event['presentation_id']] ?? throw new AnswerCheckingException(PracticeErrorCode::ValidationFailed, 'Показ не принадлежит прохождению.');
            }
            $time = $this->time($event['client_occurred_at'] ?? null);
            $previous = $this->time($receipt['last_client_time'] ?? null);
            $receipt['clock_warning'] = ($receipt['clock_warning'] ?? false) || ! $time || $time > PracticeTime::now() || ($previous && $time < $previous);
            $attemptId = null;
            if (in_array($action, ['answer', 'help'], true)) {
                foreach (['event_type', 'answer', 'help_kind', 'reason'] as $key) {
                    if (array_key_exists($key, $event)) {
                        $input[$key] = $event[$key];
                    }
                }
                if ($this->time($event['client_occurred_at'] ?? null)) {
                    $input['client_occurred_at'] = $event['client_occurred_at'];
                }
                $response = $this->runs->event($actor, $run->id, $action, $input);
                $attemptId = $response['attempt']['attempt_id'];
            } elseif (in_array($action, ['introduction', 'advance', 'pause', 'resume', 'finish'], true)) {

                $context = app(GuestImportContext::class);
                $context->sourceAt = $action === 'finish' && ! $receipt['clock_warning'] ? $time : null;
                try {
                    $response = $this->runs->command($actor, $run->id, $action, $input);
                } finally {
                    $context->sourceAt = null;
                }
            } else {
                AnswerChecker::invalid('Неизвестная операция гостя.');
            }
            $this->ledger->record($episode, $event, ['server_run_id' => $run->id, 'server_event_id' => $attemptId]);
            $receipt['guest_revision']++;
            $receipt['server_revision'] = $response['state']['revision'];
            $receipt['last_sequence'] = $event['sequence'];
            $receipt['last_client_time'] = $event['client_occurred_at'] ?? null;
            $accepted[] = $event['client_event_id'];
        }
        $mapping->update(['receipt' => $receipt]);

        return ['guest_run_id' => $bundle['guest_run_id'], 'server_run_id' => $mapping->server_run_id, ...$receipt, 'accepted_event_ids' => $accepted];
    }
}
