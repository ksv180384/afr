<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostComment\CreatePostCommentRequest;
use App\Jobs\SendTelegramLogJob;
use App\Models\Post\Post;
use App\Services\App\PostComment\PostCommentService;
use Illuminate\Support\Facades\Redirect;

class PostCommentController extends Controller
{
    public function store(
        CreatePostCommentRequest $request,
        PostCommentService $commentService
    )
    {
        $commentData = $request->validated();
        $commentService->create($commentData);

        $post = Post::query()->find($commentData['post_id']);

        SendTelegramLogJob::dispatch('Добавлен комментарий к посту "' . $post->title . '": ' . route('post.show', ['id' => $post->id]))->afterResponse();

        return Redirect::route('post.show', ['id' => $commentData['post_id']]);
    }
}
