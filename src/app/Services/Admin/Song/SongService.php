<?php

namespace App\Services\Admin\Song;

use App\Models\Player\PlayerSongs;
use Illuminate\Pagination\LengthAwarePaginator;

class SongService
{
    const SONGS_PAGINATE = 40;

    /**
     * @param int $limit
     * @param bool $isHidden
     * @return LengthAwarePaginator
     */
    public function getSongsPagination(int $limit, bool $isHidden = false): LengthAwarePaginator
    {

        $songs = PlayerSongs::query()
            ->select([
                'player_songs.id',
                'player_songs.artist_id',
                'player_songs.artist_name',
                'player_songs.title',
                'player_songs.hidden',
                'player_songs.user_id',
            ])
            ->with(['artist:id,name'])
            ->with(['user:id,name'])
            ->when(!$isHidden, function ($q){
                $q->where('hidden', false);
            })
            ->orderBy('artist_name', 'ASC')
            ->orderBy('title', 'ASC')
            ->paginate($limit);

        return $songs;
    }


    /**
     * Получает песню
     * int $id - идентификатор записи
     * @return PlayerSongs
     */
    public function getById(int $id): PlayerSongs
    {
        $song = PlayerSongs::query()
            ->select([
                'id',
                'artist_id',
                'title',
                'text_fr',
                'text_ru',
                'text_transcription',
            ])
            ->with(['artist:id,name'])
            ->where('id', $id)
            ->first();

        return $song;
    }
}
