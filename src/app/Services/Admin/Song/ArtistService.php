<?php

namespace App\Services\Admin\Song;

use App\Models\Player\PlayerArtistsSong;
use Illuminate\Database\Eloquent\Collection;

class ArtistService
{
    /**
     * Получаем исполнителей для вывода в селекте
     * @return Collection
     */
    public function getArtistsForSelect(): Collection
    {
        $artists = PlayerArtistsSong::query()->orderBy('name')->get(['id', 'name']);

        return $artists;
    }
}
