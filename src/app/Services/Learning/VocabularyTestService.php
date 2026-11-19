<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Support\Learning\PracticeTime;

class VocabularyTestService
{
    private function query(User $actor, array $filter = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = PersonalVocabulary::query()->where('user_id', $actor->id)->with([
            'sources.sessionVersion' => fn ($version) => $version->select('id', 'session_id', 'version', 'payload->title as source_title'),
            'sources.sessionVersion.session:id,external_id',
        ]);
        if (($filter['filter'] ?? '') === 'errors') {
            $query->where('needs_review', true);
        }
        if (($filter['filter'] ?? '') === 'difficult') {
            $query->where('is_difficult', true);
        }
        if (($filter['filter'] ?? '') === 'due') {
            $query->where('next_review_at', '<=', PracticeTime::now());
        }
        if (! empty($filter['source_session'])) {
            $query->whereHas('sources.sessionVersion.session', fn ($source) => $source->where('external_id', $filter['source_session']));
        }
        if (! empty($filter['q'])) {
            $text = mb_strtolower($filter['q']);
            $ids = [];
            $metadata = (clone $query)->withoutEagerLoads()->select('id', 'snapshot->fr as search_fr', 'snapshot->ru as search_ru')
                ->with(['sources' => fn ($source) => $source->select('personal_word_id', 'snapshot->fr as search_fr', 'snapshot->ru as search_ru')]);
            foreach ($metadata->lazyById(250) as $word) {
                $matches = function ($item) use ($text): bool {
                    $translations = json_decode($item->search_ru ?? '[]', true);
                    $translations = is_array($translations) ? implode(' ', $translations) : ($item->search_ru ?? '');

                    return str_contains(mb_strtolower(($item->search_fr ?? '').' '.$translations), $text);
                };
                if ($matches($word) || $word->sources->contains($matches)) {
                    $ids[] = $word->id;
                }
            }
            $query->whereIn('id', $ids);
        }

        return $query;
    }

