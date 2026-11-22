<?php

namespace App\Services\Learning;

use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\LearningRun;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\VocabularyEventEffect;
use App\Models\Learning\VocabularyRunEffect;

class GuestVocabularyProjector
{
    public function project(GuestSyncEpisode $episode): void
    {
        $words = PersonalVocabulary::query()->where('user_id', $episode->owner_user_id)->whereIn('id', $episode->created_word_ids ?? [])->lockForUpdate()->get()->keyBy('lexeme_key');
        $eligible = $words->filter(fn ($word) => $word->native_revision === 0);
        if ($eligible->isEmpty()) {
            return;
        }
        // Existing credits (including those predating the 24-hour rule) are immutable.
        $recorded = VocabularyEventEffect::whereIn('personal_word_id', $eligible->pluck('id'))->get()
            ->keyBy(fn ($effect) => $effect->event_id.'|'.$effect->lexeme_key);
        $lastSuccess = $eligible->map(fn ($word) => VocabularySuccessWindow::latest($word))->all();
        $visible = [];
        $applied = [];
        $success = [];
        $pending = [];
        $runs = [];
        $mappings = $episode->mappings()->where('entity_type', 'event')->with('serverEvent')->get()->sortBy(fn ($row) => $row->receipt['sequence']);
        $apply = function ($mapping) use (&$visible, &$applied, &$success, &$lastSuccess, $recorded, $eligible, $episode): void {
            $event = $mapping->serverEvent;
            if (! $event) {
                return;
            }
            $effects = [];
            foreach ($event->result['assessed_lexemes'] ?? [] as $effect) {
                if (! isset($effects[$effect['lexeme_key']]) || $effect['effect'] === 'error') {
                    $effects[$effect['lexeme_key']] = $effect['effect'];
                }
            }
            foreach ($effects as $key => $kind) {
                if (! isset($visible[$key]) || ! $eligible->has($key) || isset($applied[$event->id][$key])) {
                    continue;
                }
                $word = $eligible[$key];
                $at = $event->received_at;
                $previous = $recorded->get($event->id.'|'.$key);
                $didApply = false;
                if ($kind === 'error') {
                    $visible[$key] = [...$visible[$key], 'needs_review' => true, 'errors' => $visible[$key]['errors'] + 1,
                        'streak' => 0, 'review_step' => 0, 'next_review_at' => $at, 'last_reviewed_at' => $at];
                } elseif ($kind === 'success' && ! isset($success[$event->run_id][$key])
                    && ($previous ? ($previous->effect['success_applied'] ?? false) : VocabularySuccessWindow::allows($lastSuccess[$key] ?? null, $at))) {
                    $success[$event->run_id][$key] = true;
                    $streak = min(3, $visible[$key]['streak'] + 1);
                    $step = min(5, $visible[$key]['review_step'] + 1);
                    $visible[$key] = [...$visible[$key], 'streak' => $streak, 'review_step' => $step,
                        'needs_review' => $streak >= 3 ? false : $visible[$key]['needs_review'],
                        'next_review_at' => $at->addDays(VocabularyProgressService::INTERVAL_DAYS[$step - 1]), 'last_reviewed_at' => $at];
                    VocabularyRunEffect::firstOrCreate(['user_id' => $episode->owner_user_id, 'lexeme_key' => $key, 'run_id' => $event->run_id],
                        ['success_applied' => true, 'applied_at' => $at]);
                    if (! isset($lastSuccess[$key]) || $at->greaterThan($lastSuccess[$key])) {
                        $lastSuccess[$key] = $at;
                    }
                    $didApply = true;
                }
                VocabularyEventEffect::firstOrCreate(['event_id' => $event->id, 'lexeme_key' => $key], [
                    'user_id' => $episode->owner_user_id, 'personal_word_id' => $word->id, 'effect' => ['kind' => $kind, 'success_applied' => $didApply], 'applied_at' => $at]);
                $applied[$event->id][$key] = true;
            }
        };
        foreach ($mappings as $mapping) {
            $action = $mapping->receipt['action'];
            if ($mapping->server_run_id) {
                $runs[$mapping->server_run_id] ??= LearningRun::findOrFail($mapping->server_run_id);
            }
            if ($mapping->server_event_id) {
                $pending[$mapping->server_run_id][] = $mapping;
                $apply($mapping);
            } elseif ($action === 'finish') {
                $run = $runs[$mapping->server_run_id];
                if ($run->session_id !== null) {
                    foreach ($run->result['vocabulary_keys'] ?? array_column($run->snapshot['vocabulary'] ?? [], 'lexeme_key') as $key) {
                        if (! isset($visible[$key]) && $eligible->has($key)) {
                            $visible[$key] = ['needs_review' => false, 'is_difficult' => false, 'streak' => 0, 'errors' => 0,
                                'review_step' => 0, 'last_reviewed_at' => null, 'next_review_at' => $eligible[$key]->first_added_at->addDay()];
                        }
                    }
                }
                foreach ($pending[$mapping->server_run_id] ?? [] as $event) {
                    $apply($event);
                }
            } elseif ($action === 'vocabulary_mark') {
                $key = $mapping->receipt['lexeme_key'];
                if (isset($visible[$key])) {
                    foreach (['needs_review', 'is_difficult'] as $field) {
                        if (array_key_exists($field, $mapping->receipt)) {
                            $visible[$key][$field] = (bool) $mapping->receipt[$field];
                        }
                    }
                }
            }
        }
        foreach ($visible as $key => $values) {
            $eligible[$key]->fill($values)->save();
        }
    }
}
