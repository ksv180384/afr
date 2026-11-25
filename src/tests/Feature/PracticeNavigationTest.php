<?php

use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['published_at' => now('UTC')]);
    $payload = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    $session = LearningSession::factory()->create(['external_id' => $payload['id'], 'unit_id' => $unit->id]);
    $this->version = lessonWithContent(['session_id' => $session->id, 'payload' => $payload, 'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')]);
});
function practiceReportInput($test, array $extra = []): array
{
    return ['client_report_id' => (string) Str::uuid(), 'session_version_id' => $test->version->id, 'task_id' => 'Q01', 'text' => 'Проверьте формулировку задания.', 'include_answer' => false, ...$extra];
}

it('selects training questions from published and introduced material without creating a lesson run', function () {
    $source = $this->version->payload;
    $task = $source['tasks'][0];
    $task['id'] = 'T1';
    $source['training_tasks'] = ['recall' => [$task]];
    $this->version->update(['payload' => $source, 'checksum' => CanonicalJson::hash($source)]);
    $input = ['mode' => 'recall', 'current' => $source['id']];
    $this->postJson('/daily/api/training', $input)->assertOk()->assertJsonCount(0, 'items');
    $this->postJson('/daily/api/training', [...$input, 'seen' => $task['vocabulary_ids']])
        ->assertOk()->assertJsonPath('items.0.task.id', 'T1');
    $this->postJson('/daily/api/training', ['mode' => 'dialogue', 'completed' => [$source['id']]])
        ->assertOk()->assertJsonCount(0, 'items');
    $this->actingAs(User::factory()->create());
    $this->postJson('/daily/api/training', [...$input, 'completed' => [$source['id']]])
        ->assertOk()->assertJsonCount(0, 'items');
    expect(LearningRun::count())->toBe(0);
});

it('uses answered steps and seen introductions from a linked account lesson for training', function () {
    $source = $this->version->payload;
    $first = $source['tasks'][0];
    $source['training_tasks']['recall'] = [
        [...$first, 'id' => 'answered', 'vocabulary_ids' => ['bonjour']],
        [...$first, 'id' => 'introduced', 'vocabulary_ids' => ['salut']],
        [...$first, 'id' => 'unseen', 'vocabulary_ids' => ['je-mappelle']],
    ];
    $this->version->update(['payload' => $source, 'checksum' => CanonicalJson::hash($source)]);
    $this->actingAs(User::factory()->create());
    $state = $this->postJson('/daily/api/runs', ['kind' => 'daily', 'expected_revision' => 1,
        'client_event_id' => (string) Str::uuid()])->assertOk()->json('state');
    $url = '/daily/api/runs/'.$state['run_id'];
    $step = ['presentation_id' => $state['presentation']['presentation_id']];
    expect(LearningRun::findOrFail($state['run_id'])->snapshot)->toBeNull();
    $this->postJson('/daily/api/training', ['mode' => 'recall'])->assertOk()->assertJsonCount(0, 'items');

    $state = $this->postJson($url.'/answers', $step + ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'],
        'event_type' => 'answer', 'answer' => ['choice_id' => $first['correct_choice_id']]])->assertOk()->json('state');
    $this->postJson('/daily/api/training', ['mode' => 'recall'])->assertOk()
        ->assertJsonCount(1, 'items')->assertJsonPath('items.0.task.id', 'answered');
    $state = $this->postJson($url.'/introduction', $step + ['client_event_id' => (string) Str::uuid(),
        'expected_revision' => $state['revision']])->assertOk()->json('state');
    $this->postJson($url.'/pause', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision']])->assertOk();
    $items = $this->postJson('/daily/api/training', ['mode' => 'recall', 'unit' => $this->version->unit->external_id])
        ->assertOk()->json('items');
    expect(array_column(array_column($items, 'task'), 'id'))->toEqualCanonicalizing(['answered', 'introduced']);
});

