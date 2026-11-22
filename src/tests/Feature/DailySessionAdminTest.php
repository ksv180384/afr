<?php

use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
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

it('uploads and replaces the topic background for both practice routes', function () {
    config(['daily_practice.enabled' => true]);
    Storage::fake('public');
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['published_at' => now('UTC')]);
    $url = route('admin.daily-practice.units.background', $unit->id);
    $headers = ['Accept' => 'application/json'];

    $this->post($url, [], $headers)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->post($url, [], $headers)->assertForbidden();
    $this->actingAs($this->admin)->post($url, ['file' => UploadedFile::fake()->create('background.svg')], $headers)
        ->assertUnprocessable();

    $upload = fn () => $this->post($url, ['file' => UploadedFile::fake()->image('background.png', 1600, 2400)],
        [...$headers, 'Content-Length' => '1000000'])->assertOk()->json('background_url');
    $first = $upload();
    $oldPath = $unit->fresh()->background_path;
    Storage::disk('public')->assertExists($oldPath);

    $replacement = $upload();
    expect($replacement)->not->toBe($first);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($unit->fresh()->background_path);
    $this->get('/admin/daily-practice/catalog')->assertInertia(fn (Assert $page) => $page
        ->where('units.0.background_url', $replacement));
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('route.topics.0.background_url', $replacement);
    $this->getJson('/daily/api/content')->assertOk()->assertJsonPath('access_outline.0.units.0.background_url', $replacement);
});

it('opens the separate import page only for administrators', function () {
    $this->get('/admin/daily-practice/imports')->assertRedirect('/login');
    $this->actingAs($this->admin)->get('/admin/daily-practice/imports')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('DailySession/Imports', false));
});

it('filters the lesson table through a partial Inertia request', function () {
    $version = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->actingAs($this->admin)->get('/admin/daily-practice?search=Bonjour', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'DailySession/Index',
        'X-Inertia-Partial-Data' => 'versions,paginate,filters',
    ])->assertOk()->assertJsonPath('component', 'DailySession/Index')
        ->assertJsonPath('props.filters.search', 'Bonjour')
        ->assertJsonMissingPath('props.levels')
        ->assertJsonMissingPath('props.authUser');
});

function adminPackage(): array
{
    return json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true);
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
    ['GET', 'sessions/1'], ['GET', 'sessions/1/preview'], ['POST', 'reports'], ['POST', 'reports/1'],
    ['POST', 'catalog/levels/1/visibility'], ['POST', 'catalog/units/1/visibility'],
    ['POST', 'media'], ['POST', 'media/00000000-0000-4000-8000-000000000001/approve'],
    ['GET', 'media/00000000-0000-4000-8000-000000000001/file'],
]);

it('replaces imported lessons by stable ID without creating another content record', function () {
    [$preview, $receipt] = adminImport($this);
    expect(array_column($preview['plan']['sessions'], 'action'))->toBe(['create', 'create']);
    expect($preview['plan']['catalog_revisions'])->toBe(2);
    [$duplicate, $again] = adminImport($this);
    expect(array_column($duplicate['plan']['sessions'], 'action'))->toBe(['replace', 'replace']);
    expect($again['created_version_ids'])->toBe([]);
    $changed = adminPackage();
    $changed['sessions'][0]['title'] = 'Другой текст';
    adminImport($this, $changed);
    expect(LearningSession::find($receipt['version_ids'][0])->payload['title'])->toBe('Другой текст');
    expect(LearningSession::count())->toBe(2);
});

