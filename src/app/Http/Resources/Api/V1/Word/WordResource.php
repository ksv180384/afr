<?php

namespace App\Http\Resources\Api\V1\Word;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordResource extends JsonResource
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
            'word' => $this->word,
            'translation' => $this->translation,
            'transcription' => $this->transcription,
        ];

        return $result;
    }
}
