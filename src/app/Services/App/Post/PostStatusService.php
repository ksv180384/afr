<?php

namespace App\Services\App\Post;

use App\Exceptions\Admin\Post\StatusCannotBeDeletedException;
use App\Models\Post\PostStatus;
use Illuminate\Database\Eloquent\Collection;

class PostStatusService
{
    /**
     * @return Collection
     */
    public function getCreatePostStatuses(): Collection
    {
        $postStatuses = PostStatus::query()
            ->where('for_create', true)
            ->get(['id', 'title', 'alias']);

        return $postStatuses;
    }

    /**
     * @return Collection
     */
    public function getPostStatuses(): Collection
    {
        $postStatuses = PostStatus::query()
            ->get(['id', 'title', 'alias']);

        return $postStatuses;
    }

    /**
     * @param array $postData
     * @return PostStatus
     */
    public function create(array $postData): PostStatus
    {
        $postStatus = PostStatus::query()->create($postData);

        return $postStatus;
    }

    /**
     * @param int $id
     * @param array $postData
     * @return PostStatus
     */
    public function update(int $id, array $postData): PostStatus
    {
        $postStatus = PostStatus::query()->findOrFail($id);

        $postStatus->update($postData);

        return $postStatus;
    }

    /**
     * @param int $id
     * @return void
     */
    public function delete(int $id): void
    {
        $postStatus = PostStatus::query()->findOrFail($id);

        // Проверить, используется ли статус
        if ($postStatus->posts()->exists()) {
            throw new StatusCannotBeDeletedException();
        }

        $postStatus->delete();
    }
}
