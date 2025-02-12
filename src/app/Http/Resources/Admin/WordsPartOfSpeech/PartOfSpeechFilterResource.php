<?php

namespace App\Http\Resources\Admin\WordsPartOfSpeech;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartOfSpeechFilterResource extends JsonResource
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
            'title' => $this->title,
        ];

        return $result;
    }
}
