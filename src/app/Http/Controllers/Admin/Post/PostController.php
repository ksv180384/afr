<?php

namespace App\Http\Controllers\Admin\Post;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Post\UpdatePostStatusRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\Post\PostResource;
use App\Services\Admin\Post\PostService;
use App\Services\App\Post\PostStatusService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * @param PostService $postService
     * @param PostStatusService $postStatusService
     * @return \Inertia\Response
     */
    public function index(PostService $postService, PostStatusService $postStatusService): Response
    {
        $authUser = Helper::getUserData();
        $posts = $postService->getPostPaginate(PostService::POSTS_PAGINATE);
        $postStatuses = $postStatusService->getPostStatuses();

        return Inertia::render('Post/Posts', [
            'authUser' => $authUser,
            'postStatuses' => $postStatuses,
            'posts' => PostResource::collection($posts->items()),
            'paginate' => PaginateResource::make($posts),
        ]);
    }

    public function show(int $id)
    {

    }

    /**
     * @param int $id
     * @param UpdatePostRequest $request
     * @param PostService $postService
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(int $id, UpdatePostStatusRequest $request, PostService $postService): JsonResponse
    {
        $post = $postService->update($id, $request->all());

//        logger()
//            ->channel('telegram')
//            ->alert('В админке, обновлен статус поста: ' . route('post.show', ['id' => $post->id]));


        return response()->json(['message' => 'Статус поста успешно обновлен']);
    }
}
