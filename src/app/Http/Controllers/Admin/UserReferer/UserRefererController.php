<?php

namespace App\Http\Controllers\Admin\UserReferer;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserReferer\UserRefererResource;
use App\Http\Resources\PaginateResource;
use App\Services\Admin\UserReferer\UserRefererService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserRefererController extends Controller
{
    public function index(UserRefererService $refererService)
    {
        $authUser = Helper::getUserData();
        $userReferers = $refererService->getUserReferersPagination(UserRefererService::PAGINATE);
        $pagination = PaginateResource::make($userReferers);

        return Inertia::render('UserReferer/UserReferer', [
            'authUser' => $authUser,
            'userReferers' => UserRefererResource::collection($userReferers->items()),
            'pagination' => $pagination,
        ]);
    }
}
