<?php

namespace App\Http\Resources\Admin\User;

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
        return [
            'id' => $this->id,
            'avatar' => $this->avatar,
            'avatar_link' => $this->avatar_link,
            'name' => $this->name,
            'email' => $this->email,
            'rang' => $this->rang,
            'email_verified_at' => $this->email_verified_at?->format('d.m.Y H:i'),
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'is_ban' => $this->is_ban,
        ];
    }
}
