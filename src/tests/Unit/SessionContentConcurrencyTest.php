<?php

use App\Models\Learning\LearningEditorialEvent;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\CatalogPublicationService;
use App\Services\Learning\ContentAccess;
use App\Services\Learning\SessionImportService;
use App\Services\Learning\SessionPublicationService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;

if (getenv('PRACTICE_CONCURRENCY') !== '1') {
    it('requires an explicit dedicated MariaDB for content concurrency tests')->skip('Opt in with PRACTICE_CONCURRENCY=1 and a fresh afr_stage05_* database.');

    return;
}
if (getenv('DB_CONNECTION') !== 'mysql' || ! str_starts_with(getenv('DB_DATABASE') ?: '', 'afr_stage05_') || is_file(getenv('APP_CONFIG_CACHE') ?: dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Concurrency tests require an isolated afr_stage05_* MySQL database and uncached configuration.');
}
putenv('DB_URL=');
$_ENV['DB_URL'] = '';
$_SERVER['DB_URL'] = '';
uses(Tests\TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('mysql');
    expect(config('database.connections.mysql.database'))->toStartWith('afr_stage05_');
    config(['filesystems.disks.learning_private.root' => sys_get_temp_dir().'/afr-stage05-content/'.getenv('DB_DATABASE')]);
    Artisan::call('migrate:fresh', ['--force' => true]);
    $this->seed(LearningLevelSeeder::class);
    \App\Models\User\Gender::create(['title' => 'Не указан']);
    Rang::create(['alias' => 'polzovatel', 'title' => 'Пользователь']);
    \App\Models\User\UserConfigsView::create(['alias' => \App\Models\User\UserConfigsView::NE_POKAZYVAT_NIKOMU, 'title' => 'Не показывать никому']);
    $rank = Rang::create(['alias' => 'administrator', 'title' => 'Администратор']);
    $this->actor = User::factory()->create(['rang_id' => $rank->id]);
});

afterEach(function (): void {
    \Illuminate\Support\Facades\Storage::disk('learning_private')->deleteDirectory('previews');
});

function racePackage(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true);
}

function racePreview(User $actor, array $data): array
{
    return app(SessionImportService::class)->preview($actor, UploadedFile::fake()->createWithContent('race.json', CanonicalJson::encode($data)));
}

