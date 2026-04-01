<?php

namespace App\Http\Controllers\Admin\KaraokeUploadLog;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\KaraokeUploadLog\KaraokeUploadLogResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Services\Admin\KaraokeUploadLog\KaraokeUploadLogService;
use Inertia\Inertia;

class KaraokeUploadLogController extends Controller
{
    public function index(KaraokeUploadLogService $karaokeUploadLogService)
    {
        $authUser = Helper::getUserData();
        $logs = $karaokeUploadLogService->getLogsPagination(KaraokeUploadLogService::PAGINATE);
        $pagination = PaginateResource::make($logs);

        return Inertia::render('KaraokeUploadLog/KaraokeUploadLog', [
            'authUser' => $authUser,
            'logs' => KaraokeUploadLogResource::collection($logs->items()),
            'pagination' => $pagination,
        ]);
    }
}
