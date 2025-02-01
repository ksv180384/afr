<?php

namespace App\Services\Admin\PostComment;

use App\Models\PostComment\PostComment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PostCommentService
{

    const PAGINATE_COMMENTS = 20;

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getComments($paginate = 10): LengthAwarePaginator
    {
        $postComments = PostComment::query()->with([
            'user:id,name,avatar',
            'post:id,title',
        ])->paginate($paginate);

        return $postComments;
    }

    /**
     * @param int $postId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPostComments(int $postId): Collection
    {
        $postComments = PostComment::query()
            ->where('post_id', $postId)
            ->where('is_show', true)
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
