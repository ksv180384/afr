<?php

namespace App\Services\Learning;

use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\VocabularyRunEffect;
use Carbon\CarbonImmutable;

final class VocabularySuccessWindow
{
    public const SECONDS = 86400;

    /** The durable credit ledger is authoritative, not last_reviewed_at (which also records errors). */
    public static function latest(PersonalVocabulary $word): ?CarbonImmutable
    {
        $at = VocabularyRunEffect::where('user_id', $word->user_id)->where('lexeme_key', $word->lexeme_key)
            ->where('success_applied', true)->max('applied_at');

        return $at ? CarbonImmutable::parse($at, 'UTC')->utc() : null;
    }

    public static function allows(?CarbonImmutable $last, CarbonImmutable $at): bool
    {
        return $last === null || $at->greaterThanOrEqualTo($last->addSeconds(self::SECONDS));
    }
}