it('builds topic training only from completed lessons and introduced current material', function () {
    $unit = $this->version->unit;
    $unit->update(['sort_order' => 1]);
    $otherUnit = LearningUnit::factory()->create(['sort_order' => 2, 'published_at' => now('UTC')]);
    $payload = $this->version->payload;
    $task = $payload['tasks'][0];
    $payload['training_tasks'] = [
        'recall' => [[...$task, 'id' => 'R1'], [...$task, 'id' => 'R2']],
        'dialogue' => [[...$task, 'id' => 'D1']],
    ];
    $this->version->update(['payload' => $payload, 'checksum' => CanonicalJson::hash($payload)]);
    $sources = [$payload['id']];
    foreach (range(2, 7) as $index) {
        $source = [...$payload, 'id' => 'TRAINING-LESSON-'.$index];
        $lesson = LearningSession::factory()->create([
            'external_id' => $source['id'],
            'unit_id' => $index === 6 ? $otherUnit->id : $unit->id,
            'archived_at' => $index === 7 ? now('UTC') : null,
        ]);
        $version = lessonWithContent([
            'session_id' => $lesson->id, 'payload' => $source, 'checksum' => CanonicalJson::hash($source),
            'status' => 'published', 'published_at' => now('UTC'),
        ]);
        if ($index <= 5) {
            $sources[] = $source['id'];
        }
    }

    $recall = $this->postJson('/daily/api/training', ['unit' => $unit->external_id, 'mode' => 'recall',
        'completed' => $sources, 'previous' => array_map(fn ($source) => $source.'/R1', $sources)])
        ->assertOk()->assertJsonCount(6, 'items')->json('items');
    expect(array_unique(array_column($recall, 'source')))->toEqualCanonicalizing($sources);
    $fresh = array_filter($recall, fn ($item) => $item['task']['id'] === 'R2');
    expect(array_column($fresh, 'source'))->toEqualCanonicalizing($sources);

    $nextTraining = ['unit' => $otherUnit->external_id, 'mode' => 'dialogue'];
    $this->postJson('/daily/api/training', $nextTraining)->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->postJson('/daily/api/training', [...$nextTraining, 'current_level' => 'PRE_A1', 'completed' => $sources])
        ->assertOk()->assertJsonCount(0, 'items');

    $partial = ['unit' => $unit->external_id, 'mode' => 'recall', 'current' => $sources[0]];
    $this->postJson('/daily/api/training', $partial)->assertOk()->assertJsonCount(0, 'items');
    $introduced = $this->postJson('/daily/api/training', [...$partial, 'seen' => $task['vocabulary_ids']])
        ->assertOk()->assertJsonCount(2, 'items')->json('items');
    expect(array_unique(array_column($introduced, 'source')))->toBe([$sources[0]]);

    $actor = User::factory()->create();
    $this->actingAs($actor);
    $this->postJson('/daily/api/training', [...$nextTraining, 'current_level' => 'A1', 'completed' => $sources])->assertForbidden();
    $this->postJson('/daily/api/training', ['unit' => $unit->external_id, 'mode' => 'dialogue', 'completed' => $sources])
        ->assertOk()->assertJsonCount(0, 'items');
    expect(LearningRun::count())->toBe(0);
    $this->postJson('/daily/api/training', ['unit' => 'UNKNOWN', 'mode' => 'recall'])->assertOk()->assertJsonCount(0, 'items');

    $versions = LearningSession::whereIn('external_id', $sources)->pluck('id');
    foreach ($versions as $versionId) {
        LearningRun::factory()->create(['user_id' => $actor->id, 'session_id' => $versionId,
            'status' => 'completed', 'finished_at' => now('UTC')]);
    }
    $this->postJson('/daily/api/training', $nextTraining)->assertOk()->assertJsonCount(0, 'items');
    $dialogue = $this->postJson('/daily/api/training', ['unit' => $unit->external_id, 'mode' => 'dialogue'])
        ->assertOk()->assertJsonCount(5, 'items')->json('items');
    expect(array_column($dialogue, 'source'))->toEqualCanonicalizing($sources);
});

it('opens guest and unverified progress shells without creating account state', function () {
    $this->get('/daily/progress')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page->component('Daily/Index', false)->where('screen', 'progress')->where('enabled', true));
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/daily/progress')->assertOk();
    $this->get('/profile')->assertRedirect(route('verification.notice'));
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate.duration_minutes', 5);
    expect(LearningProfile::count())->toBe(0)->and(LearningRun::count())->toBe(0);
});

it('keeps the feature flag closed for the new shell and authenticated reports', function () {
    $this->actingAs(User::factory()->create());
    config(['daily_practice.enabled' => false]);
    $this->get('/daily/progress')->assertOk()->assertInertia(fn (Assert $page) => $page->where('enabled', false));
    $this->postJson('/daily/api/reports', practiceReportInput($this))->assertStatus(503)->assertJsonPath('code', 'disabled');
    expect(LearningContentReport::count())->toBe(0);
});

