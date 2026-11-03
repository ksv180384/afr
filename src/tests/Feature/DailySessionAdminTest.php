<?php

use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\ContentAccess;
use App\Services\Learning\MediaLibraryService;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    Storage::fake('learning_private');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->admin = User::factory()->create(['rang_id' => $rank->id]);
});

function adminPackage(): array
{
    return json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
}

function adminImport($test, ?array $package = null): array
{
    $preview = $test->actingAs($test->admin)->post('/admin/daily-practice/imports/preview',
        ['file' => UploadedFile::fake()->createWithContent('content.json', json_encode($package ?? adminPackage()))],
        ['Accept' => 'application/json'])->assertCreated()->json();
    $receipt = $test->postJson('/admin/daily-practice/imports/'.$preview['batch_id'].'/commit', [
        'token' => $preview['token'], 'file_hash' => $preview['file_hash'],
    ])->assertOk()->json();

    return [$preview, $receipt];
}

function adminWav(): string
{
    $pcm = str_repeat("\0", 16000);

    return 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
}

it('places every module endpoint behind the shared authentication and admin middleware', function () {
    foreach (app('router')->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/daily-practice')) {
            continue;
        }
        expect($route->gatherMiddleware())->toContain('auth', 'verified', 'is-admin');
    }
});

it('denies guests ordinary users and unverified administrators before reading or writing', function (string $method, string $path) {
    $url = '/admin/daily-practice/'.$path;
    $this->json($method, $url)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->json($method, $url)->assertForbidden();
    $this->admin->update(['email_verified_at' => null]);
    $this->actingAs($this->admin)->json($method, $url)->assertForbidden();
    $this->assertDatabaseCount('learning_media', 0);
    $this->assertDatabaseCount('learning_content_reports', 0);
})->with([
    ['GET', ''], ['GET', 'catalog'], ['GET', 'reports'], ['GET', 'media'],
    ['GET', 'versions/1'], ['GET', 'versions/1/preview'], ['POST', 'reports'], ['POST', 'reports/1'],
    ['POST', 'media'], ['POST', 'media/00000000-0000-4000-8000-000000000001/approve'],
    ['GET', 'media/00000000-0000-4000-8000-000000000001/file'],
]);

it('returns previews plans conflicts and duplicate skips without changing immutable snapshots', function () {
    [$preview, $receipt] = adminImport($this);
    expect(array_column($preview['plan']['sessions'], 'action'))->toBe(['create', 'create']);
    expect($preview['plan']['catalog_revisions'])->toBe(2);
    [$duplicate, $again] = adminImport($this);
    expect(array_column($duplicate['plan']['sessions'], 'action'))->toBe(['skip', 'skip']);
    expect($again['created_version_ids'])->toBe([]);
    $changed = adminPackage();
    $changed['sessions'][0]['title'] = 'Другой текст';
    $this->post('/admin/daily-practice/imports/preview', ['file' => UploadedFile::fake()->createWithContent('changed.json', json_encode($changed))],
        ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonFragment(['code' => 'version_conflict']);
    expect(LearningSessionVersion::count())->toBe(2);
});

it('lists filters and paginates versions and exposes authorized snapshot previews without progress writes', function () {
    [, $receipt] = adminImport($this);
    $id = $receipt['version_ids'][0];
    $this->get('/admin/daily-practice?search=Bonjour&level=PRE_A1&unit=PRE_A1-U01&status=draft&version=1&from=2000-01-01&to=2099-01-01')
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page->component('DailySession/Index', false)->has('versions', 1)->where('paginate.total', 1));
    $this->get('/admin/daily-practice?search=not-present')->assertInertia(fn (Assert $p) => $p->has('versions', 0));
    $this->get('/admin/daily-practice/versions/'.$id)->assertInertia(fn (Assert $p) => $p->component('DailySession/Show', false)
        ->missing('history')->missing('previous')->where('version.payload.id', 'PRE_A1-U01-L01')->has('publicMeta.breadcrumbs', 4)->where('publicMeta.missing', []));
    $this->get('/admin/daily-practice/versions/'.$id.'/preview?frame=1')->assertInertia(fn (Assert $p) => $p->component('DailySession/Preview', false)->where('frame', true));
    $this->get('/admin/daily-practice/catalog')->assertInertia(fn (Assert $p) => $p->component('DailySession/Catalog', false)->has('revisions.data', 2));
    expect(LearningRun::count())->toBe(0);
    $this->assertDatabaseCount('personal_vocabulary', 0);
    $this->getJson('/admin/daily-practice?level=NOT_A_LEVEL')->assertUnprocessable();
});

