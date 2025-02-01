<?php

namespace App\Http\Resources\Admin\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'preview' => $this->preview,
            'user' => $this->user,
            'views_count' => $this->views_count,
            'comments_count' => $this->comments_count,
            'status' => $this->status,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'created' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('d.m.Y H:i'),
            'updated' => $this->updated_at->diffForHumans(),
        ];

        return $result;
    }
}
