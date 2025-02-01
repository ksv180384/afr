<?php

namespace App\Http\Controllers\Admin\Post;

use App\Exceptions\Admin\Post\StatusCannotBeDeletedException;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostStatus\CreatePostStatusRequest;
use App\Http\Requests\Admin\PostStatus\UpdatePostStatusRequest;
use App\Http\Resources\Admin\Post\PostStatusResource;
use App\Models\Post\PostStatus;
use App\Services\App\Post\PostStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class PostStatusController extends Controller
{

    /**
     * @param PostStatusService $postStatusService
     * @return Response
     */
    public function index(PostStatusService $postStatusService): Response
    {
        $authUser = Helper::getUserData();
        $postStatuses = $postStatusService->getPostStatuses();

        return Inertia::render('PostStatus/PostStatuses', [
            'authUser' => $authUser,
            'postStatuses' => PostStatusResource::collection($postStatuses),
        ]);
    }

    public function create(): Response
    {
        $authUser = Helper::getUserData();

        return Inertia::render('PostStatus/PostStatusCreate', [
            'authUser' => $authUser,
        ]);
    }

    /**
     * @param CreatePostStatusRequest $request
     * @param PostStatusService $postStatusService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreatePostStatusRequest $request, PostStatusService $postStatusService): RedirectResponse
    {
        $post = $postStatusService->create($request->validated());

        return Redirect::route('admin.post-status.edit', ['id' => $post->id]);
    }

    /**
     * @param int $id
     * @return \Inertia\Response
     */
    public function edit(int $id): Response
    {
        $authUser = Helper::getUserData();
        $postStatus = PostStatus::query()->findOrFail($id);

        return Inertia::render('PostStatus/PostStatusEdit', [
            'authUser' => $authUser,
            'postStatus' => $postStatus,
        ]);
    }

    /**
     * @param int $id
     * @param UpdatePostStatusRequest $request
     * @param PostStatusService $postStatusService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(int $id, UpdatePostStatusRequest $request, PostStatusService $postStatusService): RedirectResponse
    {
        $postStatusService->update($id, $request->validated());

        return Redirect::route('admin.post-status.edit', ['id' => $id]);
    }

    /**
     * @param int $id
     * @param PostStatusService $postStatusService
     * @return RedirectResponse
     */
    public function delete(int $id, PostStatusService $postStatusService): RedirectResponse
    {
        try {
            $postStatusService->delete($id);
            return Redirect::route('admin.posts-statuses');
        }
        catch (StatusCannotBeDeletedException $e){
            return Redirect::back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
