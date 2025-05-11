<?php

namespace App\Http\Resources\App\SongComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongCommentResource extends JsonResource
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
            'comment' => nl2br($this->comment),
            'user' => $this->user,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'created' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('d.m.Y H:i'),
            'updated' => $this->updated_at->diffForHumans(),
        ];

        return $result;
    }
}
