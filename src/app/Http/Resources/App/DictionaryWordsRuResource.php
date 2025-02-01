<?php

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DictionaryWordsRuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = explode(',', $this->translation)[0];

        return [
            'id' => $this->id,
            'fr' => $this->word,
            'translation' => $this->word,
            'word' => $translation,
            'word_original' => $this->translation,
            'transcription' => $this->transcription,
            'example' => $this->example,
            'part_of_speech_name' => optional($this->part_of_speech)->title,

        ];
    }
}
