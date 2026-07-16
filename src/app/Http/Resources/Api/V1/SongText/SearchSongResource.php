<?php

namespace App\Http\Resources\Api\V1\SongText;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchSongResource extends JsonResource
{
    /**
     * Формирует данные песни для поисковой выдачи API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = [
            'id' => $this->id,
            'artist_name' => $this->artist_name,
            'artist' => ['name' => $this->artist_name],
            'artists' => $this->whenLoaded('artists'),
            'title' => $this->title,
        ];

        return $result;
    }
}
