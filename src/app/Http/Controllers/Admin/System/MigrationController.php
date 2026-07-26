<?php

namespace App\Http\Controllers\Admin\System;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MigrationController extends Controller
{
    /**
     * Показывает список выполненных и ожидающих миграций и результат последнего запуска.
     */
    public function index(): Response
    {
        $statusError = null;

        try {
            $migrator = app('migrator');
            $batches = collect($migrator->getRepository()->getMigrationBatches());
            $migrations = collect($migrator->getMigrationFiles(database_path('migrations')))
                ->keys()
                ->map(fn (string $migration) => [
                    'name' => $migration,
                    'status' => $batches->has($migration) ? 'completed' : 'pending',
                    'batch' => $batches->get($migration),
                ])
                ->values();
        } catch (Throwable $exception) {
            $migrations = collect();
            $statusError = $exception->getMessage();
        }

        return Inertia::render('System/Migrations', [
            'authUser' => Helper::getUserData(),
            'migrations' => $migrations,
            'statusError' => $statusError,
            'result' => session('migrationResult'),
        ]);
    }

    /**
     * Запускает миграции с блокировкой параллельных запусков и сохраняет вывод команды в сессии.
     */
    public function run(): RedirectResponse
    {
        $lock = Cache::lock('admin:migrations:run', 300);

        if (! $lock->get()) {
            return redirect()->route('admin.migrations')->with('migrationResult', [
                'success' => false,
                'exit_code' => null,
                'output' => 'Другой процесс миграции уже выполняется.',
                'started_at' => now()->format('d.m.Y H:i:s'),
                'duration_ms' => 0,
            ]);
        }

        $startedAt = now();
        $started = microtime(true);

        try {
            $exitCode = Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $result = [
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()) ?: 'Команда завершилась без текстового вывода.',
                'started_at' => $startedAt->format('d.m.Y H:i:s'),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (Throwable $exception) {
            Log::error('Admin migration run failed', ['exception' => $exception]);

            $result = [
                'success' => false,
                'exit_code' => 1,
                'output' => $exception->getMessage(),
                'started_at' => $startedAt->format('d.m.Y H:i:s'),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } finally {
            $lock->release();
        }

        return redirect()->route('admin.migrations')->with('migrationResult', $result);
    }
}
