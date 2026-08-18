<?php

namespace App\Services\Admin\KaraokeUploadLog;

use App\Models\KaraokeUploadLog;
use Illuminate\Pagination\LengthAwarePaginator;

class KaraokeUploadLogService
{
    const PAGINATE = 100;

    public function getLogsPagination(int $paginate, array $filters = []): LengthAwarePaginator
    {
        return KaraokeUploadLog::query()
            ->with(['song', 'user'])
            ->when($filters['exclude_admin'] ?? false, function ($query) {
                $query->whereDoesntHave('user', function ($query) {
                    $query->where('name', 'Admin');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($paginate);
    }
}
