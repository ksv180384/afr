<?php

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => filled($this->description) ? $this->description : null,
            'image_url' => $this->image_url,
            'songs_count' => (int) ($this->songs_count ?? 0),
            'songs' => $this->songs->map(fn ($song) => [
                'id' => $song->id,
                'artist_name' => $song->artist_name,
                'title' => $song->title,
            ])->values(),
            'lineups' => $this->lineups->map(fn ($lineup) => [
                'id' => $lineup->id,
                'name' => $lineup->artists->pluck('name')->implode(' + '),
                'description' => filled($lineup->description) ? $lineup->description : null,
                'image_url' => $lineup->image_url,
                'artists' => $lineup->artists->map(fn ($artist) => [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'image_url' => $artist->image_url,
                ])->values(),
                'songs' => $lineup->songs->map(fn ($song) => [
                    'id' => $song->id,
                    'artist_name' => $song->artist_name,
                    'title' => $song->title,
                ])->values(),
            ])->values(),
        ];
    }
}
