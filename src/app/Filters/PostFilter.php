<?php

namespace App\Filters;

use App\Models\Post\PostStatus;
use Illuminate\Database\Eloquent\Builder;

class PostFilter extends Filter
{
    /**
     * Фильтрация по статусу
     *
     * @param string $value
     * @return Builder
     */
    protected function status(string $value): Builder
    {
        $postStatus = PostStatus::query()->where('alias', $value)->first();

        return $this->builder->where('status_id', $postStatus->id);
    }
}
