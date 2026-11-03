<?php

use App\Enums\Learning\SessionVersionStatus;
use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningEditorialEvent;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\SessionImportBatch;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\CatalogPublicationService;
use App\Services\Learning\ContentAccess;
use App\Services\Learning\ContentOperationException;
use App\Services\Learning\CurriculumPublicationService;
use App\Services\Learning\SessionExportService;
use App\Services\Learning\SessionImportService;
use App\Services\Learning\SessionPackageValidator;
use App\Services\Learning\SessionPublicationService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(LearningLevelSeeder::class);
    Storage::fake('learning_private');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->admin = User::factory()->create(['rang_id' => $rank->id]);
    $this->importer = app(SessionImportService::class);
    $this->publisher = app(SessionPublicationService::class);
    $this->catalog = app(CatalogPublicationService::class);
});

function lifecycleData(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true);
}

function lifecyclePreview(User $actor, ?array $data = null): array
{
    return app(SessionImportService::class)->preview($actor, UploadedFile::fake()->createWithContent('package.json', CanonicalJson::encode($data ?? lifecycleData())));
}

function lifecycleCommit(User $actor, array $preview): array
{
    return app(SessionImportService::class)->commit($actor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
}

function lifecycleChecks(): array
{
    return array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
}

function lifecyclePublishCatalog(User $actor, array $receipt): void
{
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($actor, $id, lifecycleChecks());
        app(CatalogPublicationService::class)->publish($actor, $id);
    }
}

function lifecycleReview(User $actor, array $receipt): void
{
    foreach ($receipt['created_version_ids'] as $id) {
        app(SessionPublicationService::class)->review($actor, $id, lifecycleChecks(), 'Проверено редактором в тесте.');
    }
}

it('stores preview privately without creating content or history and binds it to the raw file', function () {
    $preview = lifecyclePreview($this->admin);
    $batch = SessionImportBatch::findOrFail($preview['batch_id']);
    expect($batch->preview_token_hash)->toBe(hash('sha256', $preview['token']))
        ->and($batch->preview_token_hash)->not->toBe($preview['token'])
        ->and($batch->expires_at->diffInSeconds(now('UTC'), true))->toBeGreaterThan(1795)
        ->and($batch->validation_report)->not->toHaveKey('token')
        ->and(LearningSession::count())->toBe(0)
        ->and(LearningUnit::count())->toBe(0)
        ->and(LearningRun::count())->toBe(0);
    Storage::disk('learning_private')->assertExists($batch->private_path);
    expect(hash('sha256', Storage::disk('learning_private')->get($batch->private_path)))->toBe($preview['file_hash']);
});

it('commits once with canonical checksums and pending catalog then replays the same receipt', function () {
    $preview = lifecyclePreview($this->admin);
    $receipt = lifecycleCommit($this->admin, $preview);
    expect($receipt['created_version_ids'])->toHaveCount(2)
        ->and($receipt['catalog_revision_ids'])->toHaveCount(2)
        ->and(LearningSession::whereNotNull('active_version_id')->count())->toBe(0)
        ->and(LearningCatalogRevision::where('status', 'pending')->count())->toBe(2)
        ->and(LearningUnit::first()->published_at)->toBeNull()
        ->and(LearningLevel::where('code', 'PRE_A1')->first()->title)->toBe('С нуля');
    foreach (LearningSessionVersion::all() as $version) {
        expect($version->status)->toBe(SessionVersionStatus::Draft)
            ->and($version->checksum)->toBe(CanonicalJson::hash($version->payload))
            ->and($version->checksum)->not->toBe($preview['file_hash']);
    }
    $events = LearningEditorialEvent::count();
    expect(lifecycleCommit($this->admin, $preview))->toBe($receipt)
        ->and(LearningEditorialEvent::count())->toBe($events);
    $again = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    expect($again['created_version_ids'])->toBe([])
        ->and($again['skipped_version_ids'])->toHaveCount(2);
});

