<?php

namespace App\Http\Resources\Admin\PostComment;

use App\Support\ViewerTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentResource extends JsonResource
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
            'post' => $this->post,
            'is_show' => $this->is_show,
            'created_at' => ViewerTime::format($this->created_at, $request),
            'created' => $this->created_at?->diffForHumans(),
            'updated_at' => ViewerTime::format($this->updated_at, $request),
            'updated' => $this->updated_at?->diffForHumans(),
        ];

        return $result;
    }
}
