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
        $query = PersonalVocabulary::query()->where('user_id', $actor->id)
            ->with('sources.sessionVersion:id,session_id,version', 'sources.sessionVersion.session:id,external_id');
        if (($filter['filter'] ?? '') === 'errors') {
            $query->where('needs_review', true);
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
            ->orderBy('external_id')->pluck('external_id');

        return ['words' => $page->getCollection()->map(fn ($word) => $this->data($word))->all(), 'source_sessions' => $sources,
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    public function words(User $actor)
    {
        return PersonalVocabulary::query()->where('user_id', $actor->id)->with('sources.sessionVersion:id,session_id,version', 'sources.sessionVersion.session:id,external_id')->get();
    }

    public function source(PersonalVocabulary $word): array
    {
        $sources = $word->sources->sort(function ($a, $b) {
            return ($b->first_added_at <=> $a->first_added_at)
                ?: strcmp($a->sessionVersion->session->external_id, $b->sessionVersion->session->external_id)
                ?: ($a->sessionVersion->version <=> $b->sessionVersion->version);
        });
        foreach ($sources as $source) {
            if ($this->items($source->snapshot)) {
                return $source->snapshot;
            }
        }

        return $sources->isEmpty() ? $word->snapshot : ($sources->first()?->snapshot ?? $word->snapshot);
    }

    public function items(array $source): array
    {
        return array_values(array_filter($source['review_items'] ?? [], fn ($item) => in_array($item['type'] ?? '', ['single_choice', 'short_answer'], true)));
    }

    public function data(PersonalVocabulary $word): array
    {
        $snapshot = $this->source($word);
        unset($snapshot['review_items']);

        return ['id' => $word->id, 'lexeme_key' => $word->lexeme_key, 'snapshot' => $snapshot,
            'needs_review' => $word->needs_review, 'streak' => $word->streak, 'errors' => $word->errors, 'review_step' => $word->review_step,
            'next_review_at' => $word->next_review_at->toIso8601String(), 'self_check' => ! $this->items($this->source($word)),
            'sources' => $word->sources->map(function ($source) {
                $snapshot = $source->snapshot;
                unset($snapshot['review_items']);

                return ['session_id' => $source->sessionVersion->session->external_id, 'version' => $source->sessionVersion->version,
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

    public function snapshot(User $actor, array $input, string $level): array
    {

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
        foreach ($gradable->take(10) as $word) {
            $source = $this->source($word);
            $options = $this->items($source);
            $item = $options[array_sum(unpack('C*', $seed.$word->lexeme_key)) % count($options)];
            $source['id'] = 'word-'.$word->id;
            $source['lexeme_key'] = $word->lexeme_key;
            $items[] = PresentationSnapshot::forReview($item, $word->lexeme_key);
            $vocabulary[] = $source;
        }

        return [['id' => (string) \Illuminate\Support\Str::uuid(), 'title' => $input['kind'] === 'review' ? 'Повторение выражений' : 'Тест личного словаря',
            'description' => 'Набор выражений закреплён при старте.', 'level' => $level, 'duration_minutes' => 3, 'seed' => $seed,
            'items' => $items, 'vocabulary' => $vocabulary, 'introductions' => [],
            'word_ids' => $gradable->take(10)->pluck('id')->all(), 'remaining_word_ids' => $gradable->skip(10)->pluck('id')->all(), 'self_check_words' => $selfCheck], $items];
    }
}
