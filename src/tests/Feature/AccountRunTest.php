<?php

use App\Models\Learning\DailyAssignment;
use App\Models\Learning\LearningActivityDay;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $package = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['external_id' => $package['units'][0]['id'], 'sort_order' => 1, 'published_at' => now('UTC')]);
    $this->versions = [];
    foreach ($package['sessions'] as $session) {
        $row = LearningSession::factory()->create(['external_id' => $session['id'], 'unit_id' => $unit->id, 'sort_order' => $session['order']]);
        $version = LearningSessionVersion::factory()->create(['session_id' => $row->id, 'payload' => $session, 'checksum' => CanonicalJson::hash($session), 'status' => 'published', 'published_at' => now('UTC')]);
        $row->update(['active_version_id' => $version->id]);
        $this->versions[] = $version;
    }
    $this->actingAs($this->learner);
});

function accountInput(int $revision = 1, array $extra = []): array
{
    return ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $revision, ...$extra];
}

function accountStart($test, array $extra = []): array
{
    return $test->postJson('/daily/api/runs', accountInput(1, ['kind' => 'daily', ...$extra]))->assertOk()->json('state');
}

function accountCommand($test, array $state, string $command, array $extra = []): array
{
    return $test->postJson('/daily/api/runs/'.$state['run_id'].'/'.$command, accountInput($state['revision'], $extra))->assertOk()->json('state');
}

function accountComplete($test, array $state): array
{
    while (true) {
        $state = accountCommand($test, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
        if ($state['position'] === $state['total']) {
            break;
        }
        $state = accountCommand($test, $state, 'advance', ['presentation_id' => $state['presentation']['presentation_id']]);
    }

    return $state;
}

it('reads today profile shell and history without creating learning state', function () {
    $this->get('/daily')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate.id', 'PRE_A1-U01-L01');
    $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('persisted', false);
    $this->getJson('/daily/api/history')->assertOk();
    foreach (['learning_profiles', 'daily_assignments', 'learning_runs', 'learning_presentations', 'learning_run_operations', 'learning_activity_days'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

it('pins a single daily run on duplicate starts and advances only by explicit operation', function () {
    $input = accountInput(1, ['kind' => 'daily', 'timezone' => 'Europe/Moscow']);
    $first = $this->postJson('/daily/api/runs', $input)->assertOk()->json();
    $this->postJson('/daily/api/runs', $input)->assertExactJson($first);
    $next = accountStart($this);
    expect($next['run_id'])->toBe($first['state']['run_id']);
    expect(LearningRun::count())->toBe(1);
    expect($next['timezone'])->toBe('Europe/Moscow');
    $this->assertDatabaseCount('learning_presentations', 6);
    expect($next['task'])->not->toHaveKey('correct_choice_id');
    $this->postJson('/daily/api/runs/'.$next['run_id'].'/advance', accountInput($next['revision'], ['presentation_id' => $next['presentation']['presentation_id']]))->assertUnprocessable();
    $this->postJson('/daily/api/runs', [...$input, 'kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01'])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
});

it('records help before grading and recovers the same answer receipt before revision checks', function () {
    $state = accountStart($this);
    $state = accountCommand($this, $state, 'introduction', ['presentation_id' => $state['presentation']['presentation_id']]);
    expect($state['presentation']['help_used'])->toBeFalse();
    $state = accountCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'help', 'help_kind' => 'audio_transcript']);
    expect($state['assistance'])->toContain('audio_transcript');
    $input = accountInput($state['revision'], ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'b']]);
    $receipt = $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', $input)->assertOk()->json();
    expect($receipt['state']['presentation']['first_result']['score'])->toBe(0);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', $input)->assertExactJson($receipt);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', [...$input, 'client_event_id' => (string) Str::uuid()])->assertConflict()->assertJsonPath('revision', $receipt['state']['revision']);
    $loaded = $this->getJson('/daily/api/runs/'.$state['run_id'])->assertOk()->json();
    expect($loaded['position'])->toBe(1);
    expect(LearningAttempt::count())->toBe(2);
});

