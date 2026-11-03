<?php

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_starts_with(config('database.connections.mysql.database'), 'afr_stage05_')) {
    exit(80);
}
config(['filesystems.disks.learning_private.root' => sys_get_temp_dir().'/afr-stage05-content/'.getenv('DB_DATABASE')]);
$job = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
while (microtime(true) < (float) $argv[2]) {
    usleep(1000);
}
try {
    $actor = \App\Models\User\User::findOrFail($job['actor_id']);
    $result = match ($job['operation']) {
        'commit' => app(\App\Services\Learning\SessionImportService::class)->commit($actor, $job['batch_id'], $job['token'], $job['file_hash']),
        'publish' => ['version_id' => app(\App\Services\Learning\SessionPublicationService::class)->publish($actor, $job['version_id'])->id],
        'answer' => app(\App\Services\Learning\RunAnswerService::class)->accept($actor, $job['run_id'], $job['input']),
        'guest_sync' => app(\App\Services\Learning\GuestSyncService::class)->sync($actor, $job['input']),
        'account_start' => app(\App\Services\Learning\AccountRunService::class)->start($actor, $job['input']),
        'account_finish' => app(\App\Services\Learning\AccountRunService::class)->command($actor, $job['run_id'], 'finish', $job['input']),
        'account_answer' => app(\App\Services\Learning\AccountRunService::class)->event($actor, $job['run_id'], 'answer', $job['input']),
        default => throw new RuntimeException('Unknown race operation'),
    };
    echo json_encode(['ok' => true, 'receipt' => $result], JSON_THROW_ON_ERROR);
} catch (\App\Services\Learning\ContentOperationException|\App\Services\Learning\AnswerCheckingException $exception) {
    echo json_encode(['ok' => false, 'code' => $exception->errorCode->value], JSON_THROW_ON_ERROR);
}
