<?php

namespace App\Http\Resources\App\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'avatar_link' => $this->avatar_link,
            'name' => $this->name,
            'rang' => $this->rang,
        ];

        return $result;
    }
}
