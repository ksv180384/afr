<?php

namespace App\Http\Resources\App\PostComment;

use App\Support\ViewerTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostShowCommentResource extends JsonResource
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
            'created_at' => ViewerTime::format($this->created_at, $request),
            'created' => $this->created_at?->diffForHumans(),
            'updated_at' => ViewerTime::format($this->updated_at, $request),
            'updated' => $this->updated_at?->diffForHumans(),
        ];

        return $result;
    }
}