it('finishes once using server time and preserves the receipt after midnight and lost acknowledgement', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-24 20:55:00', 'UTC'));
    $state = accountStart($this, ['timezone' => 'Europe/Moscow']);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', accountInput($state['revision']))->assertUnprocessable();
    $state = accountComplete($this, $state);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-24 21:05:00', 'UTC'));
    $input = accountInput($state['revision']);
    $receipt = $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $input)->assertOk()->json();
    expect($receipt['state']['finished'])->toBeTrue();
    expect($receipt['state']['summary']['independent_correct_steps'])->toBe(0);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-26 21:05:00', 'UTC'));
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $input)->assertExactJson($receipt);
    expect(LearningActivityDay::sole()->local_date->format('Y-m-d'))->toBe('2026-10-25');
    expect(DailyAssignment::sole()->local_date->format('Y-m-d'))->toBe('2026-10-24');
    $this->assertDatabaseCount('personal_vocabulary', 6);
    $this->travelBack();
});

it('preserves unfinished snapshots through midnight profile changes new publication and pause resume', function () {
    $state = accountStart($this);
    $snapshot = LearningRun::find($state['run_id'])->snapshot;
    $profileInput = accountInput(1, ['current_level' => 'A1', 'timezone' => 'Asia/Tokyo']);
    $this->patchJson('/daily/api/profile', $profileInput)->assertOk()->assertJsonPath('profile.revision', 2);
    $this->travel(2)->days();
    $again = $this->postJson('/daily/api/runs', accountInput(2, ['kind' => 'daily']))->assertOk()->json('state');
    expect($again['run_id'])->toBe($state['run_id']);
    $paused = accountCommand($this, $state, 'pause');
    expect($paused['resume'])->toBeTrue();
    $resumed = accountCommand($this, $paused, 'resume');
    expect($resumed['started'])->toBeTrue();
    expect(LearningRun::find($state['run_id'])->snapshot)->toBe($snapshot);
    expect($resumed['timezone'])->toBe($state['timezone']);
    $this->travelBack();
});

it('returns JSON for unauthenticated missing invalid disabled and CSRF expired requests', function () {
    auth()->forgetGuards();
    $this->app['auth']->guard()->logout();
    $this->get('/daily/api/today', ['Accept' => 'text/html'])->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    $this->actingAs($this->learner)->get('/daily/api/unknown', ['Accept' => 'text/html'])->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->postJson('/daily/api/runs', accountInput(1, ['kind' => 'daily', 'score' => 100]))->assertUnprocessable()->assertJsonStructure(['errors']);
    $this->app->detectEnvironment(fn () => 'local');
    $this->post('/daily/api/runs', accountInput(1, ['kind' => 'daily']), ['Accept' => 'text/html'])->assertStatus(419)->assertJsonPath('code', 'session_expired');
    $this->app->detectEnvironment(fn () => 'testing');
    config(['daily_practice.enabled' => false]);
    $this->get('/daily/api/today')->assertStatus(503)->assertJsonPath('code', 'disabled');
});

it('hides another owners run before replay and rejects future presentations', function () {
    $state = accountStart($this);
    $future = LearningRun::find($state['run_id'])->presentations()->where('position', 2)->first();
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/help', accountInput($state['revision'], ['presentation_id' => $future->id, 'event_type' => 'unknown']))->assertConflict();
    $this->actingAs(User::factory()->create());
    $this->getJson('/daily/api/runs/'.$state['run_id'])->assertNotFound();
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', accountInput($state['revision']))->assertNotFound();
});

it('selects the next session after low-score completion and replaces the assignment only explicitly', function () {
    $state = accountStart($this);
    $state = accountComplete($this, $state);
    $finished = accountCommand($this, $state, 'finish');
    $today = $this->getJson('/daily/api/today')->assertOk()->json();
    expect($today['candidate']['id'])->toBe('PRE_A1-U01-L02');
    expect($today['activity_today'])->toBeTrue();
    expect(accountStart($this)['run_id'])->toBe($finished['run_id']);
    $next = accountStart($this, ['replace_assignment' => true]);
    expect($next['session']['id'])->toBe('PRE_A1-U01-L02');
    expect($next['run_id'])->not->toBe($finished['run_id']);
    expect(DailyAssignment::sole()->selected_run_id)->toBe($next['run_id']);
    expect(LearningRun::find($finished['run_id'])->status->value)->toBe('completed');
});

