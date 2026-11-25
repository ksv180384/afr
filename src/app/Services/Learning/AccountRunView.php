<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningRun;
use App\Models\User\User;

final class AccountRunView
{
    public function __construct(private readonly RunAnswerService $answers) {}

    public function make(User $actor, LearningRun $run, bool $playback = false): array
    {
        if ($run->status->value === 'cancelled') {
            return ['owner_user_id' => $actor->id, 'run_id' => $run->id, 'mode' => 'account', 'kind' => $run->kind,
                'status' => 'cancelled', 'revision' => $run->revision, 'started' => false, 'resume' => false, 'finished' => true,
                'session' => ['title' => $run->snapshot['title'] ?? 'Повторение выражений'], 'total' => 0, 'position' => 0,
                'presentation' => null, 'task' => null, 'summary' => [], 'vocabulary' => [], 'audioUrls' => []];
        }
        $finished = $run->status->value === 'completed';
        $snapshot = $run->content();
        $presentations = $finished ? collect() : $run->presentations()->with(['firstAttempt', 'lastAttempt'])->orderBy('position')->get();
        $presentation = $presentations->firstWhere('position', $run->cursor_position);
        $step = $presentation?->taskSnapshot($snapshot);
        $task = $step['task'] ?? null;
        $help = $presentation ? $presentation->attempts()->where('event_type', 'help')->get(['payload'])->pluck('payload.help_kind')->unique()->values()->all() : [];
        $first = $presentation?->firstAttempt?->gradingResult($run->checking_policy_version, $presentation->task_id);
        $last = $presentation?->lastAttempt?->gradingResult($run->checking_policy_version, $presentation->task_id);
        $answer = $presentation?->lastAttempt?->payload['answer'] ?? $presentation?->firstAttempt?->payload['answer'] ?? null;
        $corrections = $finished ? null : $run->corrections;
        $mistakePositions = $corrections ? [] : LessonCorrections::positions($run, $presentations);
        if ($corrections) {
            $attempt = LessonCorrections::attempt($run);
            $first = $last = $attempt?->gradingResult($run->checking_policy_version);
            $answer = $attempt?->payload['answer'] ?? null;
        }
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
        if ($task && ($step['review_lexeme_key'] ?? null)) {
            $task['vocabulary_ids'] = array_column(array_values(array_filter($snapshot['vocabulary'] ?? [], fn ($word) => ($word['lexeme_key'] ?? null) === $step['review_lexeme_key'])), 'id');
        }
        $vocabulary = array_map(function ($word) {
            unset($word['review_items']);

            return $word;
        }, $snapshot['vocabulary'] ?? []);

        return [
            'has_next_test_block' => ! empty($snapshot['remaining_word_ids']), 'self_check_words' => $snapshot['self_check_words'] ?? [], 'import_meta' => $run->import_meta, 'owner_user_id' => $actor->id, 'run_id' => $run->id, 'mode' => 'account', 'kind' => $run->kind,
            'lesson_id' => $run->session_id, 'session_version_id' => $run->session_id, 'checking_policy_version' => $run->checking_policy_version,
            'activity_date' => $run->finished_at ? \App\Support\Learning\PracticeTime::localDate($run->finished_at, $run->timezone) : null,
            'revision' => $run->revision, 'status' => $run->status->value, 'started' => $run->status->value !== 'paused', 'resume' => $run->status->value === 'paused',
            'finished' => $run->status->value === 'completed', 'timezone' => $run->timezone, 'local_date' => $run->local_date->format('Y-m-d'),
            'started_at' => $run->started_at->toIso8601String(), 'finished_at' => $run->finished_at?->toIso8601String(),
            'session' => ['id' => $snapshot['id'] ?? $run->id, 'title' => $snapshot['title'] ?? 'Повторение выражений',
                'description' => $snapshot['description'] ?? '', 'level' => $snapshot['level'] ?? '', 'duration_minutes' => $snapshot['duration_minutes'] ?? 3,
                'pronunciation_notes' => $snapshot['pronunciation_notes'] ?? []],
            'task' => $task, 'presentation' => $presentation ? ['presentation_id' => $presentation->id, 'status' => $presentation->status,
                'help_used' => $presentation->help_used, 'answer' => $answer, 'first_result' => $first, 'last_result' => $last] : null,
            'position' => $run->cursor_position, 'total' => $finished ? ($run->result['total_steps'] ?? 0) : $presentations->count(),
            'mistakes_count' => $run->result['mistakes_count'] ?? ($corrections ? ($corrections['initial_total'] ?? count($corrections['positions'])) : count($mistakePositions)),
            'mistake_positions' => $mistakePositions,
            'corrections' => $corrections ? [
                'started' => $corrections['started'], 'position' => $corrections['index'] + 1, 'total' => count($corrections['positions']),
                'round' => $corrections['round'] ?? 1, 'fixed_last_round' => $corrections['fixed_last_round'] ?? 0,
                'can_finish' => LessonCorrections::canFinish($corrections, $last['correct'] ?? null),
                'positions' => $corrections['positions'], 'remaining_positions' => $corrections['remaining'] ?? [],
            ] : null,
            'introductions' => array_values(array_filter($snapshot['introductions'] ?? [], fn ($intro) => $intro['before_task_id'] === ($task['id'] ?? null))),
            'introduction_seen' => in_array($presentation?->id, $run->introductions_seen ?? [], true),
            'assistance' => $help, 'vocabulary' => $vocabulary,
            'summary' => $finished ? $run->completedResult() : $this->answers->result($actor, $run->id),
            'audioUrls' => array_combine($ids = $this->assets($snapshot), array_map(fn ($id) => route('daily.api.media', ['run' => $run->id, 'media' => $id], false), $ids)) ?: [],
            ...($playback && $run->status->value !== 'completed' && ($snapshot['kind'] ?? null) !== 'checkpoint' ? ['playback' => [
                'steps' => $presentations->map(function ($step) use ($snapshot) {
                    $item = $step->taskSnapshot($snapshot);

                    return ['presentation_id' => $step->id, 'task' => $item['task'], 'review_lexeme_key' => $item['review_lexeme_key']];
                })->all(),
                'introductions' => $snapshot['introductions'] ?? [],
            ]] : []),
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
