<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PersonalVocabularySource;
use App\Models\Learning\VocabularyEventEffect;
use App\Models\Learning\VocabularyRunEffect;
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
            } elseif ($kind === 'success') {
                $runEffect = VocabularyRunEffect::firstOrCreate(['user_id' => $run->user_id, 'lexeme_key' => $key, 'run_id' => $run->id], ['success_applied' => false]);
                if (! $runEffect->success_applied) {
                    $step = min(count(self::INTERVAL_DAYS), $word->review_step + 1);
                    $streak = min(3, $word->streak + 1);
                    $word->fill(['streak' => $streak, 'review_step' => $step, 'needs_review' => $streak >= 3 ? false : $word->needs_review,
                        'last_reviewed_at' => $at, 'next_review_at' => $at->addDays(self::INTERVAL_DAYS[$step - 1])])->save();
                    $runEffect->update(['success_applied' => true, 'applied_at' => $at]);
                    $applied = true;
                }
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
        $added = 0;
        $existing = 0;
        if ($run->session_version_id !== null) {
            foreach ($run->snapshot['vocabulary'] ?? [] as $snapshot) {
                $word = PersonalVocabulary::firstOrCreate(['user_id' => $run->user_id, 'lexeme_key' => $snapshot['lexeme_key']], [
                    'snapshot' => $snapshot, 'needs_review' => false, 'streak' => 0, 'errors' => 0, 'review_step' => 0,
                    'first_added_at' => $at, 'next_review_at' => $at->addDay(),
                ]);
                if ($word->wasRecentlyCreated) {
                    app(GuestImportContext::class)->created($word);
                }
                $word->wasRecentlyCreated ? $added++ : $existing++;
                PersonalVocabularySource::firstOrCreate(['personal_word_id' => $word->id, 'session_version_id' => $run->session_version_id], [
                    'user_id' => $run->user_id, 'vocabulary_id' => $snapshot['id'], 'snapshot' => $snapshot, 'first_added_at' => app(GuestImportContext::class)->sourceAt ?? $at,
                ]);
            }
        }
        // Replays are deduplicated per event/lexeme; existing words have already been updated.
        foreach (app(GuestImportContext::class)->episode === null ? $run->attempts()->orderBy('sequence')->get() : [] as $event) {
            $this->accepted($run, $event);
        }

        return ['words_added' => $added, 'words_existing' => $existing];
    }
}
