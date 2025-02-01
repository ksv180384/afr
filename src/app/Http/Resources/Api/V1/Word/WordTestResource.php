<?php

namespace App\Http\Resources\Api\V1\Word;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordTestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'answer' => $this['answer'],
            'answer_options' => $this['answer_options'],
        ];
    }
}
