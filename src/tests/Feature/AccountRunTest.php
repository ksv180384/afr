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
    $package = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true);
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
    $receipt = $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', $input, ['X-Practice-Owner' => (string) $this->learner->id])->assertOk()->json();
    expect($receipt['state']['presentation']['first_result']['score'])->toBe(0);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', $input)->assertExactJson($receipt);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', [...$input, 'client_event_id' => (string) Str::uuid()])->assertConflict()->assertJsonPath('revision', $receipt['state']['revision']);
    $loaded = $this->getJson('/daily/api/runs/'.$state['run_id'])->assertOk()->json();
    expect($loaded['position'])->toBe(1);
    expect(LearningAttempt::count())->toBe(2);
});

it('preloads the owners pinned tasks and answer keys in order only when requested', function () {
    $run = accountStart($this);
    $path = '/daily/api/runs/'.$run['run_id'];
    $this->getJson($path)->assertOk()->assertJsonMissingPath('playback')->assertJsonMissingPath('task.correct_choice_id');
    $response = $this->getJson($path.'?playback=1')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount($run['total'], 'playback.steps')
        ->assertJsonPath('playback.steps.0.presentation_id', $run['presentation']['presentation_id'])
        ->assertJsonMissingPath('task.correct_choice_id');
    $snapshot = $this->versions[0]->payload;
    expect(array_column($response->json('playback.steps'), 'task'))->toBe($snapshot['tasks'])
        ->and($response->json('playback.introductions'))->toBe($snapshot['introductions']);
    $this->actingAs(User::factory()->create())->getJson($path.'?playback=1')->assertNotFound()->assertJsonMissingPath('playback');
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
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/help', accountInput($state['revision'], [
        'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown',
    ]), ['X-Practice-Owner' => (string) $this->learner->id])->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->assertDatabaseCount('learning_attempts', 0);
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
    $old = accountStart($this, ['timezone' => 'UTC']);
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


it('keeps an unfinished six-task snapshot and completes a new twelve-task run including late help and resume', function () {
    $old = accountStart($this);
    $oldSnapshot = LearningRun::findOrFail($old['run_id'])->snapshot;
    $payload = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/twelve-task-package.json')), true)['sessions'][0];
    $this->versions[0]->update(['payload' => $payload, 'checksum' => CanonicalJson::hash($payload)]);
    $loaded = $this->getJson('/daily/api/runs/'.$old['run_id'])->assertOk()->json();
    expect($loaded['total'])->toBe(6)->and(LearningRun::find($old['run_id'])->snapshot)->toBe($oldSnapshot);
    $old = accountCommand($this, accountComplete($this, $loaded), 'finish');
    expect($old['summary']['score_denominator'])->toBe(6);
    $state = accountStart($this, ['kind' => 'extra', 'session_id' => $payload['id']]);
    expect($state['total'])->toBe(12);
    foreach ($payload['tasks'] as $index => $task) {
        expect($state['position'])->toBe($index + 1)->and($state['task']['id'])->toBe($task['id']);
        if ($state['introductions']) {
            $state = accountCommand($this, $state, 'introduction', ['presentation_id' => $state['presentation']['presentation_id']]);
        }
        if ($index === 8) {
            $state = accountCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'help', 'help_kind' => 'audio_transcript']);
            $state = $this->getJson('/daily/api/runs/'.$state['run_id'])->assertOk()->json();
            expect($state['task']['id'])->toBe('Q09')->and($state['presentation']['help_used'])->toBeTrue();
        }
        if ($index === 9) {
            $state = accountCommand($this, accountCommand($this, $state, 'pause'), 'resume');
            expect($state['position'])->toBe(10)->and($state['total'])->toBe(12);
        }
        $answer = match ($task['type']) {
            'single_choice' => ['choice_id' => $task['correct_choice_id']],
            'matching_pairs' => ['pairs' => array_map(fn (array $pair): array => ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']], $task['correct_pairs'])],
            'word_order' => ['token_ids' => $task['accepted_orders'][0]],
            'fill_blank' => $task['input_mode'] === 'choice' ? ['choice_id' => $task['correct_choice_id']] : ['value' => $task['accepted_answers'][0]],
            'short_answer' => ['value' => $task['accepted_answers'][0]],
        };
        $state = accountCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => $answer]);
        if ($index < 11) {
            $state = accountCommand($this, $state, 'advance', ['presentation_id' => $state['presentation']['presentation_id']]);
        }
    }
    $state = accountCommand($this, $state, 'finish');
    expect($state['finished'])->toBeTrue()
        ->and($state['summary']['processed_steps'])->toBe(12)
        ->and($state['summary']['score_denominator'])->toBe(12)
        ->and($state['summary']['score_numerator'])->toBe(11)
        ->and(LearningRun::find($old['run_id'])->snapshot)->toBe($oldSnapshot);
});


