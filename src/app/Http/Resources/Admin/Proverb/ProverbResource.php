<?php

namespace App\Http\Resources\Admin\Proverb;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProverbResource extends JsonResource
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
            'text' => $this->text,
            'translation' => $this->translation,
        ];
    }
}
