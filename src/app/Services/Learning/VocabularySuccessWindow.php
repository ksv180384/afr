<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningRun;
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

    /** The caller holds the personal word lock until the progress update commits. */
    public static function credit(PersonalVocabulary $word, LearningRun $run, CarbonImmutable $at): bool
    {
        if (! self::allows(self::latest($word), $at)) {
            return false;
        }
        $marker = VocabularyRunEffect::firstOrNew(['user_id' => $word->user_id, 'lexeme_key' => $word->lexeme_key, 'run_id' => $run->id]);
        if ($marker->success_applied) {
            return false;
        }
        $marker->fill(['success_applied' => true, 'applied_at' => $at])->save();

        return true;
    }
}
