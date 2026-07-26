<?php

use App\Http\Controllers\Admin\System\MigrationController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

test('migration controller stores command output in the session', function () {
    $lock = Mockery::mock();
    $lock->shouldReceive('get')->once()->andReturnTrue();
    $lock->shouldReceive('release')->once();

    Cache::shouldReceive('lock')
        ->once()
        ->with('admin:migrations:run', 300)
        ->andReturn($lock);
    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true, '--no-interaction' => true])
        ->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn('INFO  Nothing to migrate.');

    $response = app(MigrationController::class)->run();
    $result = session('migrationResult');

    expect($response->getTargetUrl())->toBe(route('admin.migrations'))
        ->and($result['success'])->toBeTrue()
        ->and($result['exit_code'])->toBe(0)
        ->and($result['output'])->toContain('Nothing to migrate');
});
