<?php

namespace App\Services\Learning;

final class PresentationEvaluator
{
    public function __construct(private readonly AnswerChecker $checker) {}

    public static function initial(string $presentationId): array
    {
        return ['presentation_id' => $presentationId, 'status' => 'pending', 'help_used' => false, 'first_result' => null, 'last_result' => null];
    }

    public function apply(array $snapshot, array $state, array $event, int $version): array
    {
        AnswerChecker::policy($version);
        $type = $event['event_type'] ?? null;
        $keys = match ($type) {
            'answer' => ['event_type', 'presentation_id', 'answer'],
            'help' => ['event_type', 'presentation_id', 'help_kind'],
            'unknown' => ['event_type', 'presentation_id'],
            'technical_skip' => ['event_type', 'presentation_id', 'reason'],
            default => AnswerChecker::invalid('Неизвестное учебное событие.'),
        };
        AnswerChecker::keys($event, $keys);
        if ($event['presentation_id'] !== $state['presentation_id']) {
            AnswerChecker::invalid('Событие относится к другому предъявлению.');
        }
        $task = $snapshot['task'];
        $first = $state['first_result'] === null;
        if ($type === 'help' && (! in_array($event['help_kind'], ['hint', 'audio_transcript', 'reference'], true) || ($event['help_kind'] === 'audio_transcript' && empty($task['prompt']['audio'])))) {
            AnswerChecker::invalid('Недоступный вид помощи.');
        }
        if ($type === 'technical_skip' && ($event['reason'] !== 'audio_unavailable' || (empty($task['prompt']['audio']) && ($task['skill'] ?? null) !== 'listening'))) {
            AnswerChecker::invalid('Технический пропуск доступен при недоступном аудио.');
        }
        $check = $type === 'answer' ? $this->checker->check($task, $event['answer'], $version) : null;
        $independent = $first && ! $state['help_used'] && $check?->correct === true;
        $grading = $type !== 'help';
        $solution = $grading ? ($check?->solution ?? $this->checker->solution($task)) : null;
        $pairs = $check?->pairs ?? [];
        $assessed = [];
        if ($grading) {
            $map = [];
            foreach ($snapshot['lexemes'] as $word) {
                $map['#'.$word['id']] = $word['lexeme_key'];
            }
            $items = ($task['type'] === 'matching_pairs' && $check !== null) ? $pairs : [['assessed_vocabulary_ids' => $task['assessed_vocabulary_ids'] ?? [], 'correct' => $check?->correct ?? false]];
            foreach ($items as $item) {
                $lexemeKeys = $snapshot['review_lexeme_key'] === null
                    ? array_map(fn (string $id): string => $map['#'.$id] ?? throw new \LogicException('Unknown assessed vocabulary snapshot.'), $item['assessed_vocabulary_ids'])
                    : [$snapshot['review_lexeme_key']];
                foreach ($lexemeKeys as $key) {
                    $effect = $type === 'technical_skip' ? 'none'
                        : (! $first ? ($type === 'answer' && ! $item['correct'] ? 'error' : 'none')
                            : (($type === 'unknown' || ! $item['correct']) ? 'error' : (! $state['help_used'] ? 'success' : 'none')));
                    $entry = ['lexeme_key' => $key, 'correct' => $type === 'technical_skip' ? null : (bool) $item['correct'], 'effect' => $effect];
                    $index = '#'.$key;
                    if (! isset($assessed[$index]) || ($assessed[$index]['correct'] !== false && $entry['correct'] === false)) {
                        $assessed[$index] = $entry;
                    }
                }
            }
        }
        $result = [
            'checking_policy_version' => $version, 'presentation_id' => $state['presentation_id'], 'task_id' => $task['id'],
            'event_type' => $type, 'is_first_answer' => $first && $grading,
            'correct' => $type === 'answer' ? $check->correct : ($type === 'unknown' ? false : null),
            'first_independent_success' => $independent, 'help_used' => $state['help_used'] || ($first && $type === 'help'),
            'score' => $state['first_result']['score'] ?? ($independent ? 1 : 0),
            'technical_skip' => $type === 'technical_skip' && $first,
            'feedback' => $type === 'technical_skip' ? ['message' => 'Звук недоступен. Шаг пропущен без языковой ошибки.', 'pronunciation_notes' => []]
                : ($grading ? ['message' => $task['feedback'][$check?->correct ? 'correct' : 'incorrect'], 'pronunciation_notes' => $task['feedback']['pronunciation_notes'] ?? []] : null),
            'solution' => $solution, 'pairs' => $pairs, 'assessed_lexemes' => array_values($assessed),
            'objective_evidence_eligible' => $independent && ! empty($task['objective_ids']) && isset($task['skill']),
            'objective_ids' => $task['objective_ids'] ?? [], 'skill' => $task['skill'] ?? null,
        ];
        if ($first && $type === 'help') {
            $state['help_used'] = true;
        }
        if ($first && $grading) {
            $state['first_result'] = $result;
            $state['status'] = $type === 'technical_skip' ? 'skipped' : 'answered';
        }
        $state['last_result'] = $result;

        return $state;
    }
}
