<?php

namespace App\Http\Resources\Admin\Grammar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrammarEditResource extends JsonResource
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
            'description' => $this->description,
            'content' => $this->content,
            'source' => $this->source,
        ];

        return $result;
    }
}
