<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningRun;
use App\Models\User\User;

final class AccountRunView
{
    public function __construct(private readonly RunAnswerService $answers) {}

    public function make(User $actor, LearningRun $run): array
    {
        $snapshot = $run->snapshot;
        $presentations = $run->presentations()->with(['firstAttempt', 'lastAttempt'])->orderBy('position')->get();
        $presentation = $presentations->firstWhere('position', $run->cursor_position);
        $task = $presentation?->snapshot['task'] ?? null;
        $help = $presentation ? $presentation->attempts()->where('event_type', 'help')->get(['payload'])->pluck('payload.help_kind')->unique()->values()->all() : [];
        $first = $presentation?->firstAttempt?->result;
        $last = $presentation?->lastAttempt?->result;
        if ($first) {
            unset($first['run_revision']);
        }
        if ($last) {
            unset($last['run_revision']);
        }
        if ($task) {
            unset($task['correct_choice_id'], $task['correct_pairs'], $task['accepted_answers'], $task['accepted_orders'], $task['completed_phrases']);
            $task['has_hint'] = ! empty($task['hint']);
            if (! in_array('hint', $help, true) && ! $first) {
                unset($task['hint']);
            }
        }
        if ($task && $presentation?->snapshot['review_lexeme_key']) {
            $task['vocabulary_ids'] = array_column(array_values(array_filter($snapshot['vocabulary'] ?? [], fn ($word) => ($word['lexeme_key'] ?? null) === $presentation->snapshot['review_lexeme_key'])), 'id');
        }
        $vocabulary = array_map(function ($word) {
            unset($word['review_items']);

            return $word;
        }, $snapshot['vocabulary'] ?? []);

        return [
            'has_next_test_block' => ! empty($snapshot['remaining_word_ids']), 'self_check_words' => $snapshot['self_check_words'] ?? [], 'import_meta' => $run->import_meta, 'owner_user_id' => $actor->id, 'run_id' => $run->id, 'mode' => 'account', 'kind' => $run->kind,
            'session_version_id' => $run->session_version_id, 'checking_policy_version' => $run->checking_policy_version,
            'activity_date' => $run->finished_at ? \App\Support\Learning\PracticeTime::localDate($run->finished_at, $run->timezone) : null,
            'revision' => $run->revision, 'status' => $run->status->value, 'started' => $run->status->value !== 'paused', 'resume' => $run->status->value === 'paused',
            'finished' => $run->status->value === 'completed', 'timezone' => $run->timezone, 'local_date' => $run->local_date->format('Y-m-d'),
            'started_at' => $run->started_at->toIso8601String(), 'finished_at' => $run->finished_at?->toIso8601String(),
            'session' => ['id' => $snapshot['id'] ?? $run->id, 'title' => $snapshot['title'] ?? 'Повторение выражений',
                'description' => $snapshot['description'] ?? '', 'level' => $snapshot['level'] ?? '', 'duration_minutes' => $snapshot['duration_minutes'] ?? 3,
                'pronunciation_notes' => $snapshot['pronunciation_notes'] ?? []],
            'task' => $task, 'presentation' => $presentation ? ['presentation_id' => $presentation->id, 'status' => $presentation->status,
                'help_used' => $presentation->help_used, 'answer' => $presentation->lastAttempt?->payload['answer'] ?? $presentation->firstAttempt?->payload['answer'] ?? null, 'first_result' => $first, 'last_result' => $last] : null,
            'position' => $run->cursor_position, 'total' => $presentations->count(),
            'introductions' => array_values(array_filter($snapshot['introductions'] ?? [], fn ($intro) => $intro['before_task_id'] === ($task['id'] ?? null))),
            'introduction_seen' => in_array($presentation?->id, $run->introductions_seen ?? [], true),
            'assistance' => $help, 'vocabulary' => $vocabulary,
            'summary' => $run->result ?? $this->answers->result($actor, $run->id),
            'audioUrls' => array_combine($ids = $this->assets($snapshot), array_map(fn ($id) => route('daily.api.media', ['run' => $run->id, 'media' => $id], false), $ids)) ?: [],
        ];
    }

    public function assets(array $snapshot): array
    {
        $assets = [];
        $walk = function (array $value) use (&$walk, &$assets): void {
            if (is_string($value['audio']['asset_id'] ?? null)) {
                $assets[$value['audio']['asset_id']] = true;
            }
            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($snapshot);

        return array_keys($assets);
    }
}