it('lists filters and paginates versions and exposes authorized snapshot previews without progress writes', function () {
    [, $receipt] = adminImport($this);
    $id = $receipt['version_ids'][0];
    $this->get('/admin/daily-practice?search=Bonjour&level=PRE_A1&unit=PRE_A1-U01&status=draft&version=1&from=2000-01-01&to=2099-01-01')
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page->component('DailySession/Index', false)->has('versions', 1)->where('paginate.total', 1));
    $this->get('/admin/daily-practice?search=not-present')->assertInertia(fn (Assert $p) => $p->has('versions', 0));
    $this->get('/admin/daily-practice/sessions/'.$id)->assertInertia(fn (Assert $p) => $p->component('DailySession/Show', false)
        ->missing('history')->missing('previous')->where('version.payload.id', 'PRE_A1-U01-L01')->has('publicMeta.breadcrumbs', 4)->where('publicMeta.missing', []));
    $this->get('/admin/daily-practice/sessions/'.$id.'/preview?frame=1')->assertInertia(fn (Assert $p) => $p->component('DailySession/Preview', false)->where('frame', true));
    $legacyId = $receipt['version_ids'][1];
    LearningSession::whereKey($id)->update(['legacy_content_id' => $legacyId]);
    $this->get('/admin/daily-practice/versions/'.$legacyId)->assertRedirect('/admin/daily-practice/sessions/'.$id);
    $this->get('/admin/daily-practice/versions/'.$legacyId.'/preview?frame=1')->assertRedirect('/admin/daily-practice/sessions/'.$id.'/preview?frame=1');
    $this->get('/admin/daily-practice/catalog')->assertInertia(fn (Assert $p) => $p->component('DailySession/Catalog', false)->has('revisions.data', 2));
    expect(LearningRun::count())->toBe(0);
    $this->assertDatabaseCount('personal_vocabulary', 0);
    $this->getJson('/admin/daily-practice?level=NOT_A_LEVEL')->assertUnprocessable();
});

it('requires explicit checks then publishes catalog and versions through the HTTP workflow', function () {
    [, $receipt] = adminImport($this);
    $id = $receipt['version_ids'][0];
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    $this->postJson('/admin/daily-practice/sessions/'.$id.'/publish')->assertConflict();
    $this->postJson('/admin/daily-practice/sessions/'.$id.'/review', ['checks' => []])->assertUnprocessable();
    foreach ($receipt['catalog_revision_ids'] as $revision) {
        $this->get('/admin/daily-practice/catalog/'.$revision.'/export')->assertOk()->assertDownload('catalog-'.$revision.'.json');
        $this->getJson('/admin/daily-practice/catalog/'.$revision.'/diff')->assertOk()->assertJsonStructure(['before', 'after', 'base_revision']);
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/review', ['checks' => $checks])->assertOk();
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/publish')->assertOk();
    }
    foreach ($receipt['version_ids'] as $version) {
        $this->postJson('/admin/daily-practice/sessions/'.$version.'/review', ['checks' => $checks, 'notes' => 'Проверено'])->assertOk();
    }
    $this->postJson('/admin/daily-practice/sessions/publish', ['version_ids' => $receipt['version_ids']])->assertOk();
    $this->get('/admin/daily-practice/sessions/'.$id.'/export')->assertOk()->assertDownload('session-version-'.$id.'.json');
    $this->postJson('/admin/daily-practice/sessions/'.$id.'/archive')->assertConflict();
    $this->postJson('/admin/daily-practice/sessions/archive', ['session_ids' => LearningSession::query()->pluck('id')->all()])->assertOk();
    expect(LearningSession::find($id)->status->value)->toBe('archived');
});

it('stores incomplete public content as draft but blocks review and publication', function () {
    $package = adminPackage();
    unset($package['sessions'][0]['public_page']);
    [, $receipt] = adminImport($this, $package);
    $id = $receipt['version_ids'][0];
    $this->get('/admin/daily-practice/sessions/'.$id)->assertInertia(fn (Assert $p) => $p->has('publicMeta.missing', 5));
    $this->postJson('/admin/daily-practice/sessions/'.$id.'/review', ['checks' => array_fill_keys(ContentAccess::REVIEW_CHECKS, true)])->assertUnprocessable();
    expect(LearningSession::find($id)->status->value)->toBe('draft');
});

