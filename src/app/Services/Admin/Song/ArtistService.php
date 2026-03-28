<?php

namespace App\Services\Admin\Song;

use App\Models\Player\PlayerArtistsSong;
use Illuminate\Database\Eloquent\Collection;

class ArtistService
{
    /**
     * @return Collection
     */
    public function getArtistsForSelect(): Collection
    {
        return PlayerArtistsSong::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Находит существующего исполнителя по ID или создаёт нового по имени.
     */
    public function resolveArtist(?int $artistId, ?string $artistName): PlayerArtistsSong
    {
        if ($artistId !== null) {
            return PlayerArtistsSong::findOrFail($artistId);
        }

        return PlayerArtistsSong::query()->firstOrCreate(
            ['name' => $artistName],
        );
    }
}