it('requires explicit checks then publishes catalog and versions through the HTTP workflow', function () {
    [, $receipt] = adminImport($this);
    $id = $receipt['version_ids'][0];
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    $this->postJson('/admin/daily-practice/versions/'.$id.'/publish')->assertConflict();
    $this->postJson('/admin/daily-practice/versions/'.$id.'/review', ['checks' => []])->assertUnprocessable();
    foreach ($receipt['catalog_revision_ids'] as $revision) {
        $this->get('/admin/daily-practice/catalog/'.$revision.'/export')->assertOk()->assertDownload('catalog-'.$revision.'.json');
        $this->getJson('/admin/daily-practice/catalog/'.$revision.'/diff')->assertOk()->assertJsonStructure(['before', 'after', 'base_revision']);
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/review', ['checks' => $checks])->assertOk();
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/publish')->assertOk();
    }
    foreach ($receipt['version_ids'] as $version) {
        $this->postJson('/admin/daily-practice/versions/'.$version.'/review', ['checks' => $checks, 'notes' => 'Проверено'])->assertOk();
    }
    $this->postJson('/admin/daily-practice/versions/publish', ['version_ids' => $receipt['version_ids']])->assertOk();
    $this->get('/admin/daily-practice/versions/'.$id.'/export')->assertOk()->assertDownload('session-version-'.$id.'.json');
    $this->postJson('/admin/daily-practice/versions/'.$id.'/archive')->assertConflict();
    $this->postJson('/admin/daily-practice/sessions/archive', ['session_ids' => LearningSessionVersion::query()->pluck('session_id')->all()])->assertOk();
    expect(LearningSessionVersion::find($id)->status->value)->toBe('archived');
});

it('stores incomplete public content as draft but blocks review and publication', function () {
    $package = adminPackage();
    unset($package['sessions'][0]['public_page']);
    [, $receipt] = adminImport($this, $package);
    $id = $receipt['version_ids'][0];
    $this->get('/admin/daily-practice/versions/'.$id)->assertInertia(fn (Assert $p) => $p->has('publicMeta.missing', 5));
    $this->postJson('/admin/daily-practice/versions/'.$id.'/review', ['checks' => array_fill_keys(ContentAccess::REVIEW_CHECKS, true)])->assertUnprocessable();
    expect(LearningSessionVersion::find($id)->status->value)->toBe('draft');
});

it('creates and processes task reports without altering content', function () {
    [, $receipt] = adminImport($this);
    $id = $receipt['version_ids'][0];
    $before = LearningSessionVersion::find($id)->payload;
    $this->postJson('/admin/daily-practice/reports', ['version_id' => $id, 'task_id' => 'MISSING', 'text' => 'Ошибка'])->assertUnprocessable();
    $report = $this->postJson('/admin/daily-practice/reports', ['version_id' => $id, 'task_id' => 'Q01', 'text' => 'Проверить звук'])->assertCreated()->json('id');
    $this->postJson('/admin/daily-practice/reports/'.$report, ['status' => 'invalid'])->assertUnprocessable();
    $this->postJson('/admin/daily-practice/reports/'.$report, ['status' => 'resolved', 'editor_notes' => 'Нужна новая версия'])->assertOk();
    expect(LearningContentReport::find($report)->processed_by)->toBe($this->admin->id);
    expect(LearningContentReport::find($report)->resolved_at)->not->toBeNull();
    $this->get('/admin/daily-practice/reports?status=resolved')->assertInertia(fn (Assert $p) => $p->has('reports.data', 1));
    expect(LearningSessionVersion::find($id)->payload)->toBe($before);
});

it('validates real WAV bytes duration and protected playback before explicit media approval', function () {
    $this->actingAs($this->admin);
    $this->post('/admin/daily-practice/media', ['file' => UploadedFile::fake()->createWithContent('bad.wav', '<script>alert(1)</script>')], ['Accept' => 'application/json'])->assertUnprocessable();
    $asset = $this->post('/admin/daily-practice/media', ['file' => UploadedFile::fake()->createWithContent('clip.wav', adminWav())], ['Accept' => 'application/json'])->assertCreated()->json();
    expect($asset['duration_ms'])->toBe(1000);
    expect($asset['review_status'])->toBe('pending');
    expect(Storage::disk('learning_private')->exists($asset['path']))->toBeTrue();
    $this->postJson('/admin/daily-practice/media/'.$asset['id'].'/approve', ['confirmed' => false])->assertUnprocessable();
    $this->get('/admin/daily-practice/media/'.$asset['id'].'/file')->assertOk()->assertHeader('Content-Type', 'audio/wav');
    $this->postJson('/admin/daily-practice/media/'.$asset['id'].'/approve', ['confirmed' => true])->assertOk()->assertJsonPath('review_status', 'approved');
    $this->get('/admin/daily-practice/media')->assertRedirect('/admin/daily-practice');
    Storage::disk('learning_private')->put($asset['path'], 'tampered');
    $this->get('/admin/daily-practice/media/'.$asset['id'].'/file')->assertNotFound();
});

