<?php

namespace App\Services\Admin\UserReferer;

use App\Models\User\User;
use App\Models\UserReferer;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRefererService
{
    const PAGINATE = 100;

    public function getUserReferersPagination(int $paginate): LengthAwarePaginator
    {
        $userReferers = UserReferer::query()
            ->with(['user'])
            ->orderByDesc('created_at')
            ->paginate($paginate);

        return $userReferers;
    }
}
