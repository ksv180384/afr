<?php

namespace App\Services\App\PostComment;

use App\Models\PostComment\PostComment;
use Illuminate\Database\Eloquent\Collection;

class PostCommentService
{
    /**
     * @param int $postId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPostComments(int $postId): Collection
    {
        $postComments = PostComment::query()
            ->with(['user:id,name,avatar'])
            ->where('post_id', $postId)
            ->where('is_show', true)
            ->orderByDesc('created_at')
            ->get();

        return $postComments;
    }

    public function create(array $commentData): PostComment
    {
        $postComment = PostComment::query()->create([
            'user_id' => $commentData['user_id'],
            'post_id' => $commentData['post_id'],
            'parent_post_comment_id' => $commentData['parent_post_comment_id'] ?? null,
            'comment' => $commentData['comment'],
        ]);

        return $postComment;
    }
}
