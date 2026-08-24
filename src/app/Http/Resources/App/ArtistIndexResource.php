<?php

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'songs_count' => (int) ($this->songs_count ?? 0),
            'songs' => $this->whenLoaded('songs', fn () => $this->songs
                ->map(fn ($song) => [
                    'id' => $song->id,
                    'title' => $song->title,
                ])
                ->values()),
        ];
    }
}