it('rejects malformed or overlong WAV without trusting client duration', function () {
    $library = app(MediaLibraryService::class);
    expect(fn () => $library->inspect(substr(adminWav(), 0, -2)))->toThrow(\Illuminate\Validation\ValidationException::class);
    $data = str_repeat("\0", 16000 * 301);
    $long = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($data)).$data;
    expect(fn () => $library->inspect($long))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('shows curriculum coverage and controls and runs their review publish archive workflow', function () {
    $package = adminPackage();
    foreach ($package['sessions'] as &$session) {
        $session['kind'] = 'checkpoint';
        $session['skills'] = array_values(array_unique(array_map(fn ($skill) => $skill === 'listening' ? 'vocabulary' : $skill, $session['skills'])));
        foreach ($session['tasks'] as &$task) {
            if ($task['skill'] === 'listening') {
                $task['skill'] = 'vocabulary';
            }
        }
        unset($task);
    }
    unset($session);
    $package['curricula'] = [['id' => 'PRE_A1-PROGRAM', 'version' => 1, 'level' => 'PRE_A1',
        'required_objective_ids' => array_column($package['sessions'][0]['objectives'], 'id'),
        'checkpoint_session_ids' => array_column($package['sessions'], 'id')]];
    [, $receipt] = adminImport($this, $package);
    $id = $receipt['curriculum_ids'][0];
    $this->get('/admin/daily-practice/curricula/'.$id.'/export')->assertOk()->assertDownload('curriculum-'.$id.'.json');
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/review', ['checks' => $checks])->assertUnprocessable();
    foreach ($receipt['catalog_revision_ids'] as $revision) {
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/review', ['checks' => $checks])->assertOk();
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/publish')->assertOk();
    }
    foreach ($receipt['version_ids'] as $version) {
        $this->postJson('/admin/daily-practice/versions/'.$version.'/review', ['checks' => $checks])->assertOk();
    }
    $this->postJson('/admin/daily-practice/versions/publish', ['version_ids' => $receipt['version_ids']])->assertOk();
    $this->get('/admin/daily-practice/catalog')->assertInertia(fn (Assert $p) => $p->has('curricula.data.0.coverage', 3)
        ->where('curricula.data.0.checkpoints.0.ready', true)->where('coverage.published_sessions', 2));
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/review', ['checks' => $checks])->assertOk();
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/publish')->assertOk()->assertJsonPath('status', 'published');
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/archive')->assertOk()->assertJsonPath('status', 'archived');
});

it('rechecks revoked admin access and validates preview scenarios and filters', function () {
    [, $receipt] = adminImport($this);
    $this->getJson('/admin/daily-practice/versions/'.$receipt['version_ids'][0].'/preview?scenario=unknown')->assertUnprocessable();
    foreach (['load_error', 'empty', 'exhausted', 'save'] as $scenario) {
        $this->get('/admin/daily-practice/versions/'.$receipt['version_ids'][0].'/preview?frame=1&scenario='.$scenario)
            ->assertInertia(fn (Assert $p) => $p->where('scenario', $scenario));
    }
    $this->getJson('/admin/daily-practice?page=0')->assertUnprocessable();
    $rank = Rang::where('alias', 'polzovatel')->firstOrFail();
    User::whereKey($this->admin->id)->update(['rang_id' => $rank->id]);
    $this->getJson('/admin/daily-practice')->assertForbidden();
});

it('stores media and report timestamps in UTC with a Moscow application timezone', function () {
    config(['app.timezone' => 'Europe/Moscow']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-25 12:30:00', 'UTC'));
    [, $receipt] = adminImport($this);
    $report = $this->postJson('/admin/daily-practice/reports', ['version_id' => $receipt['version_ids'][0], 'text' => 'Проверка времени'])->assertCreated()->json('id');
    $this->postJson('/admin/daily-practice/reports/'.$report, ['status' => 'resolved'])->assertOk();
    expect(LearningContentReport::find($report)->getRawOriginal('resolved_at'))->toBe('2026-10-25 12:30:00');
    $asset = app(MediaLibraryService::class)->upload($this->admin, UploadedFile::fake()->createWithContent('utc.wav', adminWav()));
    app(MediaLibraryService::class)->approve($this->admin, $asset->id);
    expect($asset->fresh()->getRawOriginal('reviewed_at'))->toBe('2026-10-25 12:30:00');
    expect($asset->fresh()->getRawOriginal('created_at'))->toBe('2026-10-25 12:30:00');
    $this->travelBack();
});

it('redirects the retired media page and cleans expired import files without a scheduler', function () {
    [$preview] = adminImport($this);
    $batch = App\Models\Learning\SessionImportBatch::findOrFail($preview['batch_id']);
    $batch->update(['expires_at' => now('UTC')->subMinute()]);
    adminImport($this);
    Storage::disk('learning_private')->assertMissing($batch->private_path);
    $this->get('/admin/daily-practice/media')->assertRedirect('/admin/daily-practice');
});
