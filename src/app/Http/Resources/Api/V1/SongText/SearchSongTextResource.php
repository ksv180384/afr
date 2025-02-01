<?php

namespace App\Http\Resources\Api\V1\SongText;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchSongTextResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = [
            'artist_name' => $this->artist_name,
            'title' => $this->title,
            'text_fr' => $this->text_fr,
            'text_ru' => $this->text_ru,
            'text_transcription' => $this->text_transcription,
        ];

        return $result;
    }
}
