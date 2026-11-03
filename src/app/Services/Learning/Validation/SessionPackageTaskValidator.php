<?php

namespace App\Services\Learning\Validation;

use App\DTOs\Learning\PackageValidationReport;

final class SessionPackageTaskValidator
{
    public function validate(array $package, PackageValidationReport $report): void
    {
        foreach (['levels' => 'code', 'units' => 'id', 'sessions' => 'id', 'curricula' => 'id'] as $collection => $key) {
            $this->unique($package[$collection] ?? [], $key, '/'.$collection, $report);
        }
        foreach ($package['sessions'] ?? [] as $index => $session) {
            if (! is_array($session)) {
                continue;
            }
            $base = '/sessions/'.$index;
            foreach (['tasks', 'vocabulary', 'objectives', 'introductions'] as $field) {
                $this->unique($session[$field] ?? [], 'id', $base.'/'.$field, $report);
            }
            $vocabulary = $this->ids($session['vocabulary'] ?? []);
            $objectives = $this->ids($session['objectives'] ?? []);
            $tasks = $this->ids($session['tasks'] ?? []);
            foreach ($session['introductions'] ?? [] as $ii => $introduction) {
                if (! is_array($introduction)) {
                    continue;
                }
                $at = $base.'/introductions/'.$ii;
                $this->references([$introduction['before_task_id'] ?? null], $tasks, $at, 'before_task_id', $report, false);
                $this->references($introduction['vocabulary_ids'] ?? [], $vocabulary, $at, 'vocabulary_ids', $report);
            }
            if (($session['level'] ?? null) === 'PRE_A1' && empty($session['introductions'])) {
                $report->warning('introduction_required', $base.'/introductions', 'Перед новым материалом PRE_A1 необходимы вводные карточки.');
            }
            foreach ($session['tasks'] ?? [] as $ti => $task) {
                if (! is_array($task)) {
                    continue;
                }
                $at = $base.'/tasks/'.$ti;
                $this->references($task['objective_ids'] ?? [], $objectives, $at, 'objective_ids', $report);
                $this->references($task['vocabulary_ids'] ?? [], $vocabulary, $at, 'vocabulary_ids', $report);
                $this->references($task['assessed_vocabulary_ids'] ?? [], $task['vocabulary_ids'] ?? [], $at, 'assessed_vocabulary_ids', $report, true, 'invalid_assessed_vocabulary');
                if (is_string($task['skill'] ?? null) && ! in_array($task['skill'], (array) ($session['skills'] ?? []), true)) {
                    $report->error('invalid_skill', $at.'/skill', 'Навык шага отсутствует в skills сессии.');
                }
                $this->task($task, $at, $report);
            }
            foreach ($session['vocabulary'] ?? [] as $vi => $word) {
                if (! is_array($word)) {
                    continue;
                }
                $at = $base.'/vocabulary/'.$vi;
                $this->unique($word['review_items'] ?? [], 'id', $at.'/review_items', $report);
                if (empty($word['review_items'])) {
                    $report->warning('review_item_required', $at.'/review_items', 'Для публикации слова нужен автоматически проверяемый вопрос повторения.');
                }
                foreach ($word['review_items'] ?? [] as $ri => $review) {
                    if (is_array($review)) {
                        $this->task($review, $at.'/review_items/'.$ri, $report);
                    }
                }
            }
            $report->warning('editorial_review_required', $base, 'Французский, смысл ответов, произношение и соответствие уровню должен проверить редактор.');
            if (empty($session['public_page'])) {
                $report->warning('public_page_required', $base.'/public_page', 'Для публикации требуется полноценная публичная страница.');
            }
        }
    }