function raceWorkers(User $actor, array $jobs): array
{
    $processes = [];
    $files = [];
    $startAt = microtime(true) + 1;
    try {
        foreach ($jobs as $job) {
            $file = tempnam(sys_get_temp_dir(), 'afr-stage05-race-');
            chmod($file, 0600);
            file_put_contents($file, json_encode(['actor_id' => $actor->id, ...$job], JSON_THROW_ON_ERROR));
            $files[] = $file;
            $pipes = [];
            $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/Learning/content-worker.php'), $file, (string) $startAt], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to launch race worker');
            }
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($process), $err)->toBe(0);
            $results[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

it('serializes duplicate commits on the same token and returns the same receipt', function () {
    $preview = racePreview($this->actor, racePackage());
    $results = raceWorkers($this->actor, [['operation' => 'commit', ...$preview], ['operation' => 'commit', ...$preview]]);
    expect($results[0]['ok'])->toBeTrue()->and($results[1])->toBe($results[0])
        ->and(LearningSessionVersion::count())->toBe(2)
        ->and(LearningEditorialEvent::where('operation', 'import_commit')->count())->toBe(1);
});

it('serializes distinct tokens for identical versions without creating duplicates', function () {
    $first = racePreview($this->actor, racePackage());
    $second = racePreview($this->actor, racePackage());
    $results = raceWorkers($this->actor, [['operation' => 'commit', ...$first], ['operation' => 'commit', ...$second]]);
    expect(array_column($results, 'ok'))->toBe([true, true])
        ->and(array_sum(array_map(fn (array $result): int => count($result['receipt']['created_version_ids']), $results)))->toBe(2)
        ->and(array_sum(array_map(fn (array $result): int => count($result['receipt']['skipped_version_ids']), $results)))->toBe(2)
        ->and(LearningSessionVersion::count())->toBe(2);
});

it('atomically rejects one of two concurrent conflicting versions', function () {
    $first = racePreview($this->actor, racePackage());
    $data = racePackage();
    $data['sessions'][1]['title'] .= ' другая редакция';
    $second = racePreview($this->actor, $data);
    $results = raceWorkers($this->actor, [['operation' => 'commit', ...$first], ['operation' => 'commit', ...$second]]);
    expect(count(array_filter($results, fn (array $result): bool => $result['ok'])))->toBe(1)
        ->and(collect($results)->first(fn (array $result): bool => ! $result['ok'])['code'])->toBe('version_conflict')
        ->and(LearningSessionVersion::count())->toBe(2)
        ->and(\App\Models\Learning\LearningCatalogRevision::count())->toBe(2);
});

it('serializes competing publications and preserves one active version and old run snapshots', function () {
    $preview = racePreview($this->actor, racePackage());
    $receipt = app(SessionImportService::class)->commit($this->actor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($this->actor, $id, $checks);
        app(CatalogPublicationService::class)->publish($this->actor, $id);
    }
    foreach ($receipt['created_version_ids'] as $id) {
        app(SessionPublicationService::class)->review($this->actor, $id, $checks);
    }
    app(SessionPublicationService::class)->publishMany($this->actor, $receipt['created_version_ids']);
    $old = LearningSessionVersion::find($receipt['created_version_ids'][0]);
    $run = \App\Models\Learning\LearningRun::factory()->create(['user_id' => $this->actor->id, 'session_version_id' => $old->id]);
    $jobs = [];
    foreach ([2, 3] as $number) {
        $data = racePackage();
        unset($data['levels'], $data['units']);
        $data['sessions'] = [$data['sessions'][0]];
        $data['sessions'][0]['version'] = $number;
        $data['sessions'][0]['title'] .= ' '.$number;
        $preview = racePreview($this->actor, $data);
        $receipt = app(SessionImportService::class)->commit($this->actor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
        $id = $receipt['created_version_ids'][0];
        app(SessionPublicationService::class)->review($this->actor, $id, $checks);
        $jobs[] = ['operation' => 'publish', 'version_id' => $id];
    }
    $results = raceWorkers($this->actor, $jobs);
    expect(array_column($results, 'ok'))->toBe([true, true])
        ->and($old->session->versions()->where('status', 'published')->count())->toBe(1)
        ->and($run->refresh()->session_version_id)->toBe($old->id)
        ->and(LearningSession::find($old->session_id)->active_version_id)->toBeIn(array_column($jobs, 'version_id'));
});

function raceAnswerRun(User $actor): array
{
    $snapshot = racePackage()['sessions'][0];
    $version = LearningSessionVersion::factory()->create(['payload' => $snapshot]);
    $run = \App\Models\Learning\LearningRun::factory()->create(['user_id' => $actor->id, 'session_version_id' => $version->id]);
    $task = $snapshot['tasks'][0];
    $presentation = \App\Models\Learning\LearningPresentation::factory()->create([
        'run_id' => $run->id, 'task_id' => $task['id'],
        'snapshot' => \App\Services\Learning\PresentationSnapshot::forTask($snapshot, $task['id']),
    ]);
    $input = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'presentation_id' => $presentation->id,
        'expected_revision' => 1, 'event_type' => 'answer', 'answer' => ['choice_id' => $task['correct_choice_id']]];

    return [$run, $input];
}

it('serializes concurrent first answers to one presentation', function () {
    [$run, $input] = raceAnswerRun($this->actor);
    $other = [...$input, 'client_event_id' => (string) \Illuminate\Support\Str::uuid()];
    $results = raceWorkers($this->actor, [
        ['operation' => 'answer', 'run_id' => $run->id, 'input' => $input],
        ['operation' => 'answer', 'run_id' => $run->id, 'input' => $other],
    ]);
    expect(count(array_filter($results, fn (array $result): bool => $result['ok'])))->toBe(1)
        ->and(collect($results)->first(fn (array $result): bool => ! $result['ok'])['code'])->toBe('revision_conflict')
        ->and(\App\Models\Learning\LearningAttempt::count())->toBe(1)
        ->and(\App\Models\Learning\LearningAttempt::where('is_first_answer', true)->count())->toBe(1)
        ->and($run->refresh()->revision)->toBe(2);
});

it('replays the same first-answer event concurrently without duplicate scoring', function () {
    [$run, $input] = raceAnswerRun($this->actor);
    $job = ['operation' => 'answer', 'run_id' => $run->id, 'input' => $input];
    $results = raceWorkers($this->actor, [$job, $job]);
    expect($results[0]['ok'])->toBeTrue()->and($results[1])->toBe($results[0])
        ->and(\App\Models\Learning\LearningAttempt::count())->toBe(1)
        ->and($run->refresh()->revision)->toBe(2);
});

function raceAccountReady(User $actor): void
{
    $preview = racePreview($actor, racePackage());
    $receipt = app(SessionImportService::class)->commit($actor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($actor, $id, $checks);
        app(CatalogPublicationService::class)->publish($actor, $id);
    }
    foreach ($receipt['version_ids'] as $id) {
        app(SessionPublicationService::class)->review($actor, $id, $checks);
    }
    app(SessionPublicationService::class)->publishMany($actor, $receipt['version_ids']);
}

it('serializes concurrent account starts with one daily assignment and pinned run', function (bool $sameId) {
    raceAccountReady($this->actor);
    $input = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'];
    $other = $sameId ? $input : [...$input, 'client_event_id' => (string) \Illuminate\Support\Str::uuid()];
    $results = raceWorkers($this->actor, [['operation' => 'account_start', 'input' => $input], ['operation' => 'account_start', 'input' => $other]]);
    expect(array_column($results, 'ok'))->toBe([true, true]);
    expect($results[0]['receipt']['state']['run_id'])->toBe($results[1]['receipt']['state']['run_id']);
    expect(\App\Models\Learning\LearningRun::count())->toBe(1);
    expect(\App\Models\Learning\DailyAssignment::count())->toBe(1);
    expect(\App\Models\Learning\LearningPresentation::count())->toBe(6);
    expect(\App\Models\Learning\LearningRunOperation::count())->toBe($sameId ? 1 : 2);
})->with([true, false]);

it('serializes concurrent account finishes without a second date or result', function (bool $sameId) {
    raceAccountReady($this->actor);
    $service = app(\App\Services\Learning\AccountRunService::class);
    $state = $service->start($this->actor, ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])['state'];
    while (true) {
        $state = $service->event($this->actor, $state['run_id'], 'help', ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown'])['state'];
        if ($state['position'] === $state['total']) {
            break;
        }
        $state = $service->command($this->actor, $state['run_id'], 'advance', ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id']])['state'];
    }
    $input = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision']];
    $other = $sameId ? $input : [...$input, 'client_event_id' => (string) \Illuminate\Support\Str::uuid()];
    $results = raceWorkers($this->actor, [['operation' => 'account_finish', 'run_id' => $state['run_id'], 'input' => $input], ['operation' => 'account_finish', 'run_id' => $state['run_id'], 'input' => $other]]);
    expect(array_column($results, 'ok'))->toBe([true, true]);
    expect($results[0]['receipt']['state'])->toBe($results[1]['receipt']['state']);
    expect(\App\Models\Learning\LearningActivityDay::count())->toBe(1);
    expect(\App\Models\Learning\LearningRun::find($state['run_id'])->revision)->toBe($state['revision'] + 1);
})->with([true, false]);

it('serializes competing account answer IDs and returns only one first score', function () {
    raceAccountReady($this->actor);
    $state = app(\App\Services\Learning\AccountRunService::class)->start($this->actor, ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])['state'];
    $input = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'b']];
    $results = raceWorkers($this->actor, [['operation' => 'account_answer', 'run_id' => $state['run_id'], 'input' => $input], ['operation' => 'account_answer', 'run_id' => $state['run_id'], 'input' => [...$input, 'client_event_id' => (string) \Illuminate\Support\Str::uuid()]]]);
    expect(count(array_filter($results, fn ($r) => $r['ok'])))->toBe(1);
    expect(collect($results)->first(fn ($r) => ! $r['ok'])['code'])->toBe('revision_conflict');
    expect(\App\Models\Learning\LearningAttempt::count())->toBe(1);
});