it('pins the started version when the active publication changes', function () {
    $state = accountStart($this);
    $snapshot = LearningRun::find($state['run_id'])->snapshot;
    $payload = $this->versions[0]->payload;
    $payload['version'] = 2;
    $payload['title'] = 'Новая редакция';
    $version = LearningSessionVersion::factory()->create(['session_id' => $this->versions[0]->session_id, 'version' => 2, 'payload' => $payload, 'checksum' => CanonicalJson::hash($payload), 'status' => 'published']);
    $this->versions[0]->session->update(['active_version_id' => $version->id]);
    $loaded = $this->getJson('/daily/api/runs/'.$state['run_id'])->assertOk()->json();
    expect($loaded['session_version_id'])->toBe($this->versions[0]->id);
    expect($loaded['session']['title'])->toBe($snapshot['title']);
    expect(LearningRun::find($state['run_id'])->snapshot)->toBe($snapshot);
});

it('uses the profile level as the starting point and keeps the previous run paused', function () {
    $old = accountStart($this);
    LearningLevel::where('code', 'A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['level' => 'A1', 'published_at' => now('UTC')]);
    $payload = $this->versions[0]->payload;
    $payload['id'] = 'A1-NEW';
    $payload['level'] = 'A1';
    $payload['unit_id'] = $unit->external_id;
    $session = LearningSession::factory()->create(['external_id' => 'A1-NEW', 'level' => 'A1', 'unit_id' => $unit->id]);
    $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $payload, 'checksum' => CanonicalJson::hash($payload), 'status' => 'published']);
    $session->update(['active_version_id' => $version->id]);
    $this->patchJson('/daily/api/profile', accountInput(1, ['current_level' => 'A1', 'timezone' => 'UTC']))->assertOk();
    $selected = $this->postJson('/daily/api/runs', accountInput(2, ['kind' => 'daily']))->assertOk()->json('state');
    expect($selected['run_id'])->toBe($old['run_id']);
    $next = $this->postJson('/daily/api/runs', accountInput(2, ['kind' => 'daily', 'replace_assignment' => true]))->assertOk()->json('state');
    expect($next['session']['level'])->toBe('A1');
    expect(LearningRun::find($old['run_id'])->status->value)->toBe('paused');
    expect(DailyAssignment::sole()->selected_run_id)->toBe($next['run_id']);
});

