<?php

namespace App\Http\Controllers\Admin\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\BanRequest;
use App\Http\Resources\App\User\UserResource;
use App\Http\Resources\PaginateResource;
use App\Services\Admin\User\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(UserService $userService)
    {
        $authUser = Helper::getUserData();
        $users = $userService->getUsersPagination(UserService::USER_PAGINATE);
        $pagination = PaginateResource::make($users);

        return Inertia::render('User/Users', [
            'authUser' => $authUser,
            'users' => UserResource::collection($users->items()),
            'pagination' => $pagination,
        ]);
    }

    public function ban(BanRequest $request,  UserService $userService)
    {
        $banData = $request->validated();
        $user = $userService->ban($banData['id'], $banData['ban']);

        return response()->json(['user' => UserResource::make($user)]);
    }
}