it('serializes vocabulary successes from two separate runs without losing either increment', function () {
    raceAccountReady($this->actor);
    $snapshot = racePackage()['sessions'][0]['vocabulary'][0];
    $word = \App\Models\Learning\PersonalVocabulary::factory()->create(['user_id' => $this->actor->id, 'lexeme_key' => $snapshot['lexeme_key'], 'snapshot' => $snapshot, 'needs_review' => true]);
    $service = app(\App\Services\Learning\AccountRunService::class);
    $jobs = [];
    for ($i = 0; $i < 2; $i++) {
        $state = $service->start($this->actor, ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => 1, 'kind' => 'vocabulary_test', 'word_ids' => [$word->id]])['state'];
        $jobs[] = ['operation' => 'account_answer', 'run_id' => $state['run_id'], 'input' => ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision'],
            'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]];
    }
    $results = raceWorkers($this->actor, $jobs);
    expect(array_column($results, 'ok'))->toBe([true, true]);
    expect($word->fresh()->streak)->toBe(2);
    expect($word->fresh()->review_step)->toBe(2);
    expect(\App\Models\Learning\VocabularyEventEffect::count())->toBe(2);
    expect(\App\Models\Learning\VocabularyRunEffect::where('success_applied', true)->count())->toBe(2);
});