it('rejects guest reports before validating content or creating a report', function () {
    $this->postJson('/daily/api/reports', practiceReportInput($this))->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    $this->postJson('/daily/api/reports', [])->assertUnauthorized();
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['user_id' => 1, 'include_answer' => true, 'answer' => ['value' => 'bonjour']]))->assertUnauthorized();
    $this->post('/daily/api/reports', practiceReportInput($this), ['Accept' => 'text/html'])->assertUnauthorized();
    expect(LearningContentReport::count())->toBe(0)->and(User::count())->toBe(0)->and(LearningRun::count())->toBe(0);
});
it('accepts authenticated reports without answers and replays a lost receipt once', function () {
    $reporter = User::factory()->create();
    $this->actingAs($reporter);
    $input = practiceReportInput($this);
    $first = $this->postJson('/daily/api/reports', $input)->assertOk()->json();
    $this->postJson('/daily/api/reports', $input)->assertOk()->assertExactJson($first);
    $this->postJson('/daily/api/reports', [...$input, 'text' => 'Другое содержимое'])->assertStatus(409);
    $report = LearningContentReport::sole();
    expect($report->answer_snapshot)->toBeNull()->and($report->user_id)->toBe($reporter->id)->and($report->task_id)->toBe('Q01');
    expect(User::count())->toBe(1)->and(LearningRun::count())->toBe(0);
});

it('attaches only an explicitly opted in answer and rejects hidden answer payloads and malformed answers', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['answer' => ['value' => 'личный ответ']]))->assertUnprocessable();
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['include_answer' => true, 'answer' => 'invalid']))->assertUnprocessable();
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['include_answer' => true, 'answer' => ['secret' => 'no']]))->assertUnprocessable();
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['include_answer' => true, 'answer' => ['value' => '  À bientôt !  ']]))->assertOk();
    expect(LearningContentReport::sole()->answer_snapshot)->toBe(['value' => '  À bientôt !  ']);
});

it('validates the exact version and step and refuses draft and hidden catalog reports', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['task_id' => 'MISSING']))->assertUnprocessable();
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['user_id' => 123]))->assertUnprocessable();
    $draft = lessonWithContent(['payload' => $this->version->payload]);
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['session_version_id' => $draft->id]))->assertNotFound();
    $this->version->unit->update(['archived_at' => now('UTC')]);
    $this->postJson('/daily/api/reports', practiceReportInput($this))->assertNotFound();
    expect(LearningContentReport::count())->toBe(0);
});

it('allows an owner to report their archived run and hides another owners run before storing a report', function () {
    $owner = User::factory()->create();
    $service = app(AccountRunService::class);
    $state = $service->start($owner, ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])['state'];
    $this->version->update(['archived_at' => now('UTC')]);
    $input = practiceReportInput($this, ['run_id' => $state['run_id']]);
    $this->actingAs(User::factory()->create())->postJson('/daily/api/reports', $input)->assertNotFound();
    $this->actingAs($owner)->postJson('/daily/api/reports', $input)->assertOk();
    expect(LearningContentReport::sole()->user_id)->toBe($owner->id);
    $other = lessonWithContent();
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['run_id' => $state['run_id'], 'session_version_id' => $other->id]))->assertNotFound();
});

it('uses real CSRF validation and limits authenticated reporting without writing a rejected message', function () {
    $this->actingAs(User::factory()->create());
    $this->app->detectEnvironment(fn () => 'local');
    $this->post('/daily/api/reports', practiceReportInput($this), ['Accept' => 'text/html'])->assertStatus(419)->assertJsonPath('code', 'session_expired');
    $this->app->detectEnvironment(fn () => 'testing');
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/daily/api/reports', practiceReportInput($this))->assertOk();
    }
    $this->postJson('/daily/api/reports', practiceReportInput($this))->assertStatus(429);
    expect(LearningContentReport::count())->toBe(6);
});

it('exposes practice settings only behind the flag on the existing verified profile', function () {
    \App\Models\Proverb::create(['text' => 'Bonjour', 'translation' => 'Приветствие']);
    $this->actingAs(User::factory()->create())->get('/profile')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profile/Edit', false)->where('daily_practice_enabled', true));
    expect(LearningProfile::count())->toBe(0);
    $this->get('/daily', ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()), 'Referer' => url('/profile')])->assertOk()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Daily/Index');
});

it('accepts reports for an owners saved vocabulary source after archival without exposing it to another account', function () {
    $owner = User::factory()->create();
    $snapshot = $this->version->payload['vocabulary'][0];
    $word = \App\Models\Learning\PersonalVocabulary::factory()->studied($snapshot, $this->version)->create(['user_id' => $owner->id]);
    $this->version->update(['archived_at' => now('UTC')]);
    $input = practiceReportInput($this, ['task_id' => null]);
    $this->actingAs(User::factory()->create())->postJson('/daily/api/reports', $input)->assertNotFound();
    $this->actingAs($owner)->postJson('/daily/api/reports', $input)->assertOk();
    expect(LearningContentReport::sole()->user_id)->toBe($owner->id)->and(LearningContentReport::sole()->answer_snapshot)->toBeNull();
});