it('rejects wrong owner token hash expiry and removed privileges', function (string $kind) {
    $preview = lifecyclePreview($this->admin);
    $actor = $this->admin;
    match ($kind) {
        'token' => $preview['token'] = str_repeat('a', 64),
        'hash' => $preview['file_hash'] = str_repeat('b', 64),
        'owner' => $actor = User::factory()->create(['rang_id' => $actor->rang_id]),
        'expiry' => SessionImportBatch::find($preview['batch_id'])->update(['expires_at' => now('UTC')->subSecond()]),
        'revoked' => $actor->update(['email_verified_at' => null]),
        'tampered' => Storage::disk('learning_private')->put(SessionImportBatch::find($preview['batch_id'])->private_path, '{}'),
        'missing' => Storage::disk('learning_private')->delete(SessionImportBatch::find($preview['batch_id'])->private_path),
    };
    expect(fn () => lifecycleCommit($actor, $preview))->toThrow(ContentOperationException::class)
        ->and(LearningSession::count())->toBe(0)
        ->and(LearningCatalogRevision::count())->toBe(0);
})->with(['token', 'hash', 'owner', 'expiry', 'revoked', 'tampered', 'missing']);

it('revalidates content conflicts after preview and rolls back the whole package', function () {
    $first = lifecyclePreview($this->admin);
    $changed = lifecycleData();
    $changed['sessions'][1]['title'] .= ' изменён';
    $second = lifecyclePreview($this->admin, $changed);
    lifecycleCommit($this->admin, $first);
    expect(fn () => lifecycleCommit($this->admin, $second))->toThrow(ContentOperationException::class)
        ->and(SessionImportBatch::find($second['batch_id'])->status)->toBe('preview')
        ->and(LearningSessionVersion::count())->toBe(2)
        ->and(LearningCatalogRevision::count())->toBe(2);
});

it('rolls back earlier inserts if a later catalog unique constraint fails', function () {
    $data = lifecycleData();
    $data['units'][0]['order'] = 5;
    $data['units'][0]['slug'] = 'existing';
    $preview = lifecyclePreview($this->admin, $data);
    LearningUnit::factory()->create(['level' => 'PRE_A1', 'sort_order' => 5, 'slug' => 'existing']);
    expect(fn () => lifecycleCommit($this->admin, $preview))->toThrow(ContentOperationException::class)
        ->and(LearningCatalogRevision::count())->toBe(0)
        ->and(LearningSessionVersion::count())->toBe(0)
        ->and(SessionImportBatch::find($preview['batch_id'])->status)->toBe('preview');
});

it('publishes only reviewed catalog revisions and rejects stale bases', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    $id = $receipt['catalog_revision_ids'][0];
    expect(fn () => $this->catalog->publish($this->admin, $id))->toThrow(ContentOperationException::class);
    $this->catalog->review($this->admin, $id, lifecycleChecks());
    $second = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    $stale = $second['catalog_revision_ids'][0];
    $this->catalog->review($this->admin, $stale, lifecycleChecks());
    $this->catalog->publish($this->admin, $id);
    expect(fn () => $this->catalog->publish($this->admin, $stale))->toThrow(ContentOperationException::class);
    expect($this->catalog->diff($this->admin, $stale)['current_revision'])->toBe(1)
        ->and(LearningCatalogRevision::find($stale)->status)->toBe('reviewed');
});

it('requires explicit editorial checks and published catalog', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    $id = $receipt['created_version_ids'][0];
    expect(fn () => $this->publisher->publish($this->admin, $id))->toThrow(ContentOperationException::class);
    expect(fn () => $this->publisher->review($this->admin, $id, ['language' => true]))->toThrow(ContentOperationException::class);
    $this->publisher->review($this->admin, $id, lifecycleChecks());
    expect(fn () => $this->publisher->publish($this->admin, $id))->toThrow(ContentOperationException::class);
    lifecyclePublishCatalog($this->admin, $receipt);
    expect(fn () => $this->publisher->publish($this->admin, $id))->toThrow(ContentOperationException::class);
    lifecycleReview($this->admin, $receipt);
    $this->publisher->publishMany($this->admin, $receipt['created_version_ids']);
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(2);
});

