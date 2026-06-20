<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class SongFilters extends Filter
{
    protected function text(string $value): Builder
    {
        $value = trim($value);

        if ($value === '') {
            return $this->builder;
        }

        return $this->builder
            ->join('player_artists_songs', 'player_songs.artist_id', '=', 'player_artists_songs.id')
            ->where(function (Builder $query) use ($value) {
                $query
                    ->where('player_artists_songs.name', 'LIKE', '%' . $value . '%')
                    ->orWhere('player_songs.artist_name', 'LIKE', '%' . $value . '%')
                    ->orWhere('player_songs.title', 'LIKE', '%' . $value . '%');
            });
    }
}
