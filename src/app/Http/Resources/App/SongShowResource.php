<?php

namespace App\Http\Resources\App;

use App\Services\App\Song\SongService;
use App\Services\PlayerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist_name' => $this->artist_name,
            'text_fr' => explode("\n", (new PlayerService())->formatText($this->text_fr)),
            'text_ru' => explode("\n", (new PlayerService())->formatText($this->text_ru)),
            'text_transcription' => explode("\n", (new PlayerService())->formatText($this->text_transcription)),
        ];
    }
}
