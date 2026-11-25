<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;

final class PresentationSnapshot
{
    public static function atPosition(array $runSnapshot, int $position, string $taskId): array
    {
        $index = $position - 1;
        if (isset($runSnapshot['tasks'][$index])) {
            $snapshot = ['task' => $runSnapshot['tasks'][$index],
                'lexemes' => array_map(fn (array $word): array => ['id' => $word['id'], 'lexeme_key' => $word['lexeme_key']], $runSnapshot['vocabulary']),
                'review_lexeme_key' => null];
        } else {
            $snapshot = $runSnapshot['items'][$index] ?? null;
        }
        if (($snapshot['task']['id'] ?? null) !== $taskId) {
            throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Задание не соответствует позиции в закреплённом прохождении.');
        }

        return $snapshot;
    }

    public static function forReview(array $reviewItem, string $lexemeKey): array
    {
        if ($lexemeKey === '' || ! in_array($reviewItem['type'] ?? null, ['single_choice', 'short_answer'], true)) {
            throw new AnswerCheckingException(PracticeErrorCode::Unavailable, 'Недоступен проверяемый шаблон повторения.');
        }

        return ['task' => $reviewItem, 'lexemes' => [], 'review_lexeme_key' => $lexemeKey];
    }
}