it('switches the active version atomically while preserving old runs payloads and publication dates', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    lifecyclePublishCatalog($this->admin, $receipt);
    lifecycleReview($this->admin, $receipt);
    $this->publisher->publishMany($this->admin, $receipt['created_version_ids']);
    $old = LearningSessionVersion::find($receipt['created_version_ids'][0]);
    $snapshot = $old->payload;
    $publishedAt = $old->published_at->toIso8601String();
    $run = LearningRun::factory()->create(['user_id' => $this->admin->id, 'session_version_id' => $old->id]);
    $data = lifecycleData();
    $data['sessions'] = [$data['sessions'][0]];
    $data['sessions'][0]['version'] = 2;
    $data['sessions'][0]['title'] .= ' новая версия';
    unset($data['levels'], $data['units']);
    $newReceipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin, $data));
    lifecycleReview($this->admin, $newReceipt);
    $newId = $newReceipt['created_version_ids'][0];
    $this->publisher->publish($this->admin, $newId);
    $this->publisher->publish($this->admin, $newId);
    expect($old->refresh()->status)->toBe(SessionVersionStatus::Archived)
        ->and($old->payload)->toBe($snapshot)
        ->and($old->published_at->toIso8601String())->toBe($publishedAt)
        ->and($run->refresh()->session_version_id)->toBe($old->id)
        ->and($old->session->active_version_id)->toBe($newId)
        ->and($old->session->versions()->where('status', 'published')->count())->toBe(1);
});

it('refuses dangling public dependencies and can archive the whole dependent group without deleting history', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    lifecyclePublishCatalog($this->admin, $receipt);
    lifecycleReview($this->admin, $receipt);
    $this->publisher->publishMany($this->admin, $receipt['created_version_ids']);
    $ids = LearningSession::orderBy('id')->pluck('id')->all();
    expect(fn () => $this->publisher->archive($this->admin, [$ids[0]]))->toThrow(ContentOperationException::class);
    $result = $this->publisher->archive($this->admin, $ids);
    expect($result['archived_session_ids'])->toBe($ids)
        ->and(LearningSession::whereNotNull('active_version_id')->count())->toBe(0)
        ->and(LearningSessionVersion::count())->toBe(2)
        ->and(LearningSessionVersion::where('status', 'archived')->count())->toBe(2)
        ->and(LearningEditorialEvent::where('operation', 'session_archive')->count())->toBe(2);
});

it('exports immutable imported resources after cleanup and keeps commit receipts replayable', function () {
    $preview = lifecyclePreview($this->admin);
    $receipt = lifecycleCommit($this->admin, $preview);
    SessionImportBatch::find($preview['batch_id'])->update(['expires_at' => now('UTC')->subSecond()]);
    expect($this->importer->pruneExpired())->toBe(1);
    Storage::disk('learning_private')->assertMissing(SessionImportBatch::find($preview['batch_id'])->private_path);
    expect(lifecycleCommit($this->admin, $preview))->toBe($receipt);
    $export = app(SessionExportService::class)->batch($this->admin, $preview['batch_id']);
    expect(CanonicalJson::hash($export))->toBe(CanonicalJson::hash(lifecycleData()))
        ->and(app(SessionPackageValidator::class)->validate(CanonicalJson::encode($export))->isValid())->toBeTrue();
    $versionExport = app(SessionExportService::class)->version($this->admin, $receipt['created_version_ids'][0]);
    expect($versionExport['sessions'][0])->toBe(LearningSessionVersion::find($receipt['created_version_ids'][0])->payload);
    expect($this->importer->pruneExpired())->toBe(0);
});

it('expires abandoned previews and schedules the cleanup command', function () {
    $preview = lifecyclePreview($this->admin);
    $batch = SessionImportBatch::find($preview['batch_id']);
    $batch->update(['expires_at' => now('UTC')->subSecond()]);
    $this->artisan('daily-practice:prune-previews')->assertSuccessful();
    expect($batch->refresh()->status)->toBe('expired');
    Storage::disk('learning_private')->assertMissing($batch->private_path);
    expect(fn () => lifecycleCommit($this->admin, $preview))->toThrow(ContentOperationException::class);
    $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
    expect(collect($events)->contains(fn ($event): bool => str_contains($event->command ?? '', 'daily-practice:prune-previews')))->toBeTrue();
});

