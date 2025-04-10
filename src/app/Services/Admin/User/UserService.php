<?php

namespace App\Services\Admin\User;

use App\Models\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    const USER_PAGINATE = 40;

    /**
     * @param $paginate
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUsersPagination($paginate): LengthAwarePaginator
    {
        $users = User::query()
            ->select([
                'id',
                'name',
                'rang_id',
                'avatar',
            ])
            ->with(['gender', 'rang'])
            ->paginate($paginate);

        return $users;
    }
}
