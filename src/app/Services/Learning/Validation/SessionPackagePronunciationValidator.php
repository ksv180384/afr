<?php

namespace App\Services\Learning\Validation;

use App\DTOs\Learning\PackageValidationReport;
use Normalizer;

final class SessionPackagePronunciationValidator
{
    public static function normalize(string $value): string
    {
        return trim(preg_replace('/\\s+/u', ' ', Normalizer::normalize(str_replace('’', "'", $value))));
    }

    public function validate(array $package, PackageValidationReport $report): void
    {
        $this->walk($package, '', $report);
    }

    private function walk(array $value, string $path, PackageValidationReport $report): void
    {
        if (! array_is_list($value)) {
            $notes = [];
            $text = self::normalize($this->strings($value));
            foreach ((array) ($value['pronunciation_notes'] ?? []) as $index => $note) {
                if (! is_array($note) || ! is_string($note['fr'] ?? null)) {
                    continue;
                }
                $fr = self::normalize($note['fr']);
                if (isset($notes[$fr])) {
                    $report->error('duplicate_pronunciation_note', $path.'/pronunciation_notes/'.$index.'/fr', 'Одна и та же запись произношения повторена в контейнере.');
                }
                $notes[$fr] = true;
                if ($fr !== '' && ! str_contains($text, $fr)) {
                    $report->error('pronunciation_note_mismatch', $path.'/pronunciation_notes/'.$index.'/fr', 'Французский фрагмент не найден в этом контейнере.');
                }
            }
            if (($value['answer_lang'] ?? null) === 'fr' && is_array($value['accepted_answers'] ?? null)) {
                foreach ($value['accepted_answers'] as $index => $answer) {
                    if (is_string($answer) && ! isset($notes[self::normalize($answer)])) {
                        $report->error('transcription_required', $path.'/accepted_answers/'.$index, 'Для каждого допустимого французского ответа нужна запись pronunciation_notes.');
                    }
                }
            }
            $mixed = [];
            foreach (['instruction', 'hint', 'correct', 'incorrect', 'heading', 'summary', 'description', 'title'] as $field) {
                if (is_string($value[$field] ?? null)) {
                    $mixed[] = $value[$field];
                }
            }
            foreach ((array) ($value['paragraphs'] ?? []) as $paragraph) {
                if (is_string($paragraph)) {
                    $mixed[] = $paragraph;
                }
            }
            if (isset($value['before_task_id']) && is_string($value['text'] ?? null)) {
                $mixed[] = $value['text'];
            }
            if (isset($value['prompt']) && is_array($value['prompt']) && ($value['prompt']['text_lang'] ?? null) === 'ru' && is_string($value['prompt']['text'] ?? null)) {
                $mixed[] = $value['prompt']['text'];
            }
            $remaining = self::normalize(implode(' ', $mixed));
            $fragments = array_keys($notes);
            usort($fragments, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            foreach ($fragments as $fragment) {
                $remaining = str_replace($fragment, '', $remaining);
            }
            $remaining = preg_replace('/\\b(?:ID|JSON|PRE_A1|TTS)\\b/u', '', $remaining);
            if (preg_match('/[A-Za-zÀ-ÖØ-öø-ÿŒœ]/u', $remaining)) {
                $report->warning('pronunciation_coverage_review', $path, 'Проверьте покрытие французских вставок русскими транскрипциями перед публикацией.');
            }
        }
        foreach ($value as $key => $child) {
            if (is_array($child) && $key !== 'pronunciation_notes') {
                $this->walk($child, $path.'/'.str_replace(['~', '/'], ['~0', '~1'], (string) $key), $report);
            }
        }
    }

    private function strings(array $value): string
    {
        $parts = [];
        foreach ($value as $key => $child) {
            if ($key === 'pronunciation_notes') {
                continue;
            }
            if (is_string($child)) {
                $parts[] = $child;
            } elseif (is_array($child)) {
                $parts[] = $this->strings($child);
            }
        }

        return implode(' ', $parts);
    }
}