it('protects every HTTP operation and supports uploads downloads and validation reports', function () {
    $prefix = '/admin/daily-practice';
    $this->getJson($prefix.'/schema')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    $this->actingAs(User::factory()->create())->getJson($prefix.'/schema')->assertForbidden();
    $this->actingAs($this->admin)->getJson($prefix.'/schema')->assertOk()->assertDownload('session-package-v1.schema.json');
    $this->get($prefix.'/templates/task-types')->assertOk()->assertDownload('task-types.json');
    $this->getJson($prefix.'/templates/../../../.env')->assertNotFound();
    $this->postJson($prefix.'/imports/preview', ['file' => UploadedFile::fake()->createWithContent('bad.json', '{"status":1,"status":2}')])->assertUnprocessable()->assertJsonFragment(['code' => 'duplicate_json_key']);
    $response = $this->post($prefix.'/imports/preview', ['file' => UploadedFile::fake()->createWithContent('package.json', CanonicalJson::encode(lifecycleData()))])->assertCreated();
    $preview = $response->json();
    $url = $prefix.'/imports/'.$preview['batch_id'].'/commit';
    $this->postJson($url, [])->assertUnprocessable();
    $receipt = $this->postJson($url, ['token' => $preview['token'], 'file_hash' => $preview['file_hash']])->assertOk()->json();
    $this->postJson($url, ['token' => $preview['token'], 'file_hash' => $preview['file_hash']])->assertOk()->assertExactJson($receipt);
    $this->get($prefix.'/imports/'.$preview['batch_id'].'/export')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->get($prefix.'/versions/'.$receipt['created_version_ids'][0].'/export')->assertOk()->assertDownload('session-version-'.$receipt['created_version_ids'][0].'.json');
    $this->postJson($prefix.'/versions/'.$receipt['created_version_ids'][0].'/review', ['checks' => ['language' => true]])->assertUnprocessable();
});

function lifecycleCheckpointPackage(): array
{
    $data = lifecycleData();
    foreach ($data['sessions'] as &$session) {
        $session['kind'] = 'checkpoint';
        $session['skills'] = array_values(array_unique(array_map(fn (string $skill): string => $skill === 'listening' ? 'vocabulary' : $skill, $session['skills'])));
        foreach ($session['tasks'] as &$task) {
            if ($task['skill'] === 'listening') {
                $task['skill'] = 'vocabulary';
            }
        }
        unset($task);
    }
    unset($session);
    $data['curricula'] = [[
        'id' => 'PRE_A1-PROGRAM', 'version' => 1, 'level' => 'PRE_A1',
        'required_objective_ids' => array_column($data['sessions'][0]['objectives'], 'id'),
        'checkpoint_session_ids' => array_column($data['sessions'], 'id'),
    ]];

    return $data;
}

it('publishes versioned curricula only with ready checkpoints and preserves pinned enrollments', function () {
    $preview = lifecyclePreview($this->admin, lifecycleCheckpointPackage());
    $receipt = lifecycleCommit($this->admin, $preview);
    $service = app(CurriculumPublicationService::class);
    $id = $receipt['curriculum_ids'][0];
    expect(fn () => $service->publish($this->admin, $id))->toThrow(ContentOperationException::class);
    expect(fn () => $service->review($this->admin, $id, lifecycleChecks()))->toThrow(ContentOperationException::class);
    lifecyclePublishCatalog($this->admin, $receipt);
    lifecycleReview($this->admin, $receipt);
    $this->publisher->publishMany($this->admin, $receipt['created_version_ids']);
    $service->review($this->admin, $id, lifecycleChecks());
    $old = $service->publish($this->admin, $id);
    $enrollment = \App\Models\Learning\LearningCurriculumEnrollment::factory()->create(['user_id' => $this->admin->id, 'curriculum_version_id' => $old->id]);
    expect(fn () => $this->publisher->archive($this->admin, LearningSession::pluck('id')->all()))->toThrow(ContentOperationException::class);
    $data = lifecycleCheckpointPackage();
    $next = $data['curricula'][0];
    $next['version'] = 2;
    $second = lifecycleCommit($this->admin, lifecyclePreview($this->admin, ['schema_version' => 1, 'package_id' => 'curriculum-next', 'language_pair' => 'fr-ru', 'sessions' => [], 'curricula' => [$next]]));
    $newId = $second['curriculum_ids'][0];
    $service->review($this->admin, $newId, lifecycleChecks());
    $service->publish($this->admin, $newId);
    expect($old->refresh()->status)->toBe(SessionVersionStatus::Archived)
        ->and($enrollment->refresh()->curriculum_version_id)->toBe($old->id)
        ->and(LearningCurriculum::where('status', 'published')->count())->toBe(1);
    $service->archive($this->admin, $newId);
    $this->publisher->archive($this->admin, LearningSession::pluck('id')->all());
    expect(LearningCurriculum::count())->toBe(2)
        ->and($enrollment->refresh()->exists)->toBeTrue();
});

