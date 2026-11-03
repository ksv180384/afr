<?php

namespace App\Console\Commands;

use App\Services\Learning\SessionImportService;
use Illuminate\Console\Command;

class PruneSessionPreviews extends Command
{
    protected $signature = 'daily-practice:prune-previews';

    protected $description = 'Удалить просроченные приватные исходники импорта, сохранив квитанции и историю';

    public function handle(SessionImportService $service, \App\Services\Learning\PracticeTelemetry $telemetry): int
    {
        try {
            $deleted = $service->pruneExpired();
            $telemetry->record('preview_cleanup', ['status' => 200, 'deleted_files' => $deleted]);
            $this->info('Удалено файлов: '.$deleted);
        } catch (\Throwable $error) {
            $telemetry->record('preview_cleanup_failure', ['status' => 500, 'error_class' => $error::class]);
            throw $error;
        }

        return self::SUCCESS;
    }
}