    public function unique(mixed $items, string $key, string $path, PackageValidationReport $report): void
    {
        if (! is_array($items)) {
            return;
        }
        $seen = [];
        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_string($item[$key] ?? null)) {
                continue;
            }
            $id = '#'.$item[$key];
            if (isset($seen[$id])) {
                $report->error('duplicate_id', $path.'/'.$index.'/'.$key, 'Идентификатор повторён в одной области уникальности.');
            }
            $seen[$id] = true;
        }
    }

    private function ids(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter(array_column(array_filter($items, 'is_array'), 'id'), 'is_string')) : [];
    }

    private function references(mixed $values, mixed $allowed, string $base, string $field, PackageValidationReport $report, bool $array = true, string $code = 'reference_not_found'): void
    {
        if (! is_array($values) || ! is_array($allowed)) {
            return;
        }
        foreach ($values as $index => $value) {
            if (is_string($value) && ! in_array($value, $allowed, true)) {
                $report->error($code, $base.'/'.$field.($array ? '/'.$index : ''), 'Ссылка не найдена в допустимой локальной области.');
            }
        }
    }

    private function task(array $task, string $path, PackageValidationReport $report): void
    {
        if (isset($task['choices']) && is_array($task['choices'])) {
            $this->unique($task['choices'], 'id', $path.'/choices', $report);
            $this->references([$task['correct_choice_id'] ?? null], $this->ids($task['choices']), $path, 'correct_choice_id', $report, false);
            $seen = [];
            foreach ($task['choices'] as $index => $choice) {
                if (! is_array($choice) || ! is_string($choice['text'] ?? null)) {
                    continue;
                }
                $key = ($choice['text_lang'] ?? '').':'.mb_strtolower(SessionPackagePronunciationValidator::normalize($choice['text']));
                if (isset($seen[$key])) {
                    $report->error('duplicate_choice_text', $path.'/choices/'.$index.'/text', 'Одинаковые варианты ответа не различимы для ученика.');
                }
                $seen[$key] = true;
            }
        }
        if (($task['type'] ?? null) === 'matching_pairs') {
            $this->matching($task, $path, $report);
        }
        if (($task['type'] ?? null) === 'word_order') {
            $this->unique($task['tokens'] ?? [], 'id', $path.'/tokens', $report);
            $tokens = $this->ids($task['tokens'] ?? []);
            $texts = array_column($task['tokens'], 'text', 'id');
            $phrases = array_map($this->phrase(...), array_column($task['completed_phrases'], 'fr'));
            foreach ($task['accepted_orders'] ?? [] as $index => $order) {
                if (! is_array($order)) {
                    continue;
                }
                $actual = $order;
                sort($actual, SORT_STRING);
                $expected = $tokens;
                sort($expected, SORT_STRING);
                if ($actual !== $expected || count($actual) !== count(array_unique($actual, SORT_REGULAR))) {
                    $report->error('invalid_word_order', $path.'/accepted_orders/'.$index, 'Каждый порядок должен содержать все ID токенов ровно один раз.');
                } elseif (! in_array($this->phrase(implode(' ', array_map(fn (string $id): string => $texts[$id], $order))), $phrases, true)) {
                    $report->error('completed_phrase_missing', $path.'/completed_phrases', 'Нет полной фразы для допустимого порядка слов.');
                }
            }
        }
        if (($task['type'] ?? null) === 'fill_blank' && isset($task['text_before'], $task['text_after']) && is_string($task['text_before']) && is_string($task['text_after'])) {
            $answers = $task['accepted_answers'] ?? [];
            if (($task['input_mode'] ?? null) === 'choice' && is_array($task['choices'] ?? null)) {
                $choices = array_column(array_filter($task['choices'], 'is_array'), 'text', 'id');
                $answers = isset($choices[$task['correct_choice_id'] ?? '']) ? [$choices[$task['correct_choice_id']]] : [];
            }
            $phrases = array_map($this->phrase(...), array_filter(array_column(array_filter((array) ($task['completed_phrases'] ?? []), 'is_array'), 'fr'), 'is_string'));
            foreach ((array) $answers as $answer) {
                if (is_string($answer) && ! in_array($this->phrase($task['text_before'].$answer.$task['text_after']), $phrases, true)) {
                    $report->error('completed_phrase_missing', $path.'/completed_phrases', 'Нет полной фразы для правильного заполнения пропуска.');
                }
            }
        }
    }

    private function matching(array $task, string $path, PackageValidationReport $report): void
    {
        foreach (['left_items', 'right_items'] as $field) {
            $this->unique($task[$field] ?? [], 'id', $path.'/'.$field, $report);
        }
        $left = $this->ids($task['left_items'] ?? []);
        $right = $this->ids($task['right_items'] ?? []);
        $usedLeft = [];
        $usedRight = [];
        $assessed = [];
        foreach ($task['correct_pairs'] ?? [] as $index => $pair) {
            if (! is_array($pair)) {
                continue;
            }
            foreach (['left_id' => $left, 'right_id' => $right] as $field => $ids) {
                $this->references([$pair[$field] ?? null], $ids, $path.'/correct_pairs/'.$index, $field, $report, false);
            }
            $usedLeft[] = $pair['left_id'] ?? null;
            $usedRight[] = $pair['right_id'] ?? null;
            $this->references($pair['assessed_vocabulary_ids'] ?? [], $task['vocabulary_ids'] ?? [], $path.'/correct_pairs/'.$index, 'assessed_vocabulary_ids', $report, true, 'invalid_assessed_vocabulary');
            $assessed = [...$assessed, ...array_filter((array) ($pair['assessed_vocabulary_ids'] ?? []), 'is_string')];
        }
        sort($left, SORT_STRING);
        sort($right, SORT_STRING);
        sort($usedLeft, SORT_STRING);
        sort($usedRight, SORT_STRING);
        if ($left !== $usedLeft || $right !== $usedRight) {
            $report->error('invalid_matching_pairs', $path.'/correct_pairs', 'Нужно полное взаимно однозначное сопоставление без повторов.');
        }
        $assessed = array_values(array_unique($assessed));
        $upper = array_filter((array) ($task['assessed_vocabulary_ids'] ?? []), 'is_string');
        sort($assessed, SORT_STRING);
        sort($upper, SORT_STRING);
        if ($assessed !== array_values($upper)) {
            $report->error('invalid_assessed_vocabulary', $path.'/assessed_vocabulary_ids', 'Набор должен совпадать с объединением проверяемых слов всех пар.');
        }
    }

    private function phrase(string $value): string
    {
        return mb_strtolower(trim(SessionPackagePronunciationValidator::normalize($value), ' .!?…'));
    }
}