it('creates and processes task reports without altering content', function () {
    [, $receipt] = adminImport($this);
    $id = $receipt['version_ids'][0];
    $before = LearningSession::find($id)->payload;
    $this->postJson('/admin/daily-practice/reports', ['version_id' => $id, 'task_id' => 'MISSING', 'text' => 'Ошибка'])->assertUnprocessable();
    $report = $this->postJson('/admin/daily-practice/reports', ['version_id' => $id, 'task_id' => 'Q01', 'text' => 'Проверить звук'])->assertCreated()->json('id');
    $this->postJson('/admin/daily-practice/reports/'.$report, ['status' => 'invalid'])->assertUnprocessable();
    $this->postJson('/admin/daily-practice/reports/'.$report, ['status' => 'resolved', 'editor_notes' => 'Нужна новая версия'])->assertOk();
    expect(LearningContentReport::find($report)->processed_by)->toBe($this->admin->id);
    expect(LearningContentReport::find($report)->resolved_at)->not->toBeNull();
    $this->get('/admin/daily-practice/reports?status=resolved')->assertInertia(fn (Assert $p) => $p->has('reports.data', 1));
    expect(LearningSession::find($id)->payload)->toBe($before);
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
        $this->postJson('/admin/daily-practice/sessions/'.$version.'/review', ['checks' => $checks])->assertOk();
    }
    $this->postJson('/admin/daily-practice/sessions/publish', ['version_ids' => $receipt['version_ids']])->assertOk();
    $this->get('/admin/daily-practice/catalog')->assertInertia(fn (Assert $p) => $p->has('curricula.data.0.coverage', 3)
        ->where('curricula.data.0.checkpoints.0.ready', true)->where('coverage.published_sessions', 2));
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/review', ['checks' => $checks])->assertOk();
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/publish')->assertOk()->assertJsonPath('status', 'published');
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/archive')->assertOk()->assertJsonPath('status', 'archived');
});

