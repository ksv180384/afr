<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class SongFilters extends Filter
{
    protected function text(string $value): Builder
    {
        $value = preg_replace('/[^a-zA-Zа-яА-Я0-9à-ÿ]/u', '', $value);
        return $this->builder
            ->join('player_artists_songs', 'player_songs.artist_id', '=', 'player_artists_songs.id')
            // TODO Добавить в таблицу поле для поиска, которое объединяет player_artists_songs.name, player_songs.title без символов
            ->whereRaw("REGEXP_REPLACE(CONCAT(player_artists_songs.name, player_songs.title), '[^a-zA-Z0-9à-ÿ]', '') LIKE ?", ['%' . $value . '%']);
//            ->where(function ($q) use ($value) {
//                return $q
//                    ->where(DB::raw('CONCAT(player_artists_songs.name, player_songs.title)'), 'LIKE', '%' . $value . '%');
//                    ->where('player_artists_songs.name', 'LIKE', '%' . $value . '%')
//                    ->orWhere('player_songs.title', 'LIKE', '%' . $value . '%');
//            });
    }
}
