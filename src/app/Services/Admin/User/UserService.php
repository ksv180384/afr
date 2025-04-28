<?php

namespace App\Services\Admin\User;

use App\Http\Requests\Admin\User\BanRequest;
use App\Models\User\Rang;
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

    public function ban(int $userId, bool $ban): User
    {
        if ($ban){
            $rang = Rang::query()->where('alias', 'zabanen')->first();
        }
        else{
            $rang = Rang::query()->where('alias', 'polzovatel')->first();
        }

        $user = User::query()->findOrFail($userId);
        $user->rang_id = $rang->id;
        $user->save();

        return $user;
    }
}
