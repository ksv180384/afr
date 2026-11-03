<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class FileOnlyHostingProbe implements ShouldQueue
{
    public function handle(): void
    {
        Cache::put('file-only-hosting-probe', 'done');
    }
}

it('executes queued work before returning without creating a database job', function () {
    expect(config('queue.default'))->toBe('sync');
    Cache::forget('file-only-hosting-probe');
    Bus::dispatch(new FileOnlyHostingProbe);
    expect(Cache::get('file-only-hosting-probe'))->toBe('done');
    $this->assertDatabaseCount('jobs', 0);
});

it('overrides an older cached database queue configuration when providers register', function () {
    config(['queue.default' => 'database']);
    (new App\Providers\AppServiceProvider(app()))->register();
    Cache::forget('file-only-hosting-probe');
    Bus::dispatch(new FileOnlyHostingProbe);
    expect(Cache::get('file-only-hosting-probe'))->toBe('done');
    $this->assertDatabaseCount('jobs', 0);
});
