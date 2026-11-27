<?php

namespace App\Http\Resources\Admin\User;

use App\Support\ViewerTime;
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
            'email_verified_at' => ViewerTime::format($this->email_verified_at, $request),
            'created_at' => ViewerTime::format($this->created_at, $request),
            'is_ban' => $this->is_ban,
        ];
    }
}
