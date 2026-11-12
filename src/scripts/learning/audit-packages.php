<?php

// Local CLI only. Force isolation before Laravel can read .env or a cached configuration.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$options = getopt('', ['package:', 'context:', 'map:', 'unit:', 'output-dir:', 'new-material', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/learning/audit-packages.php --package=FILE [--context=EARLIER_FILE ...] [--new-material] [--output-dir=DIR]\n"
        ."   or: php scripts/learning/audit-packages.php --map=MAP --unit=UNIT [--unit=UNIT ...] [--new-material] [--output-dir=DIR]\n"
        ."With --map and no --unit, audit every active package. Reports are editorial warnings, not language approval.\n";
    exit(0);
}
$many = fn ($value): array => $value === null ? [] : (array) $value;
try {
    if (isset($options['map']) === isset($options['package']) || (isset($options['unit']) && ! isset($options['map']))) {
        throw new InvalidArgumentException('Choose --package or --map; --unit requires --map.');
    }
    foreach (['APP_ENV' => 'testing', 'DB_URL' => '', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'APP_CONFIG_CACHE' => sys_get_temp_dir().'/afr-audit-no-config-'.bin2hex(random_bytes(12)).'.php'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    if (isset($options['map'], $options['context'])) {
        throw new InvalidArgumentException('--map derives previous context from the map; do not combine it with --context.');
    }
    $appRoot = dirname(__DIR__, 2);
    require $appRoot.'/vendor/autoload.php';
    require __DIR__.'/PedagogyAudit.php';
    $app = require $appRoot.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.driver' => 'sqlite', 'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync']);
    if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'sqlite'
        || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Audit requires testing and uncached SQLite :memory:.');
    }
    if (Illuminate\Support\Facades\Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('In-memory migrations failed.');
    }
    (new Database\Seeders\Learning\LearningLevelSeeder)->run();
    $validator = app(App\Services\Learning\SessionPackageValidator::class);
    $resolve = function (string $path): string {
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new InvalidArgumentException('Unreadable input: '.$path);
        }

        return $real;
    };
    $jobs = [];
    if (isset($options['map'])) {
        $mapPath = $resolve($options['map']);
        $map = json_decode(file_get_contents($mapPath), true, 512, JSON_THROW_ON_ERROR);
        $requested = $many($options['unit'] ?? null);
        $found = [];
        foreach ($map['levels'] as $level) {
            $units = $level['units'];
            usort($units, fn ($a, $b) => $a['order'] <=> $b['order']);
            foreach ($units as $index => $unit) {
                if ($requested === [] || in_array($unit['id'], $requested, true)) {
                    $paths = array_map(fn ($u) => $resolve(dirname($mapPath).'/'.$u['package_path']), array_slice($units, 0, $index + 1));
                    $path = array_pop($paths);
                    $jobs[] = ['path' => $path, 'context' => $paths];
                    $found[] = $unit['id'];
                }
            }
        }
        if (array_diff($requested, $found)) {
            throw new InvalidArgumentException('Unknown map unit: '.implode(', ', array_diff($requested, $found)));
        }
    } else {
        foreach ($many($options['package']) as $path) {
            $jobs[] = ['path' => $resolve($path), 'context' => array_map($resolve, $many($options['context'] ?? null))];
        }
    }
    if ($jobs === []) {
        throw new InvalidArgumentException('No input packages selected.');
    }
    $directory = $options['output-dir'] ?? config('daily_practice.content_path').'/programme/editorial/improvement';
    if (preg_match('~(^|[/\\\\])packages([/\\\\]|$)~', $directory)) {
        throw new InvalidArgumentException('Reports must be outside importable packages; use editorial/improvement.');
    }
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Cannot create report directory.');
    }
    $directory = realpath($directory);
    if (preg_match('~(^|[/\\\\])packages([/\\\\]|$)~', $directory)) {
        throw new InvalidArgumentException('Reports must be outside importable packages; use editorial/improvement.');
    }
    $inputs = [];
    foreach ($jobs as $job) {
        foreach ([$job['path'], ...$job['context']] as $input) {
            $inputs[$input] = hash_file('sha256', $input);
        }
    }
    if (isset($mapPath)) {
        $inputs[$mapPath] = hash_file('sha256', $mapPath);
    }
    $destinations = [];
    foreach ($jobs as $job) {
        $output = $directory.'/'.pathinfo($job['path'], PATHINFO_FILENAME).'.pedagogy.json';
        if (isset($destinations[$output]) || isset($inputs[$output]) || (is_link($output) && isset($inputs[realpath($output)]))) {
            throw new InvalidArgumentException('Report path would collide with an input or another report: '.$output);
        }
        $destinations[$output] = $job;
    }
    $seed = function (array $package): void {
        foreach ($package['units'] ?? [] as $content) {
            App\Models\Learning\LearningUnit::updateOrCreate(['external_id' => $content['id']], [
                'level' => $content['level'], 'slug' => $content['slug'], 'sort_order' => $content['order'], 'title' => $content['title'],
                'summary' => $content['summary'], 'learning_outcomes' => $content['learning_outcomes'], 'published_at' => now('UTC'),
            ]);
        }
        foreach ($package['sessions'] as $content) {
            $unit = App\Models\Learning\LearningUnit::where('external_id', $content['unit_id'])->firstOrFail();
            foreach ($content['objectives'] as $objective) {
                App\Models\Learning\LearningObjective::firstOrCreate(['stable_id' => $objective['id']], ['level' => $content['level'], 'title' => $objective['title']]);
            }
            $session = App\Models\Learning\LearningSession::updateOrCreate(['external_id' => $content['id']], [
                'level' => $content['level'], 'unit_id' => $unit->id, 'sort_order' => $content['order'],
            ]);
            $version = App\Models\Learning\LearningSessionVersion::create([
                'session_id' => $session->id, 'version' => $content['version'], 'schema_version' => $package['schema_version'], 'payload' => $content,
                'checksum' => App\Services\Learning\Validation\CanonicalJson::hash($content), 'status' => 'published', 'published_at' => now('UTC'),
            ]);
            $session->update(['active_version_id' => $version->id]);
        }
        foreach ($package['curricula'] ?? [] as $content) {
            App\Models\Learning\LearningCurriculum::firstOrCreate(['external_id' => $content['id'], 'version' => $content['version']], [
                'level' => $content['level'], 'required_objective_ids' => $content['required_objective_ids'],
                'checkpoint_session_ids' => $content['checkpoint_session_ids'], 'checksum' => App\Services\Learning\Validation\CanonicalJson::hash($content), 'status' => 'draft',
            ]);
        }
    };
    $exitCode = 0;
    foreach ($destinations as $output => $job) {
        if (in_array($job['path'], $job['context'], true)) {
            throw new InvalidArgumentException('Target package cannot be its own previous context.');
        }
        // A transaction per target prevents one target or its future content leaking into another audit.
        Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $previous = [];
            foreach (array_unique($job['context']) as $path) {
                $context = $validator->validateFile($path);
                if (! $context->isValid()) {
                    throw new RuntimeException('Invalid or incomplete previous context '.$path.': '.json_encode($context->toArray()['errors'], JSON_UNESCAPED_UNICODE));
                }
                $previous[] = $context->package();
                $seed($context->package());
            }
            $canonical = $validator->validateFile($job['path']);
            $pedagogy = $canonical->isValid()
                ? (new PracticeAudit\PedagogyAudit)->analyze($canonical->package(), $previous, isset($options['new-material']))
                : ['status' => 'not_run_invalid_package', 'language_quality' => 'not_assessed', 'warning_count' => 0, 'warnings' => []];
            $report = ['report_version' => 1, 'tool' => 'LP-03 local package audit', 'source' => $job['path'], 'sha256' => $inputs[$job['path']],
                'context' => array_map(fn ($path) => ['source' => $path, 'sha256' => $inputs[$path]], array_unique($job['context'])),
                'isolation' => ['environment' => $app->environment(), 'database' => ':memory:', 'connection' => 'sqlite', 'configuration_cached' => false, 'production_checked' => false],
                'canonical' => $canonical->toArray(), 'pedagogy' => $pedagogy];
            foreach ($inputs as $path => $hash) {
                if (hash_file('sha256', $path) !== $hash) {
                    throw new RuntimeException('Input changed during analysis: '.$path);
                }
            }
            $temporary = tempnam($directory, '.audit-');
            try {
                if (file_put_contents($temporary, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL) === false || ! rename($temporary, $output)) {
                    throw new RuntimeException('Cannot write report: '.$output);
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            echo json_encode(['report' => $output, 'canonical_valid' => $canonical->isValid(), 'pedagogical_warnings' => $pedagogy['warning_count']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
            if (! $canonical->isValid()) {
                $exitCode = 1;
            }
        } finally {
            Illuminate\Support\Facades\DB::rollBack();
        }
    }
    exit($exitCode);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['audit_error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL);
    exit(2);
}
