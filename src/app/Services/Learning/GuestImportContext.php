<?php

namespace App\Services\Learning;

use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\PersonalVocabulary;

class GuestImportContext
{
    public ?GuestSyncEpisode $episode = null;

    public ?\Carbon\CarbonImmutable $sourceAt = null;

    public function during(GuestSyncEpisode $episode, \Closure $action): mixed
    {
        $previous = $this->episode;
        $previousSource = $this->sourceAt;
        $this->sourceAt = null;
        $this->episode = $episode;
        try {
            return $action();
        } finally {
            $this->episode = $previous;
            $this->sourceAt = $previousSource;
        }
    }

    public function protected(PersonalVocabulary $word): bool
    {
        return $this->episode !== null && (! in_array($word->id, $this->episode->created_word_ids ?? [], true) || $word->native_revision > 0);
    }

    public function created(PersonalVocabulary $word): void
    {
        if ($this->episode) {
            $this->episode->update(['created_word_ids' => array_values(array_unique([...($this->episode->created_word_ids ?? []), $word->id]))]);
        }
    }
}
