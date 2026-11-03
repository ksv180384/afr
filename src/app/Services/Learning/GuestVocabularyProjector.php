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
        $words = PersonalVocabulary::query()->where('user_id', $episode->owner_user_id)->whereIn('id', $episode->created_word_ids ?? [])->get()->keyBy('lexeme_key');
        $eligible = $words->filter(fn ($word) => $word->native_revision === 0);
        if ($eligible->isEmpty()) {
            return;
        }
        $visible = [];
        $applied = [];
        $success = [];
        $pending = [];
        $runs = [];
        $mappings = $episode->mappings()->where('entity_type', 'event')->with('serverEvent')->get()->sortBy(fn ($row) => $row->receipt['sequence']);
        $apply = function ($mapping) use (&$visible, &$applied, &$success, $eligible, $episode): void {
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
                $didApply = false;
                if ($kind === 'error') {
                    $visible[$key] = [...$visible[$key], 'needs_review' => true, 'errors' => $visible[$key]['errors'] + 1,
                        'streak' => 0, 'review_step' => 0, 'next_review_at' => $at, 'last_reviewed_at' => $at];
                } elseif ($kind === 'success' && ! isset($success[$event->run_id][$key])) {
                    $success[$event->run_id][$key] = true;
                    $streak = min(3, $visible[$key]['streak'] + 1);
                    $step = min(5, $visible[$key]['review_step'] + 1);
                    $visible[$key] = [...$visible[$key], 'streak' => $streak, 'review_step' => $step,
                        'needs_review' => $streak >= 3 ? false : $visible[$key]['needs_review'],
                        'next_review_at' => $at->addDays(VocabularyProgressService::INTERVAL_DAYS[$step - 1]), 'last_reviewed_at' => $at];
                    VocabularyRunEffect::firstOrCreate(['user_id' => $episode->owner_user_id, 'lexeme_key' => $key, 'run_id' => $event->run_id],
                        ['success_applied' => true, 'applied_at' => $at]);
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
                if ($run->session_version_id !== null) {
                    foreach ($run->snapshot['vocabulary'] ?? [] as $source) {
                        $key = $source['lexeme_key'];
                        if (! isset($visible[$key]) && $eligible->has($key)) {
                            $visible[$key] = ['needs_review' => false, 'streak' => 0, 'errors' => 0,
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
                    $visible[$key]['needs_review'] = $mapping->receipt['needs_review'];
                }
            }
        }
        foreach ($visible as $key => $values) {
            $eligible[$key]->fill($values)->save();
        }
    }
}