it('creates explicit extras independently and enforces prerequisites', function () {
    $this->postJson('/daily/api/runs', accountInput(1, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L02']))->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'PRE_A1-U01-L01') && str_contains($message, 'Сначала завершите'));
    $extra = accountStart($this, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01']);
    expect(DailyAssignment::count())->toBe(0);
    $daily = accountStart($this);
    $again = accountStart($this, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01']);
    expect(count(array_unique([$extra['run_id'], $daily['run_id'], $again['run_id']])))->toBe(3);
    expect(DailyAssignment::sole()->selected_run_id)->toBe($daily['run_id']);
});

it('creates frozen review and vocabulary test sets only from owned words', function () {
    $word = $this->versions[0]->payload['vocabulary'][0];
    $personal = \App\Models\Learning\PersonalVocabulary::factory()->create(['user_id' => $this->learner->id, 'lexeme_key' => $word['lexeme_key'], 'snapshot' => $word, 'next_review_at' => now('UTC')->subDay()]);
    $foreign = \App\Models\Learning\PersonalVocabulary::factory()->create();
    $this->postJson('/daily/api/runs', accountInput(1, ['kind' => 'review', 'word_ids' => [$foreign->id]]))->assertNotFound();
    $review = accountStart($this, ['kind' => 'review']);
    expect($review['total'])->toBe(1);
    expect($review['task']['vocabulary_ids'])->toBe(['word-'.$personal->id]);
    expect($review['task'])->not->toHaveKey('correct_choice_id');
    $test = accountStart($this, ['kind' => 'vocabulary_test', 'word_ids' => [$personal->id]]);
    expect($test['run_id'])->not->toBe($review['run_id']);
    $personal->update(['snapshot' => ['changed' => true]]);
    expect($this->getJson('/daily/api/runs/'.$review['run_id'])->assertOk()->json('vocabulary.0.fr'))->toBe($word['fr']);
    $review = accountComplete($this, $review);
    accountCommand($this, $review, 'finish');
    expect(DailyAssignment::count())->toBe(0);
    expect(LearningActivityDay::count())->toBe(1);
});

it('rolls back answers and future integration effects atomically and never reapplies accepted effects', function () {
    $personal = \App\Models\Learning\PersonalVocabulary::factory()->create(['user_id' => $this->learner->id]);
    $hook = new class($personal->id) extends \App\Services\Learning\RunIntegrations
    {
        public bool $fail = true;

        public function __construct(public int $word) {}

        public function accepted(LearningRun $run, LearningAttempt $attempt): void
        {
            \App\Models\Learning\PersonalVocabulary::whereKey($this->word)->increment('errors');
            if ($this->fail) {
                $this->fail = false;
                throw new RuntimeException('test rollback');
            }
        }
    };
    $this->app->instance(\App\Services\Learning\RunIntegrations::class, $hook);
    $state = accountStart($this);
    $input = accountInput($state['revision'], ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/help', $input)->assertStatus(503)->assertJsonPath('code', 'unavailable');
    expect(LearningAttempt::count())->toBe(0);
    expect($personal->fresh()->errors)->toBe(0);
    $accepted = $this->postJson('/daily/api/runs/'.$state['run_id'].'/help', $input)->assertOk()->json();
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/help', $input)->assertExactJson($accepted);
    expect($personal->fresh()->errors)->toBe(1);
    expect(LearningAttempt::count())->toBe(1);
});

it('rolls back failed finish integrations before recording the day or a completed run', function () {
    $hook = new class extends \App\Services\Learning\RunIntegrations
    {
        public bool $fail = true;

        public function finishing(LearningRun $run, array $summary, \Carbon\CarbonImmutable $finishedAt): array
        {
            if ($this->fail) {
                $this->fail = false;
                throw new RuntimeException('finish rollback');
            }

            return ['integrations_pending' => ['vocabulary', 'objectives']];
        }
    };
    $this->app->instance(\App\Services\Learning\RunIntegrations::class, $hook);
    $state = accountComplete($this, accountStart($this));
    $input = accountInput($state['revision']);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $input)->assertStatus(503);
    expect(LearningRun::find($state['run_id'])->status->value)->toBe('in_progress');
    expect(LearningActivityDay::count())->toBe(0);
    $receipt = $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $input)->assertOk()->json();
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $input)->assertExactJson($receipt);
    expect(LearningActivityDay::count())->toBe(1);
});

it('orders history by accepted server sequence even when the wall clock moves backwards', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-25 12:00:00', 'UTC'));
    $first = accountStart($this, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-24 12:00:00', 'UTC'));
    $second = accountStart($this, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01']);
    $rows = $this->getJson('/daily/api/history')->assertOk()->json('data');
    expect(array_column($rows, 'id'))->toBe([$second['run_id'], $first['run_id']]);
    expect($rows[0]['record_sequence'])->toBeGreaterThan($rows[1]['record_sequence']);
    $this->travelBack();
});

it('refuses to reset an active daily assignment on the same level', function () {
    $run = accountStart($this);
    $this->postJson('/daily/api/runs', accountInput(1, ['kind' => 'daily', 'replace_assignment' => true]))->assertUnprocessable();
    expect(LearningRun::count())->toBe(1);
    expect(DailyAssignment::sole()->selected_run_id)->toBe($run['run_id']);
});