it('rechecks revoked admin access and validates preview scenarios and filters', function () {
    [, $receipt] = adminImport($this);
    $this->getJson('/admin/daily-practice/sessions/'.$receipt['version_ids'][0].'/preview?scenario=unknown')->assertUnprocessable();
    foreach (['load_error', 'empty', 'exhausted', 'save'] as $scenario) {
        $this->get('/admin/daily-practice/sessions/'.$receipt['version_ids'][0].'/preview?frame=1&scenario='.$scenario)
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


it('edits lesson content and rejects stale edits', function () {
    [, $receipt] = adminImport($this);
    $row = LearningSession::findOrFail($receipt['version_ids'][0]);
    $content = $row->payload;
    $content['title'] = 'Изменено в редакторе';
    $this->postJson('/admin/daily-practice/sessions/'.$row->id.'/save', ['content' => $content, 'checksum' => $row->checksum])->assertOk();
    expect($row->fresh()->payload['title'])->toBe($content['title']);
    $this->postJson('/admin/daily-practice/sessions/'.$row->id.'/save', ['content' => $content, 'checksum' => $row->checksum])->assertUnprocessable();
});

it('requires confirmation and completely deletes a lesson and removes dangling prerequisites', function () {
    [, $receipt] = adminImport($this);
    $row = LearningSession::findOrFail($receipt['version_ids'][0]);
    $url = '/admin/daily-practice/manage/sessions/'.$row->id.'/delete';
    $this->postJson($url)->assertUnprocessable();
    $this->postJson($url, ['confirmed' => true])->assertOk();
    $this->assertDatabaseMissing('learning_sessions', ['id' => $row->id]);
    expect(LearningSession::findOrFail($receipt['version_ids'][1])->payload['prerequisite_ids'])->not->toContain($row->payload['id']);
});

it('deletes a topic with all its lessons', function () {
    [, $receipt] = adminImport($this);
    $unit = LearningSession::findOrFail($receipt['version_ids'][0])->unit;
    $this->postJson('/admin/daily-practice/manage/units/'.$unit->id.'/delete', ['confirmed' => true])->assertOk();
    $this->assertDatabaseMissing('learning_units', ['id' => $unit->id]);
    expect(LearningSession::count())->toBe(0);
});

it('deletes a level and moves its profiles to the nearest remaining level', function () {
    adminImport($this);
    $profile = \App\Models\Learning\LearningProfile::factory()->create(['user_id' => $this->admin->id, 'current_level' => 'PRE_A1']);
    $this->postJson('/admin/daily-practice/manage/levels/PRE_A1/delete', ['confirmed' => true])->assertOk();
    $this->assertDatabaseMissing('learning_levels', ['code' => 'PRE_A1']);
    expect($profile->fresh()->getRawOriginal('current_level'))->toBe('A1');
    $this->get('/daily')->assertOk();
    $program = \App\Models\Learning\LearningCurriculum::factory()->create(['level' => 'A1']);
    $this->postJson('/admin/daily-practice/manage/curricula/'.$program->id.'/delete', ['confirmed' => true])->assertOk();
});


it('replaces published content and drops unfinished attempts', function () {
    [, $receipt] = adminImport($this);
    $row = LearningSession::findOrFail($receipt['version_ids'][0]);
    $row->update(['status' => 'published', 'published_at' => now()]);
    $run = LearningRun::factory()->create(['user_id' => $this->admin->id, 'session_id' => $row->id]);
    $p = adminPackage(); $p['sessions'][0]['title'] = 'Новое содержимое'; $p['sessions'][0]['version'] = 99;
    [, $updated] = adminImport($this, $p);
    expect($updated['version_ids'][0])->toBe($row->id);
    expect($row->fresh()->payload['title'])->toBe('Новое содержимое');
    expect($row->fresh()->status->value)->toBe('published');
    expect($run->fresh())->toBeNull();
    expect(LearningSession::count())->toBe(2);
});

it('deletes published lesson runs and sources but retains personal words', function () {
    [, $receipt] = adminImport($this);
    $row = LearningSession::findOrFail($receipt['version_ids'][0]);
    $row->update(['status' => 'published', 'published_at' => now()]);
    $run = LearningRun::factory()->create(['user_id' => $this->admin->id, 'session_id' => $row->id]);
    $word = \App\Models\Learning\PersonalVocabulary::factory()->create(['user_id' => $this->admin->id]);
    \App\Models\Learning\PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id, 'user_id' => $this->admin->id, 'session_id' => $row->id]);
    $this->postJson('/admin/daily-practice/manage/sessions/'.$row->id.'/delete', ['confirmed' => true])->assertOk();
    $this->assertDatabaseMissing('learning_runs', ['id' => $run->id]);
    $this->assertDatabaseHas('personal_vocabulary', ['id' => $word->id]);
});

it('deletes programs with their enrollments', function () {
    $program = \App\Models\Learning\LearningCurriculum::factory()->create();
    $this->actingAs($this->admin)->postJson('/admin/daily-practice/manage/curricula/'.$program->id.'/delete', ['confirmed' => true])->assertOk();
    $this->assertDatabaseMissing('learning_curricula', ['id' => $program->id]);
});


it('imports multiple packages through successive ordinary requests and replaces existing lessons', function () {
    $this->actingAs($this->admin);
    $package = adminPackage();
    $first = $package; $first['sessions'] = [$package['sessions'][0]];
    $first['sessions'][0]['public_page']['related_session_ids'] = [];
    $second = $package; $second['sessions'] = [$package['sessions'][1]];
    $upload = fn ($p) => $this->post('/admin/daily-practice/imports/bulk', ['file' => UploadedFile::fake()->createWithContent('package.json', json_encode($p))], ['Accept' => 'application/json']);
    $one = $upload($first)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
    $upload($second)->assertOk();
    expect(LearningSession::count())->toBe(2);
    $first['sessions'][0]['title'] = 'Массовое обновление';
    $again = $upload($first)->assertOk()->json();
    expect($again['updated_version_ids'])->toBe($one['version_ids'])->and($again['created_version_ids'])->toBe([]);
    expect(LearningSession::count())->toBe(2);
    expect(LearningSession::find($one['version_ids'][0])->payload['title'])->toBe('Массовое обновление');
    expect(\App\Models\Learning\LearningCatalogRevision::count())->toBe(2);
});

it('rejects companion documents on the server and does not partially import invalid packages', function () {
    $this->actingAs($this->admin)->post('/admin/daily-practice/imports/bulk', ['file' => UploadedFile::fake()->createWithContent('notes.json', '{"checks":{}}')], ['Accept' => 'application/json'])->assertUnprocessable();
    $p = adminPackage(); $p['sessions'][1]['tasks'][0]['type'] = 'invalid_type';
    $this->post('/admin/daily-practice/imports/bulk', ['file' => UploadedFile::fake()->createWithContent('bad.json', json_encode($p))], ['Accept' => 'application/json'])->assertUnprocessable();
    expect(LearningSession::count())->toBe(0);
});

it('forbids bulk imports by a non administrator', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/admin/daily-practice/imports/bulk', ['file' => UploadedFile::fake()->createWithContent('package.json', json_encode(adminPackage()))], ['Accept' => 'application/json'])->assertForbidden();
    expect(LearningSession::count())->toBe(0);
});


