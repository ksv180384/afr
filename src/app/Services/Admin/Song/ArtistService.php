<?php

namespace App\Services\Admin\Song;

use App\Models\Player\PlayerArtistsSong;
use Illuminate\Database\Eloquent\Collection;

class ArtistService
{
    /**
     * Возвращает отсортированный список исполнителей для полей выбора в админке.
     *
     * @return Collection
     */
    public function getArtistsForSelect(): Collection
    {
        return PlayerArtistsSong::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Находит выбранных исполнителей или создаёт новых, сохраняя порядок и убирая дубликаты.
     *
     * @param array<int, array{id: int|null, name: string|null}> $artists
     */
    public function resolveArtists(array $artists): Collection
    {
        $resolved = new Collection();

        foreach ($artists as $artistData) {
            $artist = ! empty($artistData['id'])
                ? PlayerArtistsSong::findOrFail($artistData['id'])
                : PlayerArtistsSong::query()->firstOrCreate([
                    'name' => trim($artistData['name']),
                ]);

            if (! $resolved->contains('id', $artist->id)) {
                $resolved->push($artist);
            }
        }

        return $resolved;
    }
}
