<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;

final class PresentationSnapshot
{
    public static function forTask(array $session, string $taskId): array
    {
        foreach ($session['tasks'] as $task) {
            if ($task['id'] === $taskId) {
                return ['task' => $task, 'lexemes' => array_map(fn (array $word): array => ['id' => $word['id'], 'lexeme_key' => $word['lexeme_key']], $session['vocabulary']), 'review_lexeme_key' => null];
            }
        }
        throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Задание отсутствует в снимке сессии.');
    }

    public static function forReview(array $reviewItem, string $lexemeKey): array
    {
        if ($lexemeKey === '' || ! in_array($reviewItem['type'] ?? null, ['single_choice', 'short_answer'], true)) {
            throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Недоступен проверяемый шаблон повторения.');
        }

        return ['task' => $reviewItem, 'lexemes' => [], 'review_lexeme_key' => $lexemeKey];
    }

    public static function bound(array $runSnapshot, int $position, array $snapshot): void
    {
        $expected = null;
        if (isset($runSnapshot['tasks'][$position - 1])) {
            $expected = self::forTask($runSnapshot, $runSnapshot['tasks'][$position - 1]['id']);
        } elseif (isset($runSnapshot['items'][$position - 1])) {
            $expected = $runSnapshot['items'][$position - 1];
        }
        if ($expected === null || \App\Services\Learning\Validation\CanonicalJson::hash($expected) !== \App\Services\Learning\Validation\CanonicalJson::hash($snapshot)) {
            throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Предъявление не соответствует закреплённому снимку run.');
        }
    }
}
