<?php

namespace App\Services\Learning;

use App\Models\Learning\PracticeLexeme;
use App\Models\Learning\SessionLexeme;

final class PronunciationCatalog
{
    /** Key by spoken text, including lesson-specific variants and examples. */
    public function rows(): array
    {
        $rows = [];
        $add = static function (?string $text, array $translations) use (&$rows): void {
            if ($text === null || trim($text) === '') {
                return;
            }
            $key = PronunciationRecordings::key($text);
            $rows[$key] ??= ['key' => $key, 'text' => $text, 'ru' => []];
            $rows[$key]['ru'] = array_values(array_unique([...$rows[$key]['ru'], ...$translations]));
        };
        $cards = PracticeLexeme::pluck('content', 'lexeme_key');
        foreach ($cards as $card) {
            $add($card['fr'], $card['ru']);
        }
        $entries = SessionLexeme::select('lexeme_key', 'context->fr as fr', 'context->ru as ru',
            'context->example->fr as example_fr', 'context->example->ru as example_ru')
            ->whereNotNull('context->example->fr')->orWhereNotNull('context->fr')->orWhereNotNull('context->ru')->toBase()->cursor();
        foreach ($entries as $entry) {
            if ($entry->fr !== null || $entry->ru !== null) {
                $card = $cards[$entry->lexeme_key];
                $add($entry->fr ?? $card['fr'], $entry->ru === null ? $card['ru'] : json_decode($entry->ru, true, flags: JSON_THROW_ON_ERROR));
            }
            $add($entry->example_fr, $entry->example_ru === null ? [] : [$entry->example_ru]);
        }

        return $rows;
    }
}
