<?php

namespace App\Http\Controllers\Admin\SongComment;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SongComment\SongCommentResource;
use App\Http\Resources\PaginateResource;
use App\Models\Player\PlayerSongComment;
use App\Services\Admin\SongComment\SongCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SongCommentController extends Controller
{
    public function index(SongCommentService $songCommentService)
    {
        $authUser = Helper::getUserData();
        $comments = $songCommentService->getSongComments(SongCommentService::PAGINATION);

        return Inertia::render('SongComment/SongComments', [
            'authUser' => $authUser,
            'comments' => SongCommentResource::collection($comments->items()),
            'pagination' => PaginateResource::make($comments),
        ]);
    }

    public function toggleShow(int $id): JsonResponse
    {
        $comment = PlayerSongComment::query()->findOrFail($id);
        $comment->is_hidden = !$comment->is_hidden;
        $comment->save();

        return response()->json([
            'message' => 'Видимость сообщения успешно изменена.',
            'comment' => SongCommentResource::make($comment)
        ]);
    }
}