it('keeps short review blocks below the ordinary lesson minimum', function (int $count) {
    foreach (array_slice($this->versions[0]->payload['vocabulary'], 0, $count) as $word) {
        \App\Models\Learning\PersonalVocabulary::factory()->create([
            'user_id' => $this->learner->id, 'lexeme_key' => $word['lexeme_key'],
            'snapshot' => $word, 'next_review_at' => now('UTC')->subDay(),
        ]);
    }
    $state = accountStart($this, ['kind' => 'review']);
    expect($state['total'])->toBe($count);
    $finished = accountCommand($this, accountComplete($this, $state), 'finish');
    expect($finished['finished'])->toBeTrue()->and($finished['summary']['score_denominator'])->toBe($count);
})->with([1, 2, 3]);

it('gives an unfinished extra run priority over the completed daily assignment', function () {
    $first = accountStart($this);
    $finished = accountCommand($this, accountComplete($this, $first), 'finish');
    $this->getJson('/daily/api/today')->assertJsonPath('active_run', null)->assertJsonPath('candidate.id', 'PRE_A1-U01-L02');
    $extra = accountStart($this, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L02']);
    $this->getJson('/daily/api/today')->assertOk()
        ->assertJsonPath('run.id', $finished['run_id'])
        ->assertJsonPath('active_run.id', $extra['run_id'])
        ->assertJsonPath('route.lessons.0.status', 'completed')
        ->assertJsonPath('route.lessons.1.status', 'active');
});

it('rejects a start from a cached profile after the authenticated owner changes', function () {
    $this->postJson('/daily/api/runs', accountInput(1, ['kind' => 'daily', 'expected_owner_user_id' => $this->learner->id + 1]))->assertForbidden();
    $this->assertDatabaseCount('learning_runs', 0);
    $this->assertDatabaseCount('learning_profiles', 0);
});

it('opens the active run for a route start and preserves its pinned first step on resume', function () {
    $first = accountStart($this, ['route_start' => true]);
    $this->getJson('/daily/api/runs/'.$first['run_id'])->assertOk()
        ->assertJsonPath('presentation.presentation_id', $first['presentation']['presentation_id'])
        ->assertJsonPath('introduction_seen', false);
    $paused = accountCommand($this, $first, 'pause');
    $operation = accountInput(1, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01', 'route_start' => true]);
    $this->postJson('/daily/api/runs', $operation)->assertOk()
        ->assertJsonPath('state.run_id', $first['run_id'])->assertJsonPath('state.status', 'paused');
    $this->postJson('/daily/api/runs', $operation)->assertOk()
        ->assertJsonPath('state.run_id', $first['run_id'])->assertJsonPath('accepted_event_id', $operation['client_event_id']);
    $resumed = accountCommand($this, $paused, 'resume');
    expect($resumed['presentation']['presentation_id'])->toBe($first['presentation']['presentation_id'])
        ->and($resumed['introductions'])->toBe($first['introductions'])
        ->and($resumed['introduction_seen'])->toBeFalse();
    $this->assertDatabaseCount('learning_runs', 1);
});

it('opens the requested available lesson while keeping another unfinished lesson', function () {
    $first = accountStart($this, ['route_start' => true]);
    accountCommand($this, accountComplete($this, $first), 'finish');

    $firstInput = ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01', 'route_start' => true];
    $secondInput = ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L02', 'route_start' => true];
    $repeat = accountStart($this, $firstInput);
    $this->getJson('/daily/api/today')->assertOk()
        ->assertJsonPath('route.lessons.0.status', 'active')
        ->assertJsonPath('route.lessons.1.status', 'available');

    $second = accountStart($this, $secondInput);
    expect($second['session']['id'])->toBe('PRE_A1-U01-L02');
    $this->getJson('/daily/api/today')->assertOk()
        ->assertJsonPath('route.lessons.0.run_id', $repeat['run_id'])
        ->assertJsonPath('route.lessons.1.run_id', $second['run_id']);
    expect(accountStart($this, $firstInput)['run_id'])->toBe($repeat['run_id']);
    expect(accountStart($this, $secondInput)['run_id'])->toBe($second['run_id']);
    $this->assertDatabaseCount('learning_runs', 3);
});

it('continues the selected practice level and keeps the previous lesson available', function () {
    LearningLevel::where('code', 'B1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['level' => 'B1', 'published_at' => now('UTC')]);
    $payload = [...$this->versions[0]->payload, 'id' => 'B1-FIRST', 'level' => 'B1', 'unit_id' => $unit->external_id];
    $session = LearningSession::factory()->create(['external_id' => $payload['id'], 'level' => 'B1', 'unit_id' => $unit->id]);
    $version = LearningSessionVersion::factory()->create([
        'session_id' => $session->id,
        'payload' => $payload,
        'checksum' => CanonicalJson::hash($payload),
        'status' => 'published',
        'published_at' => now('UTC'),
    ]);
    $session->update(['active_version_id' => $version->id]);

    $this->patchJson('/daily/api/profile', accountInput(1, ['current_level' => 'B1', 'timezone' => 'UTC']))->assertOk();
    $previous = $this->postJson('/daily/api/runs', accountInput(2, ['kind' => 'daily', 'route_start' => true]))
        ->assertOk()->assertJsonPath('state.session.level', 'B1')->json('state');
    $this->patchJson('/daily/api/profile', accountInput(2, ['current_level' => 'PRE_A1', 'timezone' => 'UTC']))->assertOk();
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('active_run', null)
        ->assertJsonPath('candidate.level', 'PRE_A1');
    $current = $this->postJson('/daily/api/runs', accountInput(3, ['kind' => 'daily', 'route_start' => true]))
        ->assertOk()->assertJsonPath('state.session.level', 'PRE_A1')->json('state');
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('active_run.id', $current['run_id']);

    $this->patchJson('/daily/api/profile', accountInput(3, ['current_level' => 'B1', 'timezone' => 'UTC']))->assertOk();
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('active_run.id', $previous['run_id']);
    $this->postJson('/daily/api/runs', accountInput(4, ['kind' => 'daily', 'route_start' => true]))
        ->assertOk()->assertJsonPath('state.run_id', $previous['run_id'])
        ->assertJsonPath('state.presentation.presentation_id', $previous['presentation']['presentation_id']);
    $this->assertDatabaseCount('learning_runs', 2);
});

it('only creates the next or repeated route lesson after an explicit start', function () {
    $first = accountStart($this, ['route_start' => true]);
    accountCommand($this, accountComplete($this, $first), 'finish');
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate.id', 'PRE_A1-U01-L02');
    $this->assertDatabaseCount('learning_runs', 1);

    $input = accountInput(1, ['kind' => 'daily', 'route_start' => true]);
    $next = $this->postJson('/daily/api/runs', $input)->assertOk()->assertJsonPath('state.session.id', 'PRE_A1-U01-L02')->json('state');
    $this->postJson('/daily/api/runs', $input)->assertOk()->assertJsonPath('state.run_id', $next['run_id']);
    accountCommand($this, accountComplete($this, $next), 'finish');
    $profile = $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate', null)->json('profile');
    $this->postJson('/daily/api/runs', accountInput($profile['revision'], ['kind' => 'daily', 'route_start' => true]))->assertNotFound();
    $this->assertDatabaseCount('learning_runs', 2);

    $repeat = $this->postJson('/daily/api/runs', accountInput($profile['revision'], [
        'kind' => 'extra', 'route_start' => true, 'session_id' => 'PRE_A1-U01-L01',
    ]))->assertOk()->json('state');
    expect($repeat['session']['id'])->toBe('PRE_A1-U01-L01')->and($repeat['run_id'])->not->toBe($first['run_id']);
    $this->assertDatabaseCount('learning_runs', 3);
});


it('persists pronunciation help for the current question and leaves a graded answer intact', function () {
    $run = accountStart($this);
    $event = ['presentation_id' => $run['presentation']['presentation_id'], 'event_type' => 'help', 'help_kind' => 'language_support'];
    $helped = accountCommand($this, $run, 'help', $event);
    expect($helped['presentation']['help_used'])->toBeTrue();
    $this->getJson('/daily/api/runs/'.$run['run_id'])->assertOk()->assertJsonPath('assistance', ['language_support']);
    $graded = accountCommand($this, $helped, 'answers', [
        'presentation_id' => $event['presentation_id'], 'event_type' => 'answer',
        'answer' => ['choice_id' => $this->versions[0]->payload['tasks'][0]['correct_choice_id']],
    ]);
    expect($graded['presentation']['first_result'])->toMatchArray(['correct' => true, 'score' => 0]);

    $fresh = accountStart($this, ['kind' => 'extra', 'session_id' => $this->versions[0]->payload['id']]);
    $wrong = accountCommand($this, $fresh, 'answers', [
        'presentation_id' => $fresh['presentation']['presentation_id'], 'event_type' => 'answer',
        'answer' => ['choice_id' => collect($this->versions[0]->payload['tasks'][0]['choices'])
            ->firstWhere('id', '!=', $this->versions[0]->payload['tasks'][0]['correct_choice_id'])['id']],
    ]);
    $after = accountCommand($this, $wrong, 'help', [...$event, 'presentation_id' => $fresh['presentation']['presentation_id']]);
    expect($after['presentation']['first_result'])->toBe($wrong['presentation']['first_result'])
        ->and($after['presentation']['help_used'])->toBeFalse();
});
