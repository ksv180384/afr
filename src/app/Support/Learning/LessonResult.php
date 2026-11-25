<?php

namespace App\Support\Learning;

final class LessonResult
{
    // The order of these counters is part of the v1 storage format.
    public const COUNTERS = ['total_steps', 'independent_correct_steps', 'technical_skips', 'mistakes_count', 'words_added', 'words_existing'];

    public const FIELDS = [...self::COUNTERS, 'added_word_ids'];

    public static function compact(array $result): array
    {
        // Keep nonstandard historical results lossless, including missing fields.
        if (array_diff(self::COUNTERS, array_keys($result)) || array_diff(array_keys($result), self::FIELDS)) {
            return $result;
        }

        $packed = ['v' => 1, 's' => array_map(fn ($key) => $result[$key], self::COUNTERS)];
        if (array_key_exists('added_word_ids', $result)) {
            $packed['w'] = $result['added_word_ids'];
        }

        return $packed;
    }

    public static function expand(array $result): array
    {
        if (($result['v'] ?? null) !== 1 || ! is_array($result['s'] ?? null) || ! array_is_list($result['s'])
            || count($result['s']) !== count(self::COUNTERS) || array_diff(array_keys($result), ['v', 's', 'w'])) {
            return $result;
        }

        $expanded = array_combine(self::COUNTERS, $result['s']);
        if (array_key_exists('w', $result)) {
            $expanded['added_word_ids'] = $result['w'];
        }

        return $expanded;
    }
}
