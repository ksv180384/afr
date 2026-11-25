<?php

namespace App\Support\Learning;

final class CompletedVocabularyRun
{
    // These tags, tuple positions and defaults are part of the on-disk v1 format.
    private const SNAPSHOT_TAGS = ['review' => 'rs1', 'vocabulary_test' => 'ts1'];
    private const CATEGORIES = ['meaning', 'context'];
    private const RESULT_TAG = 'vr1';

    public static function compactSnapshot(array $snapshot, string $kind): array
    {
        if (! isset(self::SNAPSHOT_TAGS[$kind], $snapshot['id'], $snapshot['level'], $snapshot['duration_minutes'])
            || ! is_array($snapshot['reviewed_items'] ?? null)) {
            return $snapshot;
        }

        $items = [];
        foreach ($snapshot['reviewed_items'] as $key => $item) {
            if (! is_array($item) || count($item) !== 2 || ! is_string($item['id'] ?? null)) {
                return $snapshot;
            }
            $category = array_search($item['category'] ?? null, self::CATEGORIES, true);
            if ($category === false) {
                return $snapshot;
            }
            $items[$key] = [$item['id'], $category];
        }

        $tuple = [self::SNAPSHOT_TAGS[$kind], $snapshot['id'], $snapshot['level'], $snapshot['duration_minutes'], $items];
        $metadata = array_diff_key($snapshot, array_flip(['id', 'level', 'duration_minutes', 'reviewed_items']));

        return self::withExceptions($tuple, $metadata, self::snapshotDefaults($kind));
    }

    public static function expandSnapshot(array $stored): array
    {
        $kind = array_search($stored[0] ?? null, self::SNAPSHOT_TAGS, true);
        if ($kind === false || ! array_is_list($stored) || ! is_array($stored[4] ?? null)) {
            return $stored;
        }
        [, $id, $level, $minutes, $packedItems] = $stored;
        $items = [];
        foreach ($packedItems as $key => [$itemId, $category]) {
            $items[$key] = ['id' => $itemId, 'category' => self::CATEGORIES[$category]];
        }

        return ['id' => $id, 'level' => $level, 'duration_minutes' => $minutes, 'reviewed_items' => $items]
            + self::restoreFields(self::snapshotDefaults($kind), $stored[5] ?? []);
    }

    private static function snapshotDefaults(string $kind): array
    {
        return [
            'title' => $kind === 'review' ? 'Повторение выражений' : 'Тест личного словаря',
            'description' => 'Набор выражений закреплён при старте.',
            'review_mode' => $kind === 'review' ? 'suggested' : null,
            'remaining_word_ids' => [],
            'self_check_words' => [],
        ];
    }

    public static function compactResult(array $result): array
    {
        $total = $result['total_steps'] ?? null;
        $correct = $result['independent_correct_steps'] ?? null;
        $skips = $result['technical_skips'] ?? null;
        $mistakes = $result['mistakes_count'] ?? 0;
        if (! is_int($total) || ! is_int($correct) || ! is_int($skips) || ! is_int($mistakes)) {
            return $result;
        }

        return self::withExceptions([self::RESULT_TAG, $total, $correct, $skips, $mistakes], $result,
            self::resultDefaults($total, $correct, $skips, $mistakes));
    }

    public static function expandResult(array $stored): array
    {
        if (($stored[0] ?? null) !== self::RESULT_TAG || ! array_is_list($stored) || count($stored) < 5) {
            return $stored;
        }
        [, $total, $correct, $skips, $mistakes] = $stored;

        return self::restoreFields(self::resultDefaults($total, $correct, $skips, $mistakes), $stored[5] ?? []);
    }

    private static function resultDefaults(int $total, int $correct, int $skips, int $mistakes): array
    {
        return [
            'checking_policy_version' => 1, 'total_steps' => $total, 'processed_steps' => $total,
            'independent_correct_steps' => $correct, 'score_numerator' => $correct, 'score_denominator' => $total,
            'score_percent' => $total ? $correct * 100 / $total : null, 'technical_skips' => $skips,
            'all_steps_processed' => $total > 0, 'words_added' => 0, 'words_existing' => 0,
            'promotion' => null, 'integrations_pending' => [], 'mistakes_count' => $mistakes,
        ];
    }

    private static function withExceptions(array $tuple, array $fields, array $defaults): array
    {
        $overrides = [];
        foreach ($fields as $key => $value) {
            if (! array_key_exists($key, $defaults) || $defaults[$key] !== $value) {
                $overrides[$key] = $value;
            }
        }
        $missing = array_keys(array_diff_key($defaults, $fields));
        if ($overrides || $missing) {
            $tuple[] = [$overrides, $missing];
        }

        return $tuple;
    }

    private static function restoreFields(array $defaults, array $exceptions): array
    {
        [$overrides, $missing] = $exceptions ?: [[], []];

        return array_diff_key(array_replace($defaults, $overrides), array_flip($missing));
    }
}
