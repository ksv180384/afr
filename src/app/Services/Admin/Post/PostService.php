<?php

namespace App\Services\Admin\Post;

use App\Filters\PostFilter;
use App\Helpers\Helper;
use App\Models\Post\Post;
use App\Models\Post\PostView;
use App\Services\App\HtmlTruncatorService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PostService
{
    const POSTS_PAGINATE = 10;

    const POST_PREVIEW_LENGTH = 400;

    /**
     * @param int $paginateCount
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPostPaginate(int $paginateCount): LengthAwarePaginator
    {
        $posts = Post::query()
            ->select([
                'id',
                'user_id',
                'title',
                'preview',
                'status_id',
                'created_at',
                'updated_at',
            ])
            ->with([
                'user:id,name,avatar,rang_id',
                'user.rang',
                'status'
            ])
            ->withCount(['views', 'comments' => function ($q) {
                $q->where('is_show', true);
            }])
            ->orderByDesc('updated_at')
            ->paginate($paginateCount);

        return $posts;
    }

    public function getPostByUserIdPaginate(int $userId, int $paginateCount): LengthAwarePaginator
    {
        $filter = new PostFilter(request());

        $posts = Post::query()
            ->select([
                'id',
                'user_id',
                'title',
                'preview',
                'status_id',
                'created_at',
                'updated_at',
            ])
            ->with([
                'user:id,name,avatar,rang_id',
                'user.rang',
                'status'
            ])
            ->withCount(['views', 'comments' => function ($q) {
                $q->where('is_show', true);
            }])
            ->filter($filter)
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->paginate($paginateCount);

        return $posts;
    }

    /**
     * @param array $postData
     * @return mixed
     */
    public function create(array $postData): Post
    {
        $htmlTruncator = new HtmlTruncatorService();
        $preview = $htmlTruncator->truncateTextInHtml($postData['content'], self::POST_PREVIEW_LENGTH);

        $post = Post::create([
            'user_id' => Auth::id(),
            'title' => $postData['title'],
            'preview' => $preview,
            'content' => $postData['content'],
            'status_id' => $postData['status_id'],
        ]);

        return $post;
    }

    public function update(int $postId, array $postData): Post
    {
        $post = Post::query()->findOrFail($postId);

        if(!empty($postData['content'])){
            $htmlTruncator = new HtmlTruncatorService();
            $preview = $htmlTruncator->truncateTextInHtml($postData['content'], self::POST_PREVIEW_LENGTH);
        }

        $post->update([
//            'user_id' => Auth::id(),
            'title' => $postData['title'] ?? $post->title,
            'preview' => $preview ?? $post->preview,
            'content' => $postData['content'] ?? $post->content,
            'status_id' => $postData['status_id'] ?? $post->status_id,
        ]);

        return $post;
    }

    /**
     * Помечаем пост прочитанным определенным пользователем
     * @param int $postId
     * @return void
     */
    public function markViewedPost(int $postId)
    {
        $userId = Auth::id();
        $userKey = Helper::getUserKey();

        $postView = PostView::query()
            ->where('post_id', $postId)
            ->where(function ($q) use ($userId, $userKey) {
                $q->where('user_id', $userId)
                    ->orWhere('user_key', $userKey);
            })
            ->first();

        if(!$postView){
            PostView::query()->create([
                'user_id' => $userId ?? null,
                'post_id' => $postId,
                'user_key' => $userKey,
            ]);
        }
        else{
            if(!$postView->user_id && $userId){
                $postView->user_id = $userId;
            }
            if(!$postView->user_key){
                $postView->user_key = $userKey;
            }
            $postView->save();
        }
    }
}
