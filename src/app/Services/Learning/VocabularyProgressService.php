<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PersonalVocabularySource;
use App\Models\Learning\SessionLexeme;
use App\Models\Learning\VocabularyEventEffect;
use Carbon\CarbonImmutable;

class VocabularyProgressService
{
    public const INTERVAL_DAYS = [1, 3, 7, 14, 30];

    public function accepted(LearningRun $run, LearningAttempt $event): void
    {
        $import = app(GuestImportContext::class);
        if ($import->episode !== null) {
            foreach ($event->result['assessed_lexemes'] ?? [] as $entry) {
                $word = PersonalVocabulary::where('user_id', $run->user_id)->where('lexeme_key', $entry['lexeme_key'])->first();
                if ($word && $import->protected($word)) {
                    VocabularyEventEffect::firstOrCreate(['event_id' => $event->id, 'lexeme_key' => $word->lexeme_key], [
                        'user_id' => $run->user_id, 'personal_word_id' => $word->id, 'effect' => ['kind' => 'protected', 'success_applied' => false], 'applied_at' => $event->received_at]);
                }
            }

            return;
        }
        $effects = [];
        foreach ($event->result['assessed_lexemes'] ?? [] as $effect) {
            $key = $effect['lexeme_key'];
            if (! isset($effects[$key]) || $effect['effect'] === 'error') {
                $effects[$key] = $effect['effect'];
            }
        }
        foreach ($effects as $key => $kind) {
            $word = PersonalVocabulary::query()->where('user_id', $run->user_id)->where('lexeme_key', $key)->lockForUpdate()->first();
            if (! $word || VocabularyEventEffect::query()->where('event_id', $event->id)->where('lexeme_key', $key)->exists()) {
                continue;
            }
            $context = app(GuestImportContext::class);
            if ($context->protected($word)) {
                $kind = 'protected';
            }
            $applied = false;
            $at = $event->received_at;
            if ($kind === 'error') {
                $word->fill(['needs_review' => true, 'errors' => $word->errors + 1, 'streak' => 0, 'review_step' => 0, 'next_review_at' => $at, 'last_reviewed_at' => $at])->save();
            } elseif ($kind === 'success' && VocabularySuccessWindow::credit($word, $run, $at)) {
                $step = min(count(self::INTERVAL_DAYS), $word->review_step + 1);
                $streak = min(3, $word->streak + 1);
                $word->fill(['streak' => $streak, 'review_step' => $step, 'needs_review' => $streak >= 3 ? false : $word->needs_review,
                    'last_reviewed_at' => $at, 'next_review_at' => $at->addDays(self::INTERVAL_DAYS[$step - 1])])->save();
                $applied = true;
            }
            if ($context->episode === null && ($kind === 'error' || $applied)) {
                $word->increment('native_revision');
            }
            VocabularyEventEffect::create(['user_id' => $run->user_id, 'event_id' => $event->id, 'personal_word_id' => $word->id,
                'lexeme_key' => $key, 'effect' => ['kind' => $kind, 'success_applied' => $applied], 'applied_at' => $at]);
        }
    }

    public function finishing(LearningRun $run, CarbonImmutable $at): array
    {
        $addedIds = [];
        $existing = 0;
        if ($run->session_id !== null) {
            $entries = SessionLexeme::where('session_id', $run->session_id)->lockForUpdate()->pluck('id', 'lexeme_key');
            $studied = PersonalVocabularySource::where('user_id', $run->user_id)->where('session_id', $run->session_id)
                ->pluck('session_lexeme_id')->flip();
            $newEntries = [];
            foreach ($run->content()['vocabulary'] ?? [] as $expression) {
                $key = $expression['lexeme_key'];
                if (!isset($entries[$key])) {
                    continue;
                }
                $word = PersonalVocabulary::firstOrCreate(['user_id' => $run->user_id, 'lexeme_key' => $key], [
                    'needs_review' => false, 'streak' => 0, 'errors' => 0, 'review_step' => 0,
                    'first_added_at' => $at, 'next_review_at' => $at->addDay(),
                ]);
                if ($word->wasRecentlyCreated) {
                    app(GuestImportContext::class)->created($word);
                    $addedIds[] = $word->id;
                } else {
                    $existing++;
                }
                if (! $studied->has($entries[$key])) {
                    $newEntries[] = $entries[$key];
                }
            }
            if ($newEntries) {
                $run->vocabulary_set_id = VocabularyStudySets::forEntries($run->session_id, $newEntries);
                $run->vocabulary_source_at = app(GuestImportContext::class)->sourceAt;
            }
        }
        // Replays are deduplicated per event/lexeme; existing words have already been updated.
        foreach (app(GuestImportContext::class)->episode === null ? $run->attempts()->orderBy('sequence')->get() : [] as $event) {
            $this->accepted($run, $event);
        }

        return ['words_added' => count($addedIds), 'words_existing' => $existing,
            ...($addedIds ? ['added_word_ids' => $addedIds] : [])];
    }
}
