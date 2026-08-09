<?php

namespace App\Http\Controllers\Admin\KaraokeUploadLog;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\KaraokeUploadLog\KaraokeUploadLogResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\KaraokeUploadLog;
use App\Services\Admin\KaraokeUploadLog\KaraokeUploadLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function showFile(KaraokeUploadLog $log): BinaryFileResponse
    {
        abort_unless($log->file_path && Storage::exists($log->file_path), 404);

        return response()->file(Storage::path($log->file_path), [
            'Content-Type' => $log->file_mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteFile(KaraokeUploadLog $log): RedirectResponse
    {
        if ($log->file_path) {
            Storage::delete($log->file_path);
            $log->update([
                'file_path' => null,
                'file_mime_type' => null,
                'file_size' => null,
            ]);
        }

        return back()->with('success', 'Аудиофайл удалён.');
    }
}
