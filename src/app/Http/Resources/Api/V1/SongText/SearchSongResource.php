<?php

namespace App\Http\Resources\Api\V1\SongText;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchSongResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = [
            'id' => $this->id,
            'artist_name' => $this->artist_name,
            'artist' => $this->artist,
            'title' => $this->title,
        ];

        return $result;
    }
}
