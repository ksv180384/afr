<?php

namespace App\Http\Resources\Admin\Dictionary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordEditResource extends JsonResource
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
            'id_part_of_speech' => $this->id_part_of_speech,
            'word' => $this->word,
            'translation' => $this->translation,
            'transcription' => $this->transcription,
            'example' => $this->example,
        ];

        return $result;
    }
}
