<?php

namespace App\Services\Learning;

final class ShortReviewSelection
{
    public static function items(array $source): array
    {
        return array_values(array_filter(is_array($source['review_items'] ?? null) ? $source['review_items'] : [], function ($item) {
            if (! is_array($item) || empty($item['id']) || ! is_array($item['prompt'] ?? null)) { return false; }
            if (($item['type'] ?? '') === 'short_answer') { return is_array($item['accepted_answers'] ?? null) && count($item['accepted_answers']) > 0; }
            if (($item['type'] ?? '') !== 'single_choice' || ! is_array($item['choices'] ?? null) || count($item['choices']) < 2) { return false; }
            $ids = array_column($item['choices'], 'id');

            return count($ids) === count($item['choices']) && count(array_filter($ids, fn ($id) => is_string($id) && $id !== '')) === count($ids) && count($ids) === count(array_unique($ids)) && in_array($item['correct_choice_id'] ?? null, $ids, true);
        }));
    }

    private static function normalized(string $text): string
    {
        $text = mb_strtolower(str_replace(['’', 'ʼ'], "'", $text));
        $text = preg_replace('/[\p{P}\p{Z}\s]+/u', ' ', $text);

        return trim(preg_replace('/^(?:le|la|les|un|une|des|l) /u', '', trim($text)));
    }

    public static function category(array $item, array $source): string
    {
        $prompt = $item['prompt'] ?? [];
        $text = $prompt['audio']['text'] ?? $prompt['text'] ?? '';
        if (self::normalized($text) === self::normalized($source['fr'] ?? '')) { return 'meaning'; }
        if (($prompt['text_lang'] ?? '') === 'ru' && empty($prompt['audio']) && preg_match('/(?:перевод|что означает|значение слова)/ui', $text)) { return 'meaning'; }

        return 'context';
    }

    /** Rows have key, tier, due, topic and optional previous-kind. */
    public static function select(array $rows, array $previousKeys = []): array
    {
        $fresh = array_values(array_filter($rows, fn ($row) => ! in_array($row['key'], $previousKeys, true)));
        if ($fresh) { $rows = $fresh; }
        usort($rows, fn ($a, $b) => ($a['tier'] <=> $b['tier']) ?: ($a['due'] <=> $b['due']) ?: strcmp($a['key'], $b['key']));
        $selected = []; $topics = [];
        while ($rows && count($selected) < 3) {
            $tier = $rows[0]['tier']; $index = 0;
            foreach ($rows as $i => $row) {
                if ($row['tier'] !== $tier) { break; }
                if (! in_array($row['topic'], $topics, true)) { $index = $i; break; }
            }
            $row = array_splice($rows, $index, 1)[0]; $selected[] = $row; $topics[] = $row['topic'];
        }

        return $selected;
    }

    private static function optionPool(array $source, int $index, ?string $previousKind): array
    {
        $items = self::items($source);
        $preferred = $previousKind ? ($previousKind === 'context' ? 'meaning' : 'context') : ($index % 2 === 0 ? 'context' : 'meaning');

        return array_values(array_filter($items, fn ($item) => self::category($item, $source) === $preferred)) ?: $items;
    }

    public static function estimateSeconds(array $source, int $index, ?string $previousKind): int
    {
        return max(array_map(function ($item) {
            $seconds = $item['type'] === 'short_answer' ? 40 : 30;
            $audio = $item['prompt']['audio'] ?? null;
            if ($audio) {
                $words = preg_split('/\s+/u', trim($audio['text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                $seconds += max(25, (int) ceil(count($words ?: []) * 4 / 3));
            }

            return $seconds;
        }, self::optionPool($source, $index, $previousKind)));
    }

    public static function option(array $source, string $seed, string $key, int $index, ?string $previousKind, ?string $previousId = null): array
    {
        $pool = self::optionPool($source, $index, $previousKind);
        $alternatives = array_values(array_filter($pool, fn ($item) => $item['id'] !== $previousId));
        $pool = $alternatives ?: $pool;

        return $pool[array_sum(unpack('C*', $seed.$key)) % count($pool)];
    }
}
