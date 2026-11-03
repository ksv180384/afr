<?php

namespace App\Http\Resources\Admin\Artist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistLineupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'artists' => $this->artists->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'image_url' => $artist->image_url,
            ])->sortBy('name')->values(),
            'songs' => $this->songs->map(fn ($song) => [
                'id' => $song->id,
                'title' => $song->title,
                'artist_name' => $song->artist_name,
            ])->values(),
        ];
    }
}