it('accepts bulk file bodies above the ordinary operation limit but still enforces the import limit', function () {
    $this->actingAs($this->admin);
    $json = str_repeat(' ', config('daily_practice.operations.request_bytes') + 1).json_encode(adminPackage());
    $size = strlen($json) + 1024; // Multipart envelope as reported by a real browser.
    expect($size)->toBeGreaterThan(config('daily_practice.operations.request_bytes'))->toBeLessThan(config('daily_practice.import.max_bytes'));
    $this->post('/admin/daily-practice/imports/bulk', [
        'file' => UploadedFile::fake()->createWithContent('large-package.json', $json),
    ], ['Accept' => 'application/json', 'Content-Length' => (string) $size])->assertOk();
    expect(LearningSession::count())->toBe(2);

    $this->postJson('/admin/daily-practice/sessions/status', [], ['Content-Length' => (string) $size])->assertStatus(413);
    $this->post('/admin/daily-practice/imports/bulk', [
        'file' => UploadedFile::fake()->createWithContent('package.json', json_encode(adminPackage())),
    ], ['Accept' => 'application/json', 'Content-Length' => (string) (config('daily_practice.import.max_bytes') + 65537)])->assertStatus(413);
});


it('keeps application imports available without the ignored authoring bank', function () {
    config(['daily_practice.content_path' => storage_path('testing/absent-authoring-bank')]);
    adminImport($this);
    expect(LearningSession::count())->toBe(2);
    $this->get('/admin/daily-practice/templates/pre-a1-u01')->assertOk();
    $this->get('/admin/daily-practice/templates/pre-a1-u01-v2')->assertNotFound();
    $this->artisan('daily-practice:content-status')->assertExitCode(1);
});


it('imports edits previews and exports twelve tasks and drops the old unfinished attempt', function () {
    [, $receipt] = adminImport($this);
    $row = LearningSession::findOrFail($receipt['version_ids'][0]);
    $run = LearningRun::factory()->create(['user_id' => $this->admin->id, 'session_id' => $row->id]);
    $expanded = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/twelve-task-package.json')), true);
    $training = $expanded['sessions'][0]['tasks'][0];
    $training['instruction'] = 'Выберите реплику для новой ситуации.';
    $expanded['sessions'][0]['training_tasks'] = ['dialogue' => [$training], 'recall' => []];
    [, $updated] = adminImport($this, $expanded);
    expect($updated['version_ids'][0])->toBe($row->id)
        ->and($run->fresh())->toBeNull()
        ->and($row->fresh()->payload['tasks'])->toHaveCount(12);
    $content = $row->fresh()->payload;
    $content['tasks'][11]['instruction'] = 'Обновлённый двенадцатый вопрос';
    $saved = $this->postJson('/admin/daily-practice/sessions/'.$row->id.'/save', ['content' => $content, 'checksum' => $row->fresh()->checksum]);
    $saved->assertOk();
    $this->get('/admin/daily-practice/sessions/'.$row->id)->assertInertia(fn (Assert $p) => $p
        ->has('version.payload.tasks', 12)->where('version.payload.tasks.11.instruction', $content['tasks'][11]['instruction']));
    $this->get('/admin/daily-practice/sessions/'.$row->id.'/preview')->assertOk();
    $export = $this->getJson('/admin/daily-practice/sessions/'.$row->id.'/export')->assertOk()
        ->assertJsonPath('schema_version', 1)->assertJsonPath('sessions.0.version', 1)
        ->assertJsonCount(12, 'sessions.0.tasks')->json();
    expect($export['sessions'][0])->toBe($content)
        ->and($run->fresh())->toBeNull()
        ->and(LearningSession::count())->toBe(2);
    $this->getJson('/admin/daily-practice/imports/'.$updated['batch_id'].'/export')->assertOk()->assertJsonCount(12, 'sessions.0.tasks');
});
