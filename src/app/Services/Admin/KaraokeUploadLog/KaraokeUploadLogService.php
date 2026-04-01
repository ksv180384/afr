<?php

namespace App\Services\Admin\KaraokeUploadLog;

use App\Models\KaraokeUploadLog;
use Illuminate\Pagination\LengthAwarePaginator;

class KaraokeUploadLogService
{
    const PAGINATE = 100;

    public function getLogsPagination(int $paginate): LengthAwarePaginator
    {
        return KaraokeUploadLog::query()
            ->with(['song', 'user'])
            ->orderByDesc('created_at')
            ->paginate($paginate);
    }
}