it('rolls back the entire publication group when the last version is not reviewed', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    lifecyclePublishCatalog($this->admin, $receipt);
    $this->publisher->review($this->admin, $receipt['created_version_ids'][0], lifecycleChecks());
    expect(fn () => $this->publisher->publishMany($this->admin, $receipt['created_version_ids']))->toThrow(ContentOperationException::class)
        ->and(LearningSession::whereNotNull('active_version_id')->count())->toBe(0)
        ->and(LearningSessionVersion::where('status', 'published')->count())->toBe(0)
        ->and(LearningEditorialEvent::where('operation', 'session_publish')->count())->toBe(0);
});

it('stores UTC instants and expires previews at precisely the boundary in a non-UTC application', function () {
    $oldTimezone = date_default_timezone_get();
    date_default_timezone_set('Europe/Moscow');
    try {
        $preview = lifecyclePreview($this->admin);
        $batch = SessionImportBatch::find($preview['batch_id']);
        expect($batch->expires_at->getTimezone()->getName())->toBe('UTC')
            ->and($batch->expires_at->timestamp - now('UTC')->timestamp)->toBeIn([1799, 1800]);
        $this->travelTo($batch->expires_at);
        expect(fn () => lifecycleCommit($this->admin, $preview))->toThrow(ContentOperationException::class);
    } finally {
        $this->travelBack();
        date_default_timezone_set($oldTimezone);
    }
});

it('keeps snapshots immutable for catalog revisions and draft curriculum versions', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin, lifecycleCheckpointPackage()));
    $revision = LearningCatalogRevision::find($receipt['catalog_revision_ids'][0]);
    expect(fn () => $revision->update(['content' => ['changed' => true]]))->toThrow(LogicException::class);
    $curriculum = LearningCurriculum::find($receipt['curriculum_ids'][0]);
    expect(fn () => $curriculum->update(['checkpoint_session_ids' => []]))->toThrow(LogicException::class);
});

it('rolls preview database records back and removes private files if journaling fails', function () {
    LearningEditorialEvent::creating(function (): void {
        throw new RuntimeException('Test audit failure');
    });
    try {
        expect(fn () => lifecyclePreview($this->admin))->toThrow(RuntimeException::class, 'Test audit failure')
            ->and(SessionImportBatch::count())->toBe(0)
            ->and(Storage::disk('learning_private')->allFiles('previews'))->toBe([]);
    } finally {
        LearningEditorialEvent::flushEventListeners();
        LearningEditorialEvent::clearBootedModels();
    }
});

