<?php

use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\CatalogPublicationService;
use App\Services\Learning\ContentAccess;
use App\Services\Learning\ContentOperationException;
use App\Services\Learning\PracticeUrls;
use App\Services\Learning\SessionImportService;
use App\Services\Learning\SessionPublicationService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true, 'app.url' => 'http://localhost:5000']);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    Storage::fake('learning_private');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->editor = User::factory()->create(['rang_id' => $rank->id]);
});

it('accepts the real five-session package through publication and public HTML without learner effects', function () {
    if (!is_file(config('daily_practice.content_path').'/programme/packages/PRE_A1/PRE_A1-U01.v1.json')) $this->markTestSkipped('Local learning bank is not installed.');
    $package = json_decode(file_get_contents((config('daily_practice.content_path').'/programme/packages/PRE_A1/PRE_A1-U01.v1.json')), true);
    $importer = app(SessionImportService::class);
    $preview = $importer->preview($this->editor, UploadedFile::fake()->createWithContent('first-topic.json', CanonicalJson::encode($package)));
    $receipt = $importer->commit($this->editor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
    expect($receipt['created_version_ids'])->toHaveCount(5);
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    $publisher = app(SessionPublicationService::class);
    foreach ($receipt['created_version_ids'] as $id) {
        $publisher->review($this->editor, $id, $checks);
    }
    expect(fn () => $publisher->publishMany($this->editor, $receipt['created_version_ids']))->toThrow(ContentOperationException::class);
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($this->editor, $id, $checks);
        app(CatalogPublicationService::class)->publish($this->editor, $id);
    }
    $publisher->publishMany($this->editor, $receipt['created_version_ids']);
    $this->getJson('/daily/api/content')->assertOk()->assertJsonCount(5, 'sessions');
    foreach (LearningSession::with('unit')->get() as $session) {
        $this->get(PracticeUrls::session($session))->assertOk()->assertSee($session->payload['title'])
            ->assertHeader('X-Robots-Tag', 'index, follow')->assertDontSee('correct_choice_id');
        $this->getJson('/daily/api/content/'.$session->external_id)->assertOk()->assertJsonCount(count($session->payload['tasks']), 'snapshot.tasks');
    }
    $again = $importer->preview($this->editor, UploadedFile::fake()->createWithContent('formatted.json', json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
    $replayed = $importer->commit($this->editor, $again['batch_id'], $again['token'], $again['file_hash']);
    expect($replayed['created_version_ids'])->toBe([])->and($replayed['updated_version_ids'])->toHaveCount(5);
    expect(LearningProfile::count())->toBe(0)->and(LearningRun::count())->toBe(0);
    $this->assertDatabaseCount('personal_vocabulary', 0);
});

it('publishes an explicitly reviewed browser TTS checkpoint under the agreed audio policy', function () {
    $package = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true);
    $package['sessions'][0]['kind'] = 'checkpoint';
    $importer = app(SessionImportService::class);
    $preview = $importer->preview($this->editor, UploadedFile::fake()->createWithContent('checkpoint.json', CanonicalJson::encode($package)));
    $receipt = $importer->commit($this->editor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($this->editor, $id, $checks);
        app(CatalogPublicationService::class)->publish($this->editor, $id);
    }
    $publisher = app(SessionPublicationService::class);
    foreach ($receipt['created_version_ids'] as $id) {
        $publisher->review($this->editor, $id, $checks);
    }
    $publisher->publishMany($this->editor, $receipt['created_version_ids']);
    $this->getJson('/daily/api/content/'.$package['sessions'][0]['id'])->assertOk()->assertJsonPath('snapshot.kind', 'checkpoint');
    $this->assertDatabaseCount('learning_media', 0);
});

it('keeps existing lessons readable and unchanged when practice is disabled or enabled', function () {
    $lesson = App\Models\Lesson::create(['title' => 'Независимый урок', 'description' => 'Описание старого урока', 'content' => '<p>Содержание урока</p>']);
    foreach ([false, true] as $enabled) {
        config(['daily_practice.enabled' => $enabled]);
        $this->get('/lessons')->assertOk()->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Lessons/Lessons', false)->where('menu.0.title', $lesson->title));
        $this->get('/lessons/item/'.$lesson->id)->assertOk()->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Lessons/LessonShow', false)->where('lessonContent.content', $lesson->content));
    }
    expect($lesson->fresh()->content)->toBe('<p>Содержание урока</p>')->and(LearningRun::count())->toBe(0);
});

it('accepts a twelve-step import through learner results, version replacement and review', function () {
    $package = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/twelve-task-package.json')), true, flags: JSON_THROW_ON_ERROR);
    [$first, $second] = $package['sessions'];
    $learner = User::factory()->create();
    $admin = '/admin/daily-practice';
    $upload = fn (array $content) => $this->post($admin.'/imports/preview', [
        'file' => UploadedFile::fake()->createWithContent('pilot.json', CanonicalJson::encode($content)),
    ], ['Accept' => 'application/json']);
    $import = function (array $content) use ($admin, $upload): array {
        $preview = $upload($content)->assertCreated()->json();

        return $this->postJson($admin.'/imports/'.$preview['batch_id'].'/commit', [
            'token' => $preview['token'], 'file_hash' => $preview['file_hash'],
        ])->assertOk()->json();
    };

    $this->actingAs($learner);
    $upload($package)->assertForbidden();
    $this->actingAs($this->editor);
    $receipt = $import($package);
    $checks = ['checks' => array_fill_keys(ContentAccess::REVIEW_CHECKS, true)];
    foreach ($receipt['catalog_revision_ids'] as $id) {
        $this->postJson($admin.'/catalog/'.$id.'/review', $checks)->assertOk();
        $this->postJson($admin.'/catalog/'.$id.'/publish')->assertOk();
    }
    foreach ($receipt['created_version_ids'] as $id) {
        $this->postJson($admin.'/sessions/'.$id.'/review', $checks)->assertOk();
    }
    $this->postJson($admin.'/sessions/publish', ['version_ids' => $receipt['created_version_ids']])->assertOk();

    $this->actingAs($learner);
    $this->postJson($admin.'/sessions/'.$receipt['created_version_ids'][0].'/publish')->assertForbidden();
    $this->get(PracticeUrls::session(LearningSession::where('external_id', $first['id'])->sole()))
        ->assertOk()->assertSee($first['title']);
    $start = fn (array $input) => $this->postJson('/daily/api/runs', [
        'client_event_id' => (string) Str::uuid(), 'expected_revision' => LearningProfile::where('user_id', $learner->id)->value('revision') ?? 1, ...$input,
    ])->assertOk()->json('state');
    $command = fn (array $state, string $action, array $input = []) => $this->postJson('/daily/api/runs/'.$state['run_id'].'/'.$action, [
        'client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], ...$input,
    ])->assertOk()->json('state');
    $complete = function (array $state, bool $pilot = false) use ($command, $first): array {
        for ($index = 0; $index < $state['total']; $index++) {
            $step = ['presentation_id' => $state['presentation']['presentation_id']];
            if ($pilot) {
                expect($state['task']['id'])->toBe($first['tasks'][$index]['id']);
            }
            if ($pilot && in_array($index, [0, 8], true)) {
                if ($state['introductions']) {
                    $state = $command($state, 'introduction', $step);
                }
                if ($index === 8) {
                    $state = $command($state, 'help', [...$step, 'event_type' => 'help', 'help_kind' => 'audio_transcript']);
                    expect($state['presentation']['help_used'])->toBeTrue();
                }
                $state = $command($state, 'answers', [
                    ...$step, 'event_type' => 'answer', 'answer' => ['choice_id' => $first['tasks'][$index]['correct_choice_id']],
                ]);
            } else {
                $state = $command($state, 'help', [...$step, 'event_type' => 'unknown']);
            }
            if ($index + 1 < $state['total']) {
                $state = $command($state, 'advance', $step);
            }
        }

        return $command($state, 'finish');
    };

    $old = $start(['kind' => 'daily']);
    expect($old['total'])->toBe(12);
    $package['sessions'][0]['title'] .= ' (обновлено)';
    $this->actingAs($this->editor);
    $updated = $import($package);
    expect($updated['created_version_ids'])->toBe([]);
    $session = LearningSession::where('external_id', $first['id'])->sole();
    expect($session->status->value)->toBe('published')
        ->and($session->payload['title'])->toBe($package['sessions'][0]['title']);

    $this->actingAs($learner);
    $this->getJson('/daily/api/runs/'.$old['run_id'])->assertNotFound();
    $finished = $complete($start(['kind' => 'daily']), true);
    expect($finished['finished'])->toBeTrue()
        ->and($finished['summary']['score_denominator'])->toBe(12)
        ->and($finished['summary']['score_numerator'])->toBe(1);
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate.id', $second['id']);
    expect($complete($start(['kind' => 'extra', 'session_id' => $second['id']]))['finished'])->toBeTrue();
    $this->getJson('/daily/api/vocabulary/review-plan?mode=manual')->assertOk();
    expect($complete($start(['kind' => 'review', 'review_mode' => 'manual']))['finished'])->toBeTrue();
    expect($this->getJson('/daily/api/vocabulary')->assertOk()->json('pagination.total'))->toBeGreaterThan(0);

    $words = fn () => PersonalVocabulary::where('user_id', $learner->id)->orderBy('id')->get()->toArray();
    $before = $words();
    $this->actingAs($this->editor);
    $reimport = $import($package);
    expect($reimport['created_version_ids'])->toBe([])
        ->and($reimport['updated_version_ids'])->toBe($updated['updated_version_ids'])
        ->and($session->fresh()->status->value)->toBe('published')
        ->and($words())->toBe($before);
    $this->assertDatabaseCount('learning_sessions', 2);
    $this->assertDatabaseCount('learning_sessions', 2);
    $this->assertDatabaseCount('learning_runs', 3);

    $this->actingAs($learner);
    $new = $start(['kind' => 'extra', 'session_id' => $first['id']]);
    expect(LearningRun::findOrFail($new['run_id'])->snapshot['title'])->toBe($package['sessions'][0]['title']);
    $this->getJson('/daily/api/runs/'.$finished['run_id'])->assertOk()->assertJsonPath('summary', $finished['summary']);
});
