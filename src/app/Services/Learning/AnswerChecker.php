<?php

namespace App\Services\Learning;

use App\DTOs\Learning\AnswerCheckResult;
use App\Enums\Learning\PracticeErrorCode;
use DomainException;

final class AnswerChecker
{
    public static function policy(int $version): CheckingPolicy
    {
        try {
            return CheckingPolicy::forVersion($version);
        } catch (DomainException) {
            throw new AnswerCheckingException(PracticeErrorCode::UnsupportedCheckingPolicy, 'Версия правил проверки недоступна.');
        }
    }

    public static function invalid(string $message = 'Ответ неполный или содержит недопустимые поля.'): never
    {
        throw new AnswerCheckingException(PracticeErrorCode::ValidationFailed, $message);
    }

    public static function keys(mixed $value, array $expected): void
    {
        if (! is_array($value)) {
            self::invalid();
        }
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            self::invalid();
        }
    }

    public function check(array $task, mixed $answer, int $version): AnswerCheckResult
    {
        self::policy($version);
        $solution = $this->solution($task);
        $type = $task['type'];
        if ($type === 'single_choice' || ($type === 'fill_blank' && $task['input_mode'] === 'choice')) {
            self::keys($answer, ['choice_id']);
            if (! is_string($answer['choice_id']) || ! in_array($answer['choice_id'], array_column($task['choices'], 'id'), true)) {
                self::invalid('Выберите вариант из этого предъявления.');
            }

            return new AnswerCheckResult($answer['choice_id'] === $task['correct_choice_id'], [], $solution);
        }
        if ($type === 'short_answer' || ($type === 'fill_blank' && $task['input_mode'] === 'text')) {
            self::keys($answer, ['value']);
            $text = $answer['value'];
            if (! is_string($text) || ! mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 20000 || AnswerNormalizer::normalize($text) === '') {
                self::invalid('Введите непустой короткий ответ.');
            }
            $normalized = AnswerNormalizer::normalize($text);
            $correct = in_array($normalized, array_map(AnswerNormalizer::normalize(...), $task['accepted_answers']), true);

            return new AnswerCheckResult($correct, [], $solution);
        }
        if ($type === 'word_order') {
            self::keys($answer, ['token_ids']);
            $ids = $answer['token_ids'];
            $tokens = array_column($task['tokens'], 'id');
            $this->permutation($ids, $tokens);
            $text = [];
            foreach ($task['tokens'] as $token) {
                $text['#'.$token['id']] = AnswerNormalizer::surface($token['text']);
            }
            $actual = array_map(fn (string $id): string => $text['#'.$id], $ids);
            $correct = false;
            foreach ($task['accepted_orders'] as $order) {
                $correct = $correct || $actual === array_map(fn (string $id): string => $text['#'.$id], $order);
            }

            return new AnswerCheckResult($correct, [], $solution);
        }
        self::keys($answer, ['pairs']);
        if (! is_array($answer['pairs']) || ! array_is_list($answer['pairs'])) {
            self::invalid();
        }
        $left = [];
        $right = [];
        $submitted = [];
        foreach ($answer['pairs'] as $pair) {
            self::keys($pair, ['left_id', 'right_id']);
            if (! is_string($pair['left_id']) || ! is_string($pair['right_id'])) {
                self::invalid();
            }
            $left[] = $pair['left_id'];
            $right[] = $pair['right_id'];
            $submitted['#'.$pair['left_id']] = $pair['right_id'];
        }
        $this->permutation($left, array_column($task['left_items'], 'id'));
        $this->permutation($right, array_column($task['right_items'], 'id'));
        $pairs = [];
        foreach ($task['correct_pairs'] as $pair) {
            $pairs[] = ['left_id' => $pair['left_id'], 'right_id' => $submitted['#'.$pair['left_id']], 'correct' => $submitted['#'.$pair['left_id']] === $pair['right_id'],
                'assessed_vocabulary_ids' => $pair['assessed_vocabulary_ids']];
        }

        return new AnswerCheckResult(! in_array(false, array_column($pairs, 'correct'), true), $pairs, $solution);
    }

    private function permutation(mixed $actual, array $expected): void
    {
        if (! is_array($actual) || ! array_is_list($actual) || count($actual) !== count($expected)) {
            self::invalid('Используйте все карточки или составьте все пары.');
        }
        foreach ($actual as $id) {
            if (! is_string($id)) {
                self::invalid();
            }
        }
        $sorted = $actual;
        sort($sorted, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($sorted !== $expected) {
            self::invalid('Карточки или пары повторены либо относятся к другому заданию.');
        }
    }

    public function solution(array $task): array
    {
        $type = $task['type'] ?? null;
        $answer = match ($type) {
            'single_choice' => ['choice_id' => $task['correct_choice_id']],
            'fill_blank' => match ($task['input_mode'] ?? null) {
                'choice' => ['choice_id' => $task['correct_choice_id']],
                'text' => ['accepted_answers' => $task['accepted_answers']],
                default => throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Некорректный снимок задания.'),
            },
            'short_answer' => ['accepted_answers' => $task['accepted_answers']],
            'matching_pairs' => ['pairs' => array_map(fn (array $pair): array => ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']], $task['correct_pairs'])],
            'word_order' => ['accepted_orders' => $task['accepted_orders']],
            default => throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Неподдерживаемый тип задания.'),
        };

        return [...$answer, 'completed_phrases' => $task['completed_phrases'] ?? [], 'pronunciation_notes' => $task['pronunciation_notes'] ?? []];
    }
}