it('serves JSON authorization errors in production without converting them into Inertia pages', function () {
    $this->app->detectEnvironment(fn (): string => 'production');
    $this->actingAs(User::factory()->create())->get('/admin/daily-practice/schema')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('exports valid complete version packages even while their catalog is still pending', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    $data = app(SessionExportService::class)->version($this->admin, $receipt['created_version_ids'][0]);
    expect(app(SessionPackageValidator::class)->validate(CanonicalJson::encode($data))->toArray()['errors'])->toBe([]);
});

it('preserves session array order when a package reuses existing versions', function () {
    lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    $data = lifecycleData();
    $data['sessions'] = array_reverse($data['sessions']);
    $preview = lifecyclePreview($this->admin, $data);
    lifecycleCommit($this->admin, $preview);
    expect(CanonicalJson::hash(app(SessionExportService::class)->batch($this->admin, $preview['batch_id'])))->toBe(CanonicalJson::hash($data));
});

it('cleans old orphan preview files while retaining recent and registered files', function () {
    $disk = Storage::disk('learning_private');
    $orphan = 'previews/11111111-1111-4111-8111-111111111111.json';
    $recent = 'previews/22222222-2222-4222-8222-222222222222.json';
    $disk->put($orphan, '{}');
    $disk->put($recent, '{}');
    touch($disk->path($orphan), now('UTC')->subHours(2)->timestamp);
    $preview = lifecyclePreview($this->admin);
    expect($this->importer->pruneExpired())->toBe(0);
    $disk->assertMissing($orphan);
    $disk->assertExists($recent);
    $disk->assertExists(SessionImportBatch::find($preview['batch_id'])->private_path);
});

it('rejects catalog and objective text beyond physical database limits before writing', function (string $kind, string $path) {
    $data = lifecycleData();
    match ($kind) {
        'level_title' => $data['levels'][0]['title'] = str_repeat('а', 256),
        'unit_title' => $data['units'][0]['title'] = str_repeat('а', 256),
        'objective_title' => $data['sessions'][0]['objectives'][0]['title'] = str_repeat('а', 256),
        'summary' => $data['units'][0]['summary'] = str_repeat('😀', 16384),
    };
    $report = app(SessionPackageValidator::class)->validate(CanonicalJson::encode($data));
    expect(collect($report->errors())->contains(fn ($issue): bool => $issue->code === 'text_too_long' && $issue->path === $path))->toBeTrue();
    expect(fn () => lifecyclePreview($this->admin, $data))->toThrow(ContentOperationException::class)
        ->and(SessionImportBatch::count())->toBe(0)
        ->and(LearningCatalogRevision::count())->toBe(0);
})->with([
    ['level_title', '/levels/0/title'],
    ['unit_title', '/units/0/title'],
    ['objective_title', '/sessions/0/objectives/0/title'],
    ['summary', '/units/0/summary'],
]);

it('rejects editorial notes exceeding TEXT byte capacity without changing review status', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    $id = $receipt['created_version_ids'][0];
    expect(fn () => $this->publisher->review($this->admin, $id, lifecycleChecks(), str_repeat('😀', 17000)))->toThrow(ContentOperationException::class)
        ->and(LearningSessionVersion::find($id)->status)->toBe(SessionVersionStatus::Draft);
});

it('publishes revised material at the stable explicitly renamed URL', function () {
    config(['daily_practice.enabled' => true]);
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    lifecyclePublishCatalog($this->admin, $receipt);
    lifecycleReview($this->admin, $receipt);
    $this->publisher->publishMany($this->admin, $receipt['created_version_ids']);
    $session = LearningSession::where('external_id', lifecycleData()['sessions'][0]['id'])->firstOrFail()->load('unit');
    $old = \App\Services\Learning\PracticeUrls::session($session);
    $renamed = app(\App\Services\Learning\PracticeUrls::class)->rename($this->admin, 'session', $session->id, ['expected_slug' => $session->slug, 'slug' => 'stable-published-address'])['path'];
    $data = lifecycleData();
    unset($data['levels'], $data['units']);
    $data['package_id'] = 'updated-public-material';
    $data['sessions'] = [$data['sessions'][0]];
    $data['sessions'][0]['version'] = 2;
    $data['sessions'][0]['public_page']['summary'] = 'Обновлённое публичное объяснение приветствий и знакомства перед практикой.';
    $next = lifecycleCommit($this->admin, lifecyclePreview($this->admin, $data));
    lifecycleReview($this->admin, $next);
    $this->publisher->publish($this->admin, $next['created_version_ids'][0]);
    $this->withoutVite()->get($renamed)->assertOk()->assertSee($data['sessions'][0]['public_page']['summary']);
    $this->get($old)->assertStatus(301)->assertRedirect(url($renamed));
    expect($session->fresh()->slug)->toBe('stable-published-address');
});
it('reserves old public addresses during publication as well as renaming', function () {
    $receipt = lifecycleCommit($this->admin, lifecyclePreview($this->admin));
    lifecyclePublishCatalog($this->admin, $receipt);
    lifecycleReview($this->admin, $receipt);
    $first = LearningSessionVersion::findOrFail($receipt['created_version_ids'][0])->load('session.unit');
    $path = \App\Services\Learning\PracticeUrls::unit($first->session->unit).'/'.$first->payload['public_page']['slug'];
    \App\Models\Learning\LearningUrlAlias::create(['old_path' => $path, 'target_path' => '/practice/pre-a1/another/old-material', 'session_id' => LearningSessionVersion::findOrFail($receipt['created_version_ids'][1])->session_id]);
    expect(fn () => $this->publisher->publishMany($this->admin, $receipt['created_version_ids']))->toThrow(ContentOperationException::class);
    expect($first->fresh()->status->value)->toBe('reviewed')->and($first->session->fresh()->active_version_id)->toBeNull();
});
