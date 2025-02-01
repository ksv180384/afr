<?php

namespace App\Http\Resources\Admin\Song;

use App\Services\PlayerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $arTextFr = collect((new PlayerService())->formatTextToArray($this->text_fr))->map(function ($item, $key){
            return ['time' => $key, 'text' => $item];
        });
        $arTextRu = collect((new PlayerService())->formatTextToArray($this->text_ru))->map(function ($item, $key){
            return ['time' => $key, 'text' => $item];
        });
        $arTextTranscription = collect((new PlayerService())->formatTextToArray($this->text_transcription))->map(function ($item, $key){
            return ['time' => $key, 'text' => $item];
        });

        return [
            'id' => $this->id,
            'title' => $this->title,
            'text_fr' => $this->text_fr,
            'ar_text_fr' => $arTextFr->values(),
            'ar_text_ru' => $arTextRu->values(),
            'ar_text_transcription' => $arTextTranscription->values(),
            'text_ru' => $this->text_ru,
            'text_transcription' => $this->text_transcription,
            'hidden' => $this->hidden,
            'artist' => $this->artist,
        ];
    }
}
