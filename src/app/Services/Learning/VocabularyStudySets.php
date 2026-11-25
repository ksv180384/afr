<?php

namespace App\Services\Learning;

use Illuminate\Support\Facades\DB;

final class VocabularyStudySets
{
    /** The caller holds a transaction. Entry IDs belong to one practice lesson. */
    public static function forEntries(int $sessionId, array $entryIds): int
    {
        $entryIds = array_unique($entryIds);
        sort($entryIds, SORT_NUMERIC);
        if (! $entryIds) {
            throw new \InvalidArgumentException('An empty vocabulary set has no source.');
        }

        $key = ['session_id' => $sessionId, 'signature' => hash('sha256', implode(',', $entryIds))];
        $sets = DB::table('learning_vocabulary_sets');
        $setId = (clone $sets)->where($key)->value('id');
        if ($setId !== null) {
            return $setId;
        }

        $created = $sets->insertOrIgnore($key);
        $setId = (clone $sets)->where($key)->lockForUpdate()->firstOrFail(['id'])->id;
        if ($created) {
            DB::table('learning_vocabulary_set_entries')->insert(array_map(
                fn ($id) => ['set_id' => $setId, 'session_lexeme_id' => $id], $entryIds
            ));
        }

        return $setId;
    }
}
