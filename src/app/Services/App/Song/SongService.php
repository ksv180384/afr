<?php

namespace App\Services\App\Song;

use App\Filters\SongFilters;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Services\PlayerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SongService
{
    const SONGS_SEARCH_LIMIT = 10;
    const SONGS_PAGINATE = 40;

    /**
     * @param int $limit
     * @param bool $isHidden
     * @return LengthAwarePaginator
     */
    public function getSongsPagination(int $limit, bool $isHidden = false): LengthAwarePaginator
    {

        $filter = new SongFilters(request());

        $songs = PlayerSongs::query()
            ->select([
                'player_songs.id',
                'player_songs.artist_id',
                'player_songs.artist_name',
                'player_songs.title',
                'player_songs.hidden',
            ])
            ->with(['artist:id,name'])
            ->filter($filter)
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
                'artist_name',
                'title',
                'text_fr',
                'text_ru',
                'text_transcription',
            ])
            ->where('hidden', false)
            ->where('id', $id)
            ->first();

        return $song;
    }

    /**
     * Получает список всех активных песен
     * @param bool $isAll - получать все, включая скрытые
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSongs(bool $isAll = false): Collection
    {
        if($isAll){
            $songsQuery = PlayerSongs::query();
        }else{
            $songsQuery = PlayerSongs::query()
                ->where('hidden', false);
        }

        $songs = $songsQuery
            ->orderBy('artist_name', 'ASC')
            ->orderBy('title', 'ASC')
            ->get([
                'id',
                'artist_id',
                'artist_name',
                'title',
            ]);

        return $songs;
    }

    /**
     * @return Collection
     */
    public function getArtistsSongs(): Collection
    {
        $artists = PlayerArtistsSong::query()
            ->with(['songs' => function ($q) {
                $q->select('id', 'title', 'artist_id')->where('hidden', false);
            }])
            ->orderBy('name')
            ->get();

        return $artists;
    }

    /**
     * Получает трек по названию и исполнителю
     * @param string $artist - исполнитель
     * @param string $title - название трека
     * @param string $fileName - название файла трека
     * @return PlayerSongs
     */
    public function searchByArtistAndTitle(string $artist, string $title, string $fileName): PlayerSongs | null
    {

        $searchText = (new PlayerService())->getSongNameFromFileName($fileName);

        $song = PlayerSongs::select([
                'player_songs.artist_id',
                'player_songs.artist_name',
                'player_songs.title',
                'player_songs.text_fr',
                'player_songs.text_ru',
                'player_songs.text_transcription',
                'player_songs.user_id',
                'player_songs.created_at',
                'player_songs.updated_at',
                'users.name',
            ])
            ->leftJoin('users', 'player_songs.user_id', '=', 'users.id')
            ->where('player_songs.hidden', '=', 0)
            ->where(function ($q) use ($artist, $title, $searchText) {
                return $q->when($artist, function ($q) use ($artist) {
                        return $q->orWhere('player_songs.artist_name', '=', $artist);
                    })
                    ->when($title, function ($q) use ($title) {
                        return $q->orWhere('player_songs.title', '=', $title);
                    })
                    ->when(!$title && !$title && $searchText, function ($q) use ($searchText) {
                        return $q->whereRaw("MATCH(player_songs.artist_name, player_songs.title) AGAINST (? IN BOOLEAN MODE)", [$searchText]);
                    });
            })
            ->first();

        return $song;
    }

    /**
     * @param string $searchText - название песни или артиста
     * @return Collection
     */
    public function search(string $searchText): Collection
    {

        $searchText = preg_replace('/[^a-zA-Zа-яА-Я0-9à-ÿ]/u', '', $searchText);

        $songs = PlayerSongs::select([
                'player_songs.id',
                'player_songs.artist_id',
                'player_songs.artist_name',
                'player_songs.title',
            ])
            ->leftJoin('users', 'player_songs.user_id', '=', 'users.id')
            ->join('player_artists_songs', 'player_songs.artist_id', '=', 'player_artists_songs.id')
            ->where('player_songs.hidden', '=', 0)
            // TODO Добавить в таблицу поле для поиска, которое объединяет player_artists_songs.name, player_songs.title без символов
            ->whereRaw("REGEXP_REPLACE(CONCAT(player_artists_songs.name, player_songs.title), '[^a-zA-Z0-9à-ÿ]', '') LIKE ?", ['%' . $searchText . '%'])
//            ->whereRaw("MATCH(player_artists_songs.name, player_songs.title) AGAINST (? IN BOOLEAN MODE)", [$searchText])
            ->limit(self::SONGS_SEARCH_LIMIT)
            ->get();

        return $songs;
    }

    /**
     * Поиск по тексту
     * @param $searchText - название песни или артиста
     * @return mixed
     */
    /*
    public function searchText(string $searchText)
    {
        $songs = PlayerSongs::where('hidden', '=', 0)
            ->where('text_fr', 'LIKE', '%' . $searchText . '%')
            ->get(['text_fr', 'text_ru', 'text_transcription']);

        $songs = $songs->map(function ($item){
            $item->text_fr = $this->formatText($item->text_fr);
            $item->text_ru = $this->formatText($item->text_ru);
            $item->text_transcription = $this->formatText($item->text_transcription);
            return $item;
        });

        return $songs;
    }
    */

    /*
    public function getArtists(){
        $artists = PlayerArtistsSong::select(['id', 'name'])->orderBy('name', 'ASC')->get();

        return $artists;
    }
    */

}
