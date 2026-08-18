<?php

namespace App\Services\Admin\Artist;

use App\Models\Player\PlayerArtistLineup;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use Illuminate\Support\Collection;

class ArtistLineupService
{
    /**
     * Привязывает песню к единственному составу, определяемому полным набором её исполнителей.
     *
     * @param Collection<int, PlayerArtistsSong> $artists
     */
    public function syncForSong(PlayerSongs $song, Collection $artists): void
    {
        $artistIds = $artists
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        if ($artistIds->count() < 2) {
            $song->artist_lineup_id = null;
            $song->save();

            return;
        }

        $lineup = PlayerArtistLineup::query()->firstOrCreate([
            'signature' => $artistIds->implode(':'),
        ]);

        $lineup->artists()->sync($artistIds->all());

        $song->artist_lineup_id = $lineup->id;
        $song->save();
    }
}
