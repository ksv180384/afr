<?php

use Symfony\Component\Process\Process;

function runPedagogyCli(array|string $package, array $environment = []): array
{
    $directory = sys_get_temp_dir().'/afr-pedagogy-'.bin2hex(random_bytes(8));
    mkdir($directory, 0775, true);
    $input = $directory.'/input.json';
    file_put_contents($input, is_string($package) ? $package : json_encode($package, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $hash = hash_file('sha256', $input);
    $process = new Process([PHP_BINARY, base_path('scripts/learning/audit-packages.php'), '--package='.$input, '--new-material', '--output-dir='.$directory.'/improvement'], base_path(), $environment);
    $process->setTimeout(60);
    $process->run();
    $report = $directory.'/improvement/input.pedagogy.json';
    expect(is_file($report), $process->getErrorOutput())->toBeTrue();
    expect(hash_file('sha256', $input))->toBe($hash);

    return [$process, json_decode(file_get_contents($report), true, 512, JSON_THROW_ON_ERROR)];
}

it('forces an isolated CLI even with production database variables and an existing config cache', function () {
    $cache = tempnam(sys_get_temp_dir(), 'afr-poisoned-config-');
    file_put_contents($cache, "<?php throw new RuntimeException('Working config must not be read');");
    $database = tempnam(sys_get_temp_dir(), 'afr-working-db-');
    file_put_contents($database, 'working database sentinel');
    $before = hash_file('sha256', $database);
    $package = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true);
    [$process, $report] = runPedagogyCli($package, ['APP_ENV' => 'production', 'DB_CONNECTION' => 'mysql', 'DB_URL' => 'mysql://invalid:invalid@127.0.0.1:1/production', 'DB_DATABASE' => $database, 'APP_CONFIG_CACHE' => $cache]);
    expect($process->getExitCode())->toBe(0)->and($report['canonical']['valid'])->toBeTrue()
        ->and($report['isolation'])->toMatchArray(['environment' => 'testing', 'connection' => 'sqlite', 'database' => ':memory:', 'configuration_cached' => false])
        ->and(hash_file('sha256', $database))->toBe($before);
});

it('uses canonical validation for bad objectives vocabulary introductions pairs and token orders', function () {
    $package = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true);
    $package['sessions'][0]['tasks'][0]['objective_ids'] = ['UNKNOWN'];
    $package['sessions'][0]['tasks'][0]['vocabulary_ids'] = ['UNKNOWN'];
    $package['sessions'][0]['introductions'][0]['before_task_id'] = 'UNKNOWN';
    $package['sessions'][0]['tasks'][1]['correct_pairs'][0]['right_id'] = 'UNKNOWN';
    array_pop($package['sessions'][0]['tasks'][5]['accepted_orders'][0]);
    [$process, $report] = runPedagogyCli($package);
    $paths = array_column($report['canonical']['errors'], 'path');
    expect($process->getExitCode())->toBe(1)->and($report['canonical']['valid'])->toBeFalse()
        ->and($report['pedagogy']['status'])->toBe('not_run_invalid_package');
    foreach (['/sessions/0/tasks/0/objective_ids/0', '/sessions/0/tasks/0/vocabulary_ids/0', '/sessions/0/introductions/0/before_task_id', '/sessions/0/tasks/5/accepted_orders/0'] as $path) {
        expect($paths)->toContain($path);
    }
    expect(array_column($report['canonical']['errors'], 'code'))->toContain('invalid_matching_pairs');
});

it('reports missing transcription using the actual schema instead of accepting decoded JSON', function () {
    $package = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true);
    unset($package['sessions'][0]['tasks'][0]['prompt']['audio']['transcription_ru']);
    [$process, $report] = runPedagogyCli($package);
    expect($process->getExitCode())->toBe(1)
        ->and(array_column($report['canonical']['errors'], 'code'))->toContain('transcription_required');
});

it('writes a canonical invalid-JSON report instead of treating decoding as validation', function () {
    [$process, $report] = runPedagogyCli('{');
    expect($process->getExitCode())->toBe(1)->and($report['canonical']['valid'])->toBeFalse()
        ->and($report['canonical']['errors'])->not->toBeEmpty()
        ->and($report['pedagogy']['status'])->toBe('not_run_invalid_package');
});

it('selects only a requested map topic and does not read missing future packages', function () {
    $directory = sys_get_temp_dir().'/afr-audit-map-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $input = $directory.'/topic.json';
    copy(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json'), $input);
    $map = $directory.'/map.json';
    file_put_contents($map, json_encode(['levels' => [['code' => 'PRE_A1', 'units' => [
        ['id' => 'PRE_A1-U01', 'order' => 1, 'package_path' => 'topic.json'],
        ['id' => 'PRE_A1-U02', 'order' => 2, 'package_path' => 'missing-future.json'],
    ]]]], JSON_THROW_ON_ERROR));
    $before = hash_file('sha256', $input);
    $process = new Process([PHP_BINARY, base_path('scripts/learning/audit-packages.php'), '--map='.$map, '--unit=PRE_A1-U01', '--output-dir='.$directory.'/improvement'], base_path());
    $process->setTimeout(60);
    $process->run();
    expect($process->getExitCode(), $process->getErrorOutput())->toBe(0);
    $report = json_decode(file_get_contents($directory.'/improvement/topic.pedagogy.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($report['context'])->toBe([])->and($report['canonical']['valid'])->toBeTrue()->and(hash_file('sha256', $input))->toBe($before);
});

it('refuses to place reports among importable packages', function () {
    $directory = sys_get_temp_dir().'/afr-audit-output-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $process = new Process([PHP_BINARY, base_path('scripts/learning/audit-packages.php'), '--package='.base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json'), '--output-dir='.$directory.'/packages'], base_path());
    $process->setTimeout(60);
    $process->run();
    expect($process->getExitCode())->toBe(2)->and($process->getErrorOutput())->toContain('outside importable packages')
        ->and(is_dir($directory.'/packages'))->toBeFalse();
});

it('loads preceding lessons using current lesson storage when validating a dependent package', function () {
    $directory = sys_get_temp_dir().'/afr-audit-context-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $package = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true);
    $before = [...$package, 'sessions' => [$package['sessions'][0]]];
    $before['sessions'][0]['public_page']['related_session_ids'] = [];
    $after = [...$package, 'sessions' => [$package['sessions'][1]]];
    file_put_contents($directory.'/before.json', json_encode($before, JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/after.json', json_encode($after, JSON_THROW_ON_ERROR));
    $process = new Process([PHP_BINARY, base_path('scripts/learning/audit-packages.php'),
        '--package='.$directory.'/after.json', '--context='.$directory.'/before.json',
        '--output-dir='.$directory.'/reports'], base_path());
    $process->setTimeout(60);
    $process->run();
    expect($process->getExitCode(), $process->getErrorOutput())->toBe(0);
    $report = json_decode(file_get_contents($directory.'/reports/after.pedagogy.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($report['canonical']['valid'])->toBeTrue()->and($report['context'])->toHaveCount(1);
});
