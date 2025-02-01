<?php

namespace App\Http\Resources\App\Post;

use App\Http\Resources\App\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostShowResource extends JsonResource
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
            'content' => $this->content,
            'user' => UserResource::make($this->user),
            'status' => $this->status,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'created' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('d.m.Y H:i'),
            'updated' => $this->updated_at->diffForHumans(),
        ];

        return $result;
    }
}
