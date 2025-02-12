<?php

namespace App\Http\Controllers\Admin\PostComment;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\PostComment\PostCommentResource;
use App\Models\PostComment\PostComment;
use App\Services\Admin\PostComment\PostCommentService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PostCommentController extends Controller
{

    /**
     * @param PostCommentService $postCommentService
     * @return \Inertia\Response
     */
    public function index(PostCommentService $postCommentService): Response
    {
        $authUser = Helper::getUserData();
        $comments = $postCommentService->getComments(PostCommentService::PAGINATE_COMMENTS);

        return Inertia::render('PostComment/PostsComments', [
            'authUser' => $authUser,
            'comments' => PostCommentResource::collection($comments->items()),
            'paginate' => PaginateResource::make($comments),
        ]);
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleShow(int $id): JsonResponse
    {
        $comment = PostComment::query()->findOrFail($id);
        $comment->is_show = !$comment->is_show;
        $comment->save();

        return response()->json([
            'message' => 'Видимость сообщения успешно изменена.',
            'comment' => PostCommentResource::make($comment)
        ]);
    }
}
