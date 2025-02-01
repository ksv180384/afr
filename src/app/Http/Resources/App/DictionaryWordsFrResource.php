<?php

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DictionaryWordsFrResource extends JsonResource
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
            'ru' => $this->translation,
            'translation' => $this->translation,
            'word' => $this->word,
            'word_original' => $this->word,
            'transcription' => $this->transcription,
            'example' => $this->example,
            'part_of_speech_name' => optional($this->part_of_speech)->title,
        ];
    }
}
