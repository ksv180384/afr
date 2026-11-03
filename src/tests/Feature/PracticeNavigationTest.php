<?php

use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
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
    $payload = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    $session = LearningSession::factory()->create(['external_id' => $payload['id'], 'unit_id' => $unit->id]);
    $this->version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $payload, 'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')]);
    $session->update(['active_version_id' => $this->version->id]);
});
function practiceReportInput($test, array $extra = []): array
{
    return ['client_report_id' => (string) Str::uuid(), 'session_version_id' => $test->version->id, 'task_id' => 'Q01', 'text' => 'Проверьте формулировку задания.', 'include_answer' => false, ...$extra];
}

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
    $draft = LearningSessionVersion::factory()->create(['session_id' => $this->version->session_id, 'version' => 2, 'payload' => $this->version->payload]);
    $this->postJson('/daily/api/reports', practiceReportInput($this, ['session_version_id' => $draft->id]))->assertNotFound();
    $this->version->session->unit->update(['archived_at' => now('UTC')]);
    $this->postJson('/daily/api/reports', practiceReportInput($this))->assertNotFound();
    expect(LearningContentReport::count())->toBe(0);
});

it('allows an owner to report their archived run and hides another owners run before storing a report', function () {
    $owner = User::factory()->create();
    $service = app(AccountRunService::class);
    $state = $service->start($owner, ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])['state'];
    $this->version->session->update(['archived_at' => now('UTC')]);
    $input = practiceReportInput($this, ['run_id' => $state['run_id']]);
    $this->actingAs(User::factory()->create())->postJson('/daily/api/reports', $input)->assertNotFound();
    $this->actingAs($owner)->postJson('/daily/api/reports', $input)->assertOk();
    expect(LearningContentReport::sole()->user_id)->toBe($owner->id);
    $other = LearningSessionVersion::factory()->create();
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
    $word = \App\Models\Learning\PersonalVocabulary::factory()->create(['user_id' => $owner->id, 'lexeme_key' => $snapshot['lexeme_key'], 'snapshot' => $snapshot]);
    $word->sources()->create(['user_id' => $owner->id, 'session_version_id' => $this->version->id, 'vocabulary_id' => $snapshot['id'], 'snapshot' => $snapshot, 'first_added_at' => now('UTC')]);
    $this->version->session->update(['archived_at' => now('UTC')]);
    $input = practiceReportInput($this, ['task_id' => null]);
    $this->actingAs(User::factory()->create())->postJson('/daily/api/reports', $input)->assertNotFound();
    $this->actingAs($owner)->postJson('/daily/api/reports', $input)->assertOk();
    expect(LearningContentReport::sole()->user_id)->toBe($owner->id)->and(LearningContentReport::sole()->answer_snapshot)->toBeNull();
});