it('serializes duplicate guest sync batches and repacked events without a second import or level merge', function (bool $sameBatch) {
    raceAccountReady($this->actor);
    $version = LearningSessionVersion::whereHas('session', fn ($query) => $query->where('external_id', 'PRE_A1-U01-L01'))->firstOrFail();
    $guest = (string) \Illuminate\Support\Str::uuid();
    $run = (string) \Illuminate\Support\Str::uuid();
    $time = now('UTC')->toIso8601String();
    $presentations = array_map(fn () => (string) \Illuminate\Support\Str::uuid(), $version->payload['tasks']);
    $level = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'sequence' => 1, 'action' => 'level_selection', 'current_level' => 'B1', 'timezone' => 'UTC', 'client_occurred_at' => $time];
    $events = [
        ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'sequence' => 2, 'run_id' => $run, 'action' => 'start', 'client_occurred_at' => $time],
        ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'sequence' => 3, 'run_id' => $run, 'action' => 'help', 'event_type' => 'unknown', 'presentation_id' => $presentations[0], 'expected_revision' => 1, 'client_occurred_at' => $time],
    ];
    $bundle = ['guest_run_id' => $run, 'kind' => 'daily', 'session_version_id' => $version->id, 'snapshot' => $version->payload, 'checking_policy_version' => 1, 'presentation_ids' => $presentations,
        'timezone' => 'UTC', 'local_date' => now('UTC')->toDateString(), 'started_at' => $time, 'events' => $events];
    $input = ['guest_id' => $guest, 'episode_id' => null, 'batch_id' => (string) \Illuminate\Support\Str::uuid(), 'level_events' => [$level], 'runs' => [$bundle], 'marks' => []];
    $other = $sameBatch ? $input : [...$input, 'batch_id' => (string) \Illuminate\Support\Str::uuid()];
    $results = raceWorkers($this->actor, [['operation' => 'guest_sync', 'input' => $input], ['operation' => 'guest_sync', 'input' => $other]]);
    expect(array_column($results, 'ok'))->toBe([true, true]);
    expect(\App\Models\Learning\GuestSyncEpisode::count())->toBe(1);
    expect(\App\Models\Learning\GuestSyncBatch::count())->toBe($sameBatch ? 1 : 2);
    expect(\App\Models\Learning\GuestEventMapping::count())->toBe(4);
    expect(\App\Models\Learning\LearningLevelEvent::count())->toBe(1);
    expect(\App\Models\Learning\LearningRun::count())->toBe(1);
    expect(\App\Models\Learning\LearningAttempt::count())->toBe(1);
    expect(\App\Models\Learning\LearningProfile::where('user_id', $this->actor->id)->sole()->getRawOriginal('current_level'))->toBe('B1');
})->with([true, false]);

