<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\CreatePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\App\Post\PostShowResource;
use App\Http\Resources\App\PostComment\PostShowCommentResource;
use App\Models\Post\Post;
use App\Services\App\Post\PostService;
use App\Services\App\Post\PostStatusService;
use App\Services\App\PostComment\PostCommentService;
use App\Services\App\ProverbService;
use App\Services\App\WordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
/*
    public function index(PostService $postService)
    {
        $posts = $postService->getPostPaginate(PostService::POSTS_PAGINATE);

        $paginate = PaginateResource::make($posts);
    }
*/
    /**
     * @param PostStatusService $postStatusService
     * @return \Inertia\Response
     */
    public function create(PostStatusService $postStatusService): Response
    {
        $authUser = Helper::getUserData();
        $statuses = $postStatusService->getCreatePostStatuses();

        return Inertia::render('Post/PostCreate', [
            'authUser' => $authUser,
            'statuses' => $statuses,
        ]);
    }

    /**
     * @param CreatePostRequest $request
     * @param PostService $postService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreatePostRequest $request, PostService $postService): RedirectResponse
    {
        $post = $postService->create($request->all());

        logger()
            ->channel('telegram')
            ->alert('Добавлен новый пост: ' . route('post.show', ['id' => $post->id]));

        return Redirect::route('user.posts');
    }

    /**
     * @param int $id
     * @param PostStatusService $postStatusService
     * @return Response
     *
     */
    public function edit(int $id, PostStatusService $postStatusService): Response
    {
        $authUser = Helper::getUserData();
        $post = Post::findOrFail($id);
        $statuses = $postStatusService->getCreatePostStatuses();

        return Inertia::render('Post/PostEdit', [
            'authUser' => $authUser,
            'statuses' => $statuses,
            'post' => PostShowResource::make($post),
        ]);
    }

    /**
     * @param int $id
     * @param UpdatePostRequest $request
     * @param PostService $postService
     * @return RedirectResponse
     */
    public function update(int $id, UpdatePostRequest $request, PostService $postService): RedirectResponse
    {
        $post = $postService->update($id, $request->all());

        logger()
            ->channel('telegram')
            ->alert('Обновлен пост: ' . route('post.show', ['id' => $post->id]));

        return Redirect::route('user.posts');
    }

    /**
     * @param int $id
     * @param WordService $wordService
     * @param ProverbService $proverbService
     * @return Response
     */
    public function show(
        int $id,
        WordService $wordService,
        ProverbService $proverbService,
        PostService $postService,
        PostCommentService $postCommentService
    ): Response
    {
        $authUser = Helper::getUserData();
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $post = $postService->getPostByIdAvailable($id);

        $comments = $postCommentService->getPostComments($post->id);
        $postService->markViewedPost($post->id);

        return Inertia::render('Post/PostShow', [
            'authUser' => $authUser,
            'words' => $words,
            'proverb' => $proverb,
            'post' => PostShowResource::make($post),
            'comments' => PostShowCommentResource::collection($comments),
        ]);
    }
}
