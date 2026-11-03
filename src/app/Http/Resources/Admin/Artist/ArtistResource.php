<?php

namespace App\Http\Resources\Admin\Artist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'songs_count' => $this->songs_count ?? 0,
            'lineups_count' => $this->lineups_count ?? 0,
        ];
    }
}
