<?php

namespace App\Services\Admin\User;

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
    public function getUsersPagination($paginate, array $filters = []): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $search = $filters['search'] ?? null;

        $allowedSorts = [
            'avatar',
            'name',
            'email',
            'rang',
            'email_verified_at',
            'created_at',
        ];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $users = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.email_verified_at',
                'users.created_at',
                'users.rang_id',
                'users.avatar',
            ])
            ->with(['rang'])
            ->leftJoin('rangs', 'rangs.id', '=', 'users.rang_id')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->when($sort === 'rang', function ($query) use ($direction) {
                $query->orderBy('rangs.title', $direction);
            }, function ($query) use ($sort, $direction) {
                $query->orderBy("users.{$sort}", $direction);
            })
            ->paginate($paginate);

        return $users;
    }

    public function getUser(int $userId): User
    {
        return User::query()
            ->with([
                'gender',
                'rang',
                'infos',
                'config.email',
                'config.facebook',
                'config.skype',
                'config.twitter',
                'config.vk',
                'config.odnoklassniki',
                'config.telegram',
                'config.whatsapp',
                'config.viber',
                'config.instagram',
                'config.youtube',
                'config.info',
                'config.residence',
                'config.sex',
            ])
            ->findOrFail($userId);
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
