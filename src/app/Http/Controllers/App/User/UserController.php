<?php

namespace App\Http\Controllers\App\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\User\UserResource;
use App\Http\Resources\App\User\UserShowResource;
use App\Http\Resources\PaginateResource;
use App\Services\App\ProverbService;
use App\Services\App\UserService;
use App\Services\App\WordService;
use Inertia\Inertia;

class UserController extends Controller
{

    const USERS_PAGINATE = 60;

    public function index(
        UserService $userService,
        WordService $wordService,
        ProverbService $proverbService,
    )
    {
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $authUser = Helper::getUserData();
        $users = $userService->getUsersPagination(self::USERS_PAGINATE);
        $pagination = PaginateResource::make($users);

        return Inertia::render('User/Users', [
            'words' => $words,
            'proverb' => $proverb,
            'authUser' => $authUser,
            'users' => UserResource::collection($users->items()),
            'pagination' => $pagination,
        ]);
    }

    public function show(
        int $id,
        UserService $userService,
        WordService $wordService,
        ProverbService $proverbService
    )
    {
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $authUser = Helper::getUserData();
        $user = $userService->getById($id);

//        $user = collect($user->toArray());
//        dump();
//        dd($user->config->fresh()->email);
//        dd($user->toArray());

        return Inertia::render('User/UserShow', [
            'words' => $words,
            'proverb' => $proverb,
            'authUser' => $authUser,
            'user' => UserShowResource::make($user->toArray()),
        ]);
    }
}