it('serializes two eligible checkpoint finishes into one automatic level event', function () {
    raceAccountReady($this->actor);
    $sessionRows = LearningSession::with('activeVersion')->orderBy('id')->get();
    $goal = $sessionRows[0]->activeVersion->payload['objectives'][0]['id'];
    foreach ($sessionRows as $session) {
        $payload = $session->activeVersion->payload;
        $payload['kind'] = 'checkpoint';
        $payload['prerequisite_ids'] = [];
        $payload['introductions'] = [];
        $payload['skills'] = ['vocabulary', 'listening'];
        $task = racePackage()['sessions'][0]['tasks'][0];
        $payload['vocabulary'] = racePackage()['sessions'][0]['vocabulary'];
        $payload['tasks'] = [];
        for ($i = 0; $i < 6; $i++) {
            $payload['tasks'][] = [...$task, 'id' => 'Q'.($i + 1), 'objective_ids' => [$goal], 'skill' => $i % 2 ? 'listening' : 'vocabulary'];
        }
        $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'version' => 2, 'payload' => $payload,
            'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')->subDays(2)]);
        $session->update(['active_version_id' => $version->id]);
    }
    \App\Models\Learning\LearningCurriculum::factory()->create(['external_id' => 'RACE-PROGRAM', 'level' => 'PRE_A1',
        'required_objective_ids' => [$goal], 'checkpoint_session_ids' => $sessionRows->pluck('external_id')->all(),
        'status' => 'published', 'published_at' => now('UTC')->subDays(2)]);
    $service = app(\App\Services\Learning\AccountRunService::class);
    $prepare = function ($session) use ($service) {
        $input = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $service->profile($this->actor)['revision'], 'kind' => 'extra', 'session_id' => $session->external_id];
        $state = $service->start($this->actor, $input)['state'];
        while (true) {
            $state = $service->event($this->actor, $state['run_id'], 'answer', ['client_event_id' => (string) \Illuminate\Support\Str::uuid(),
                'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'b']])['state'];
            if ($state['position'] === $state['total']) {
                return $state;
            }
            $state = $service->command($this->actor, $state['run_id'], 'advance', ['client_event_id' => (string) \Illuminate\Support\Str::uuid(),
                'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id']])['state'];
        }
    };
    \Carbon\CarbonImmutable::setTestNow(now('UTC')->subDay()->toImmutable());
    \Carbon\Carbon::setTestNow(\Carbon\CarbonImmutable::now());
    try {
        $state = $prepare($sessionRows[0]);
        $service->command($this->actor, $state['run_id'], 'finish', ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision']]);
    } finally {
        \Carbon\CarbonImmutable::setTestNow();
        \Carbon\Carbon::setTestNow();
    }
    $jobs = [];
    foreach ([$sessionRows[1], $sessionRows[0]] as $session) {
        $state = $prepare($session);
        $jobs[] = ['operation' => 'account_finish', 'run_id' => $state['run_id'], 'input' => ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision']]];
    }
    $results = raceWorkers($this->actor, $jobs);
    expect(array_column($results, 'ok'))->toBe([true, true]);
    expect(\App\Models\Learning\LearningProfile::where('user_id', $this->actor->id)->sole()->getRawOriginal('current_level'))->toBe('A1');
    expect(\App\Models\Learning\LearningLevelEvent::where('source', 'automatic')->count())->toBe(1);
    expect(\App\Models\Learning\LearningRun::where('status', 'completed')->count())->toBe(3);
    expect(\App\Models\Learning\LearningCurriculumEnrollment::count())->toBe(1);
    expect(count(array_filter($results, fn ($result) => ($result['receipt']['state']['summary']['promotion'] ?? null) !== null)))->toBe(1);
});
