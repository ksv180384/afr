<?php

namespace App\Services\Admin\Song;

use App\Models\Player\PlayerSearchSong;

class SongSearchService
{
    const SONGS_PAGINATE = 50;

    public function searchSongsPagination($limit)
    {
        $searchSongs = PlayerSearchSong::query()->orderByDesc('created_at')->paginate($limit);

        return $searchSongs;
    }
}
