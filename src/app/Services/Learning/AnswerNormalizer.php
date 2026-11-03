<?php

namespace App\Services\Learning;

use Normalizer;

final class AnswerNormalizer
{
    public static function surface(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            AnswerChecker::invalid('Требуется корректный текст UTF-8.');
        }
        $nfc = Normalizer::normalize(str_replace('’', "'", $value), Normalizer::FORM_C);
        $spaces = preg_replace('/[\\x{0009}-\\x{000D}\\x{0020}\\x{0085}\\x{00A0}\\x{1680}\\x{2000}-\\x{200A}\\x{2028}\\x{2029}\\x{202F}\\x{205F}\\x{3000}\\x{FEFF}]+/u', ' ', $nfc);

        return trim($spaces, ' ');
    }

    public static function normalize(string $value): string
    {
        return preg_replace('/^[\\p{P} ]+|[\\p{P} ]+$/u', '', mb_strtolower(self::surface($value), 'UTF-8'));
    }
}