    public function listing(User $actor, array $filter): array
    {
        $query = $this->query($actor, $filter);
        $page = $query->orderByDesc('needs_review')->orderBy('next_review_at')->orderBy('id')
            ->paginate(config('daily_practice.operations.vocabulary_page_size'));
        $sources = \App\Models\Learning\LearningSession::whereHas('versions.vocabularySources', fn ($source) => $source->where('user_id', $actor->id))
            ->with(['versions' => fn ($versions) => $versions
                ->whereHas('vocabularySources', fn ($source) => $source->where('user_id', $actor->id))
                ->select('id', 'session_id', 'version', 'payload->title as source_title')->orderByDesc('version')])
            ->orderBy('external_id')->get(['id', 'external_id']);
        $options = $sources->map(fn ($session) => [
            'id' => $session->external_id,
            'title' => $session->versions->first()?->source_title ?? 'Изученное занятие',
        ])->sortBy('title')->values();

        return ['words' => $page->getCollection()->map(fn ($word) => $this->data($word))->all(),
            'source_sessions' => $sources->pluck('external_id'), 'source_session_options' => $options,
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    public function words(User $actor)
    {
        return $this->query($actor)->get();
    }

    public function source(PersonalVocabulary $word): array
    {
        $sources = $word->sources->sort(function ($a, $b) {
            return ($b->first_added_at <=> $a->first_added_at)
                ?: strcmp($a->sessionVersion?->session?->external_id ?? '', $b->sessionVersion?->session?->external_id ?? '')
                ?: (($a->sessionVersion?->version ?? 0) <=> ($b->sessionVersion?->version ?? 0));
        });
        foreach ($sources as $source) {
            if ($this->items($source->snapshot)) {
                return $source->snapshot;
            }
        }

        return $sources->isEmpty() || $this->items($word->snapshot) ? $word->snapshot : ($sources->first()?->snapshot ?? $word->snapshot);
    }

    public function items(array $source): array
    {
        return ShortReviewSelection::items($source);
    }

    public function data(PersonalVocabulary $word): array
    {
        $snapshot = $this->source($word);
        unset($snapshot['review_items']);

        return ['id' => $word->id, 'lexeme_key' => $word->lexeme_key, 'snapshot' => $snapshot,
            'is_difficult' => (bool) $word->is_difficult,
            'needs_review' => $word->needs_review, 'streak' => $word->streak, 'errors' => $word->errors, 'review_step' => $word->review_step,
            'next_review_at' => $word->next_review_at->toIso8601String(), 'self_check' => ! $this->items($this->source($word)),
            'sources' => $word->sources->map(function ($source) {
                $snapshot = $source->snapshot;
                unset($snapshot['review_items']);

                return ['session_id' => $source->sessionVersion->session->external_id, 'version' => $source->sessionVersion->version,
                    'session_title' => $source->sessionVersion->source_title ?? $source->sessionVersion->payload['title'] ?? null,
                    'session_version_id' => $source->session_version_id, 'first_added_at' => $source->first_added_at->toIso8601String(), 'snapshot' => $snapshot];
            })->values()->all()];
    }

    public function filtered(User $actor, array $filter)
    {
        $words = $this->query($actor, $filter)->orderByDesc('needs_review')->orderBy('next_review_at')->orderBy('id')->limit(5001)->get();
        if ($words->count() > 5000) {
            AnswerChecker::invalid('Для одного набора допустимо не более 5000 выражений. Уточните фильтр или выберите занятие-источник.');
        }

        return $words;
    }

    public function selection($words, int $limit = 10, bool $errorsOnly = false)
    {
        $sorted = $words->sortBy([['needs_review', 'desc'], ['next_review_at', 'asc'], ['id', 'asc']])->values();
        $errors = $sorted->where('needs_review', true)->values();
        $other = $sorted->where('needs_review', false)->values();
        if (! $errorsOnly && $sorted->count() > 2 && $sorted->count() >= $limit) {
            $errorLimit = max(intdiv($limit, 2), $limit - $other->count());

            return $errors->take($errorLimit)->concat($other->take($limit - min($errors->count(), $errorLimit)))->values();
        }

        return $sorted->take($limit);
    }

    private function shortContext(User $actor): array
    {
        $profile = app(AccountRunService::class)->profile($actor);
        $runs = \App\Models\Learning\LearningRun::where('user_id', $actor->id)->where('kind', 'review')->orderByDesc('started_at')->orderByDesc('id')->get();
        $previousKeys = []; $previousKinds = []; $done = false; $finished = false;
        foreach ($runs as $index => $run) {
            $snapshot = $run->snapshot;
            $items = $snapshot['items'] ?? [];
            foreach ($items as $item) {
                $key = $item['review_lexeme_key'] ?? null;
                if (! $key) { continue; }
                if ($index === 0) { $previousKeys[] = $key; }
                if (! isset($previousKinds[$key])) {
                    $source = collect($snapshot['vocabulary'] ?? [])->firstWhere('lexeme_key', $key) ?? [];
                    $previousKinds[$key] = ShortReviewSelection::category($item['task'], $source);
                }
            }
            $result = $run->result ?? [];
            if ($run->status->value === 'completed' && count($items) >= 1 && count($items) <= 3 && $run->finished_at
                && PracticeTime::localDate($run->finished_at, $profile['timezone']) === PracticeTime::localDate(PracticeTime::now(), $profile['timezone'])
                && ($result['all_steps_processed'] ?? false)) {
                $finished = true;
                $done = $done || ($result['independent_correct_steps'] ?? 0) === count($items);
            }
        }

        return [$profile, $previousKeys, $previousKinds, $done, $finished];
    }

    public function shortPlan(User $actor, string $mode = 'manual', array $filter = []): array
    {
        [$profile, $previousKeys, $previousKinds, $done, $finished] = $this->shortContext($actor);
        $active = \App\Models\Learning\LearningRun::where('user_id', $actor->id)->where('status', 'in_progress')->orderByDesc('started_at')->first();
        $rows = []; $sources = []; $selfCheck = 0;
        $query = $this->query($actor, $filter);
        if (isset($filter['word_ids'])) { $query->whereIn('id', $filter['word_ids']); }
        $words = $query->get();
        if (isset($filter['word_ids']) && $words->count() !== count(array_unique($filter['word_ids']))) {
            throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Выбранные выражения недоступны.');
        }
        foreach ($words as $word) {
            $source = $this->source($word);
            if (! $this->items($source)) { $selfCheck++; continue; }
            $due = $word->next_review_at->lessThanOrEqualTo(PracticeTime::now());
            if ($mode === 'suggested' && ! $word->needs_review && ! $due) { continue; }
            $session = $word->sources->sortByDesc('first_added_at')->first()?->sessionVersion?->session?->external_id ?? $word->lexeme_key;
            $sources[$word->id] = $source;
            $rows[] = ['key' => $word->lexeme_key, 'id' => $word->id, 'tier' => $word->needs_review ? 0 : ($due ? 1 : 2),
                'due' => $word->next_review_at->getTimestamp(), 'topic' => preg_replace('/-L[0-9]+$/', '', $session),
                'previous_kind' => $previousKinds[$word->lexeme_key] ?? null];
        }
        $blocked = $active !== null || ($mode === 'suggested' && (! $profile['review_suggestions']['can_suggest'] || $finished));
        $selected = $blocked ? [] : ShortReviewSelection::select($rows, $mode === 'manual' ? $previousKeys : []);

        $estimate = 0;
        foreach ($selected as $index => $row) {
            $estimate += ShortReviewSelection::estimateSeconds($sources[$row['id']], $index, $row['previous_kind']);
        }

        return ['mode' => $mode, 'total' => count($selected), 'estimated_seconds' => $estimate, 'word_ids' => array_column($selected, 'id'), 'selection' => $selected,
            'self_check_count' => $selfCheck, 'active_run_id' => $active?->id, 'completed_today' => $done, 'finished_today' => $finished,
            'includes_not_due' => count(array_filter($selected, fn ($row) => $row['tier'] === 2)) > 0];
    }

    private function previousTestItems(User $actor, array $keys): array
    {
        $previous = [];
        $runs = \App\Models\Learning\LearningRun::where('user_id', $actor->id)
            ->whereIn('kind', ['review', 'vocabulary_test'])->orderByDesc('started_at')->orderByDesc('id')->lazy(100);
        foreach ($runs as $run) {
            foreach ($run->snapshot['items'] ?? [] as $item) {
                $key = $item['review_lexeme_key'] ?? null;
                if (in_array($key, $keys, true) && ! isset($previous[$key])) {
                    $previous[$key] = $item['task'];
                }
            }
            if (count($previous) === count($keys)) { break; }
        }

        return $previous;
    }

    public function snapshot(User $actor, array $input, string $level): array
    {

        $short = null;
        if ($input['kind'] === 'review') {
            if (isset($input['continue_run_id']) || (($input['review_mode'] ?? 'manual') === 'suggested' && (isset($input['word_ids']) || isset($input['source_session']) || isset($input['filter'])))) {
                AnswerChecker::invalid('Для предложения короткого повторения набор выбирается из изученного словаря.');
            }
            $short = $this->shortPlan($actor, $input['review_mode'] ?? 'manual', $input);
            if ($short['active_run_id']) { throw new AnswerCheckingException(PracticeErrorCode::ValidationFailed, 'Сначала завершите или явно отложите активное занятие.'); }
            if (! $short['total']) { throw new AnswerCheckingException(PracticeErrorCode::Unavailable, $short['self_check_count'] ? 'Доступны примеры и самопроверка в словаре; проверяемых вопросов сейчас нет.' : 'Сейчас нет подходящего короткого повторения.'); }
            $input['word_ids'] = $short['word_ids'];
        }
        if (isset($input['continue_run_id'])) {
            $old = app(AccountRunService::class)->owned($actor, $input['continue_run_id']);
            if ($old->status->value !== 'completed') {
                AnswerChecker::invalid('Сначала завершите предыдущий блок.');
            }
            $input['word_ids'] = $old->snapshot['remaining_word_ids'] ?? [];
            if (! $input['word_ids']) {
                AnswerChecker::invalid('В этом наборе больше нет блоков.');
            }
        }
        if (isset($input['word_ids'])) {
            $ids = array_unique($input['word_ids']);
            $words = $this->query($actor)->whereIn('id', $ids)->get()->sortBy(fn ($word) => array_search($word->id, $ids))->values();
            if ($words->count() !== count($ids)) {
                throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Выбранные выражения недоступны.');
            }
        } else {
            $query = $this->query($actor, $input);
            if (($input['filter'] ?? '') !== 'errors' && $input['kind'] === 'review') {
                $query->where(fn ($due) => $due->where('needs_review', true)->orWhere('next_review_at', '<=', PracticeTime::now()));
            }
            $query->orderBy('next_review_at')->orderBy('id');
            $words = (clone $query)->where('needs_review', true)->limit(10)->get()
                ->concat((clone $query)->where('needs_review', false)->limit(10)->get());
            $words = $this->selection($words, $input['kind'] === 'review' ? 3 : 10, ($input['filter'] ?? '') === 'errors');
        }
        if ($words->isEmpty()) {
            throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Сначала изучите выражения в занятии.');
        }
        $selfCheck = $words->filter(fn ($word) => ! $this->items($this->source($word)))->map(fn ($word) => $this->data($word))->values()->all();
        $gradable = $words->filter(fn ($word) => $this->items($this->source($word)))->values();
        if ($gradable->isEmpty()) {
            throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Для этих слов доступна самопроверка в словаре без автоматического зачёта.');
        }
        $seed = (string) \Illuminate\Support\Str::uuid();
        $items = [];
        $vocabulary = [];
        $previous = $short ? [] : $this->previousTestItems($actor, $gradable->take(10)->pluck('lexeme_key')->all());
        foreach ($gradable->take(10) as $index => $word) {
            $source = $this->source($word);
            $previousItem = $previous[$word->lexeme_key] ?? null;
            $previousKind = collect($short['selection'] ?? [])->firstWhere('key', $word->lexeme_key)['previous_kind'] ?? null;
            if ($previousItem) { $previousKind = ShortReviewSelection::category($previousItem, $source); }
            $item = ShortReviewSelection::option($source, $seed, $word->lexeme_key, $index, $previousKind, $previousItem['id'] ?? null);
            $source['id'] = 'word-'.$word->id;
            $source['lexeme_key'] = $word->lexeme_key;
            $items[] = PresentationSnapshot::forReview($item, $word->lexeme_key);
            $vocabulary[] = $source;
        }

        return [['id' => (string) \Illuminate\Support\Str::uuid(), 'title' => $input['kind'] === 'review' ? 'Повторение выражений' : 'Тест личного словаря',
            'description' => $short && $short['includes_not_due'] ? 'Повторение изученного: срок части слов ещё не наступил.' : 'Набор выражений закреплён при старте.', 'review_mode' => $short['mode'] ?? null, 'level' => $level, 'duration_minutes' => $short ? (int) ceil($short['estimated_seconds'] / 60) : 3, 'seed' => $seed,
            'items' => $items, 'vocabulary' => $vocabulary, 'introductions' => [],
            'word_ids' => $gradable->take(10)->pluck('id')->all(), 'remaining_word_ids' => $gradable->skip(10)->pluck('id')->all(), 'self_check_words' => $selfCheck], $items];
    }
}
