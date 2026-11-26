<?php

namespace App\Services\Learning;

final class PronunciationRecordings
{
    public const VOICES = ['lea', 'paul'];

    public static function text(string $text): string
    {
        $normalized = \Normalizer::normalize($text) ?: $text;

        return trim(preg_replace('/\s+/u', ' ', str_replace('’', "'", $normalized)));
    }

    public static function key(string $text): string
    {
        return hash('sha256', self::text($text));
    }

    /** Only internally derived or validated SHA-256 keys reach the filesystem. */
    public static function files(string $key): array
    {
        $files = [];
        foreach (self::VOICES as $voice) {
            $path = '/practice-audio/'.substr($key, 0, 2)."/{$key}-{$voice}.mp3";
            $absolute = public_path($path);
            if (is_file($absolute) && filesize($absolute) > 0) {
                $files[$voice] = $path.'?v='.filemtime($absolute).'-'.filesize($absolute);
            }
        }

        return $files;
    }
}
