<?php

namespace App\Http\Controllers\App\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\App\Post\PostResource;
use App\Http\Resources\App\Post\PostShowResource;
use App\Http\Resources\PaginateResource;
use App\Models\Post\Post;
use App\Services\App\Post\PostService;
use App\Services\App\Post\PostStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class UserPostController extends Controller
{
    /**
     * @param PostService $postService
     * @return \Inertia\Response
     */
    public function index(
        PostService $postService,
    ): Response
    {
        $posts = $postService->getPostByUserIdPaginate(Auth::id(), PostService::POSTS_PAGINATE);
        $authUser = Helper::getUserData();

        return Inertia::render('UserPost/UserPosts', [
            'authUser' => $authUser,
            'posts' => PostResource::collection($posts->items()),
            'pagination' => PaginateResource::make($posts),
        ]);
    }

    /**
     * @param int $id
     * @param PostStatusService $postStatusService
     * @return Response
     */
    public function edit(int $id, PostStatusService $postStatusService): Response
    {
        $authUser = Helper::getUserData();
        $post = Post::findOrFail($id);
        $statuses = $postStatusService->getCreatePostStatuses();

        return Inertia::render('UserPost/UserPostEdit', [
            'authUser' => $authUser,
            'statuses' => $statuses,
            'post' => PostShowResource::make($post),
            'previousUrl' => url()->previous(),
        ]);
    }

    /**
     * @param int $id
     * @param UpdatePostRequest $request
     * @param \App\Services\App\Post\PostService $postService
     * @return RedirectResponse
     */
    public function update(int $id, UpdatePostRequest $request, PostService $postService): RedirectResponse
    {
        $post = $postService->update($id, $request->all());

        logger()
            ->channel('telegram')
            ->alert('Обновлен пост: ' . route('post.show', ['id' => $post->id]));

        if($request->redirect){
            return redirect($request->redirect);
        }
        return Redirect::route('user.posts');
    }
}
