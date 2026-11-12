<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningLevel;

final class AvailableLevels
{
    public static function first(): string
    {
        return LearningLevel::whereNull('archived_at')->orderBy('sort_order')->value('code')
            ?? LearningLevel::orderBy('sort_order')->value('code') ?? 'PRE_A1';
    }

    public static function next(string $code): ?string
    {
        $order = LearningLevel::where('code', $code)->value('sort_order');

        return $order === null ? null : LearningLevel::where('sort_order', '>', $order)->whereNull('archived_at')->orderBy('sort_order')->value('code');
    }

    public static function choices(): array
    {
        $codes = LearningLevel::whereNull('archived_at')->pluck('code')->all();

        return array_values(array_filter(config('daily_practice.levels'), fn ($row) => in_array($row['code'], $codes, true)));
    }
}
