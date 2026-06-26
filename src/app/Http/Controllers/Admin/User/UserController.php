<?php

namespace App\Http\Controllers\Admin\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\BanRequest;
use App\Http\Requests\Admin\User\ToggleEmailVerificationRequest;
use App\Http\Resources\Admin\User\UserResource;
use App\Http\Resources\Admin\User\UserShowResource;
use App\Http\Resources\PaginateResource;
use App\Services\Admin\User\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request, UserService $userService)
    {
        $authUser = Helper::getUserData();
        $filters = $request->only(['search', 'sort', 'direction']);
        $users = $userService->getUsersPagination(UserService::USER_PAGINATE, $filters);
        $pagination = PaginateResource::make($users);

        return Inertia::render('User/Users', [
            'authUser' => $authUser,
            'users' => UserResource::collection($users->items()),
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    public function show(int $id, UserService $userService)
    {
        $authUser = Helper::getUserData();
        $user = $userService->getUser($id);

        return Inertia::render('User/UserShow', [
            'authUser' => $authUser,
            'user' => UserShowResource::make($user),
        ]);
    }

    public function ban(BanRequest $request,  UserService $userService)
    {
        $banData = $request->validated();
        $user = $userService->ban($banData['id'], $banData['ban']);

        return response()->json(['user' => UserResource::make($user)]);
    }

    public function toggleEmailVerification(ToggleEmailVerificationRequest $request, UserService $userService)
    {
        $verificationData = $request->validated();
        $user = $userService->toggleEmailVerification(
            $verificationData['id'],
            $verificationData['verified'],
        );

        return response()->json(['user' => UserShowResource::make($user)]);
    }
}
