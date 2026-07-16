<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class SongFilters extends Filter
{
    /**
     * Фильтрует песни по названию и по имени любого связанного исполнителя.
     */
    protected function text(string $value): Builder
    {
        $value = trim($value);

        if ($value === '') {
            return $this->builder;
        }

        return $this->builder
            ->where(function (Builder $query) use ($value) {
                $query
                    ->where('player_songs.artist_name', 'LIKE', '%' . $value . '%')
                    ->orWhere('player_songs.title', 'LIKE', '%' . $value . '%')
                    ->orWhereHas('artists', function (Builder $artistQuery) use ($value) {
                        $artistQuery->where('player_artists_songs.name', 'LIKE', '%' . $value . '%');
                    });
            });
    }
}
