<?php

namespace App\Http\Resources\Admin\Song;

use App\Http\Resources\Admin\User\UserMiniResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
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
            'artist' => $this->artist,
            'title' => $this->title,
            'hidden' => $this->hidden,
            'user' => UserMiniResource::make($this->user),
            'created_at' => !empty($this->created_at) ? $this->created_at->format('d.m.Y H:i') : '',
            'created' => !empty($this->created_at) ? $this->created_at->diffForHumans() : '',
            'updated_at' => !empty($this->updated_at) ? $this->updated_at->format('d.m.Y H:i') : '',
            'updated' => !empty($this->updated_at) ? $this->updated_at->diffForHumans() : '',
        ];
    }
}
