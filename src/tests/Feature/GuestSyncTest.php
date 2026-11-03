<?php

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $this->actingAs($this->learner);
    $this->payload = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['published_at' => now('UTC')]);
    $row = LearningSession::factory()->create(['unit_id' => $unit->id, 'external_id' => $this->payload['id']]);
    $this->version = LearningSessionVersion::factory()->create(['session_id' => $row->id, 'payload' => $this->payload, 'checksum' => CanonicalJson::hash($this->payload), 'status' => 'published', 'published_at' => now('UTC')->subDays(10)]);
    $row->update(['active_version_id' => $this->version->id]);
});

function syncFixture($test, bool $finish = true, int $offset = 1): array
{
    $id = (string) Str::uuid();
    $time = now('UTC')->subDay()->toIso8601String();
    $presentations = array_map(fn () => (string) Str::uuid(), $test->payload['tasks']);
    $events = [['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'start', 'run_id' => $id, 'client_occurred_at' => $time, 'kind' => 'daily', 'presentation_ids' => $presentations]];
    $revision = 1;
    foreach ($presentations as $index => $presentation) {
        $events[] = ['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'help', 'run_id' => $id, 'expected_revision' => $revision++, 'client_occurred_at' => $time, 'presentation_id' => $presentation, 'event_type' => 'unknown'];
        if (! $finish) {
            break;
        }
        if ($index < count($presentations) - 1) {
            $events[] = ['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'advance', 'run_id' => $id, 'expected_revision' => $revision++, 'client_occurred_at' => $time, 'presentation_id' => $presentation];
        }
    }
    if ($finish) {
        $events[] = ['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'finish', 'run_id' => $id, 'expected_revision' => $revision++, 'client_occurred_at' => $time];
    }

    return ['guest_run_id' => $id, 'kind' => 'daily', 'session_version_id' => $test->version->id, 'snapshot' => $test->payload, 'checking_policy_version' => 1, 'presentation_ids' => $presentations,
        'timezone' => 'Europe/Moscow', 'local_date' => \App\Support\Learning\PracticeTime::localDate(\Carbon\CarbonImmutable::parse($time), 'Europe/Moscow'), 'started_at' => $time, 'events' => $events];
}
function syncBatch(array $runs = [], string $level = 'PRE_A1'): array
{
    return ['guest_id' => (string) Str::uuid(), 'episode_id' => null, 'batch_id' => (string) Str::uuid(), 'runs' => $runs, 'marks' => [],
        'level_events' => [['client_event_id' => (string) Str::uuid(), 'sequence' => 1, 'action' => 'level_selection', 'current_level' => $level, 'timezone' => 'Europe/Moscow', 'client_occurred_at' => now('UTC')->subDay()->toIso8601String()]]];
}

it('rechecks a completed run and preserves higher level and existing vocabulary while adding sources', function () {
    $profile = LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'B1', 'timezone' => 'UTC']);
    $snapshot = $this->payload['vocabulary'][0];
    $word = PersonalVocabulary::factory()->create(['user_id' => $this->learner->id, 'lexeme_key' => $snapshot['lexeme_key'], 'snapshot' => $snapshot, 'needs_review' => false, 'streak' => 2, 'errors' => 9, 'review_step' => 4, 'next_review_at' => now('UTC')->addDays(14)]);
    $before = $word->toArray();
    $bundle = syncFixture($this, true, 2);
    $batch = syncBatch([$bundle]);
    $receipt = $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(0, 'rejected')->json();
    expect(LearningProfile::where('user_id', $this->learner->id)->sole()->getRawOriginal('current_level'))->toBe('B1');
    $run = LearningRun::find($receipt['mapping'][$bundle['guest_run_id']]['server_run_id']);
    expect($run->result['independent_correct_steps'])->toBe(0);
    expect($run->status->value)->toBe('completed');
    $this->assertDatabaseCount('personal_vocabulary', 6);
    $this->assertDatabaseCount('personal_vocabulary_sources', 6);
    foreach (['needs_review', 'streak', 'errors', 'review_step', 'next_review_at'] as $key) {
        expect($word->fresh()->toArray()[$key])->toBe($before[$key]);
    }
    $this->postJson('/daily/api/guest-sync', $batch)->assertExactJson($receipt);
    $profile->refresh()->update(['current_level' => 'PRE_A1', 'revision' => $profile->revision + 1]);
    $batch['batch_id'] = (string) Str::uuid();
    $batch['episode_id'] = $receipt['episode_id'];
    $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(0, 'rejected');
    expect($profile->fresh()->getRawOriginal('current_level'))->toBe('PRE_A1');
    $this->assertDatabaseCount('learning_runs', 1);
});

it('accepts valid runs atomically beside a corrupt snapshot and rejects cross owner transfer', function () {
    $valid = syncFixture($this, true, 2);
    $bad = syncFixture($this, true, 30);
    $bad['snapshot']['title'] = 'Forged';
    $batch = syncBatch([$valid, $bad], 'B1');
    $receipt = $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(1, 'rejected')->json();
    $this->assertDatabaseCount('learning_runs', 1);
    $this->assertDatabaseCount('personal_vocabulary', 6);
    expect(LearningProfile::where('user_id', $this->learner->id)->sole()->getRawOriginal('current_level'))->toBe('B1');
    $this->actingAs(User::factory()->create())->postJson('/daily/api/guest-sync', $batch)->assertForbidden();
});

it('keeps both unfinished runs and the account assignment and prevents server edits being overwritten', function () {
    $service = app(AccountRunService::class);
    $old = $service->start($this->learner, ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])['state'];
    $bundle = syncFixture($this, false, 2);
    $batch = syncBatch([$bundle]);
    $receipt = $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(0, 'rejected')->json();
    $this->assertDatabaseCount('learning_runs', 2);
    expect(\App\Models\Learning\DailyAssignment::sole()->selected_run_id)->toBe($old['run_id']);
    $mapped = $receipt['mapping'][$bundle['guest_run_id']];
    $service->command($this->learner, $mapped['server_run_id'], 'pause', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $mapped['server_revision']]);
    $bundle['events'][] = ['client_event_id' => (string) Str::uuid(), 'sequence' => 4, 'run_id' => $bundle['guest_run_id'], 'action' => 'advance', 'expected_revision' => 2,
        'presentation_id' => $bundle['presentation_ids'][0], 'client_occurred_at' => now('UTC')->toIso8601String()];
    $batch['runs'] = [$bundle];
    $batch['batch_id'] = (string) Str::uuid();
    $batch['level_events'] = [];
    $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonPath('rejected.0.code', 'revision_conflict');
    expect(LearningRun::find($mapped['server_run_id'])->status->value)->toBe('paused');
});

it('retains future or contradictory guest clocks as metadata but credits only the received day', function () {
    $bundle = syncFixture($this, true, 2);
    $bundle['started_at'] = '2099-01-01T00:00:00Z';
    $bundle['local_date'] = '2099-01-01';
    $receipt = $this->postJson('/daily/api/guest-sync', syncBatch([$bundle]))->assertOk()->assertJsonCount(0, 'rejected')->json();
    $run = LearningRun::find($receipt['mapping'][$bundle['guest_run_id']]['server_run_id']);
    expect($run->import_meta['clock_warning'])->toBeTrue();
    expect($run->import_meta['original_local_date'])->toBe('2099-01-01');
    expect(\App\Models\Learning\LearningActivityDay::sole()->local_date->format('Y-m-d'))->toBe(\App\Support\Learning\PracticeTime::localDate(\App\Support\Learning\PracticeTime::now(), 'Europe/Moscow'));
});

function syncReviewFixture($test, array $source, int $offset, bool $wrong = false): array
{
    $id = (string) Str::uuid();
    $presentation = (string) Str::uuid();
    $time = now('UTC')->subHour()->toIso8601String();
    $item = $source['review_items'][0];
    $snapshot = ['id' => $id, 'title' => 'Guest test', 'level' => 'PRE_A1', 'duration_minutes' => 3, 'tasks' => [$item],
        'review_items' => [['task' => $item, 'lexemes' => [], 'review_lexeme_key' => $source['lexeme_key']]], 'vocabulary' => [$source], 'introductions' => [], 'remaining_word_ids' => []];
    $events = [
        ['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'start', 'run_id' => $id, 'client_occurred_at' => $time],
        ['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'answer', 'run_id' => $id, 'expected_revision' => 1, 'presentation_id' => $presentation, 'event_type' => 'answer', 'answer' => ['choice_id' => $wrong ? 'b' : 'a'], 'client_occurred_at' => $time],
        ['client_event_id' => (string) Str::uuid(), 'sequence' => $offset++, 'action' => 'finish', 'run_id' => $id, 'expected_revision' => 2, 'client_occurred_at' => $time],
    ];

    return ['guest_run_id' => $id, 'kind' => 'vocabulary_test', 'session_version_id' => null, 'snapshot' => $snapshot, 'checking_policy_version' => 1, 'presentation_ids' => [$presentation],
        'timezone' => 'UTC', 'local_date' => \App\Support\Learning\PracticeTime::localDate(\Carbon\CarbonImmutable::parse($time), 'UTC'), 'started_at' => $time, 'events' => $events];
}

it('does not reapply a higher guest choice after a later manual downgrade and ignores superseded guest choices', function () {
    $profile = LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'A1']);
    $batch = syncBatch([], 'B1');
    $receipt = $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->json();
    expect($profile->fresh()->getRawOriginal('current_level'))->toBe('B1');
    $profile->refresh()->update(['current_level' => 'PRE_A1', 'revision' => $profile->revision + 1]);
    $batch['batch_id'] = (string) Str::uuid();
    $this->postJson('/daily/api/guest-sync', $batch)->assertOk();
    expect($profile->fresh()->getRawOriginal('current_level'))->toBe('PRE_A1');
    $other = syncBatch([], 'B1');
    $latest = [...$other['level_events'][0], 'client_event_id' => (string) Str::uuid(), 'sequence' => 2, 'current_level' => 'PRE_A1'];
    $other['level_events'][] = $latest;
    $other['level_selection_event_id'] = $latest['client_event_id'];
    $this->postJson('/daily/api/guest-sync', $other)->assertOk()->assertJsonCount(0, 'rejected');
    expect($profile->fresh()->getRawOriginal('current_level'))->toBe('PRE_A1');
});

it('keeps the vocabulary boundary across batches and respects newer native answers for newly imported words', function () {
    $bundle = syncFixture($this, true, 2);
    $batch = syncBatch([$bundle]);
    $receipt = $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->json();
    $key = $this->payload['vocabulary'][0]['lexeme_key'];
    $word = PersonalVocabulary::where('user_id', $this->learner->id)->where('lexeme_key', $key)->sole();
    $errors = $word->errors;
    foreach ([30, 40] as $offset) {
        $review = syncReviewFixture($this, $this->payload['vocabulary'][0], $offset);
        $batch['batch_id'] = (string) Str::uuid();
        $batch['level_events'] = [];
        $batch['runs'] = [$review];
        $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(0, 'rejected');
    }
    expect($word->fresh()->streak)->toBe(2);
    expect($word->fresh()->errors)->toBe($errors);
    $service = app(AccountRunService::class);
    $state = $service->start($this->learner, ['kind' => 'vocabulary_test', 'word_ids' => [$word->id], 'client_event_id' => (string) Str::uuid(), 'expected_revision' => $service->profile($this->learner)['revision']])['state'];
    $service->event($this->learner, $state['run_id'], 'answer', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    expect($word->fresh()->streak)->toBe(3);
    $late = syncReviewFixture($this, $this->payload['vocabulary'][0], 50, true);
    $batch['batch_id'] = (string) Str::uuid();
    $batch['runs'] = [$late];
    $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(0, 'rejected');
    expect($word->fresh()->streak)->toBe(3);
    expect($word->fresh()->needs_review)->toBeFalse();
    expect($word->fresh()->errors)->toBe($errors);
});

it('rechecks forged result counters and rejects an event repacked with another body without touching the original run', function () {
    $bundle = syncFixture($this, true, 2);
    $bundle['result'] = ['score' => 100, 'independent_correct_steps' => 999];
    $batch = syncBatch([$bundle]);
    $receipt = $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonCount(0, 'rejected')->json();
    $id = $receipt['mapping'][$bundle['guest_run_id']]['server_run_id'];
    expect(LearningRun::find($id)->result['independent_correct_steps'])->toBe(0);
    $bundle['events'][1]['event_type'] = 'technical_skip';
    $bundle['events'][1]['reason'] = 'audio_unavailable';
    $batch['batch_id'] = (string) Str::uuid();
    $batch['runs'] = [$bundle];
    $this->postJson('/daily/api/guest-sync', $batch)->assertOk()->assertJsonPath('rejected.0.code', 'idempotency_conflict');
    expect(LearningRun::find($id)->result['independent_correct_steps'])->toBe(0);
    $this->assertDatabaseCount('learning_runs', 1);
});

it('allows unverified owners to sync and exposes a practice return from verification without changing other verified routes', function () {
    $this->learner->update(['email_verified_at' => null]);
    $this->postJson('/daily/api/guest-sync', syncBatch([syncFixture($this, true, 2)]))->assertOk()->assertJsonCount(0, 'rejected');
    $this->get('/verify-email')->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('daily_practice_enabled', true)->where('auth.user.id', $this->learner->id));
    auth()->logout();
    $this->postJson('/daily/api/guest-sync', syncBatch())->assertUnauthorized();
});

it('uses JSON CSRF validation and feature flag errors for sync and never creates anonymous history', function () {
    $this->app->detectEnvironment(fn () => 'local');
    $this->post('/daily/api/guest-sync', syncBatch(), ['Accept' => 'text/html'])->assertStatus(419)->assertJsonPath('code', 'session_expired');
    $this->app->detectEnvironment(fn () => 'testing');
    config(['daily_practice.enabled' => false]);
    $this->postJson('/daily/api/guest-sync', syncBatch())->assertStatus(503)->assertJsonPath('code', 'disabled');
    $this->assertDatabaseCount('guest_sync_episodes', 0);
    $this->assertDatabaseCount('learning_runs', 0);
});

it('never resurrects a protected old guest error when an unfinished imported run is finished natively', function () {
    $source = $this->payload['vocabulary'][0];
    $word = PersonalVocabulary::factory()->create(['user_id' => $this->learner->id, 'lexeme_key' => $source['lexeme_key'], 'snapshot' => $source, 'streak' => 2, 'errors' => 9, 'review_step' => 4, 'needs_review' => false]);
    $bundle = syncFixture($this, false, 2);
    $receipt = $this->postJson('/daily/api/guest-sync', syncBatch([$bundle]))->assertOk()->assertJsonCount(0, 'rejected')->json();
    $id = $receipt['mapping'][$bundle['guest_run_id']]['server_run_id'];
    expect($word->fresh()->errors)->toBe(9);
    $service = app(AccountRunService::class);
    $state = $service->load($this->learner, $id);
    $state = $service->command($this->learner, $id, 'advance', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id']])['state'];
    while (true) {
        $task = $this->payload['tasks'][$state['position'] - 1];
        $answer = isset($task['correct_choice_id']) ? ['choice_id' => $task['correct_choice_id']] : match ($task['type']) {
            'matching_pairs' => ['pairs' => array_map(fn ($pair) => ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']], $task['correct_pairs'])],
            'word_order' => ['token_ids' => $task['accepted_orders'][0]],
            default => ['value' => $task['accepted_answers'][0]],
        };
        $state = $service->event($this->learner, $id, 'answer', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => $answer])['state'];
        if ($state['position'] === $state['total']) {
            break;
        }
        $state = $service->command($this->learner, $id, 'advance', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id']])['state'];
    }
    $service->command($this->learner, $id, 'finish', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision']]);
    expect($word->fresh()->errors)->toBe(9);
    expect($word->fresh()->streak)->toBe(3);
});

it('registers an unverified learner who can sync from verification without invoking real mail or jobs', function () {
    \Illuminate\Support\Facades\Event::fake([\Illuminate\Auth\Events\Registered::class]);
    \Illuminate\Support\Facades\Queue::fake();
    auth()->logout();
    $this->post('/register', ['name' => 'mergelearner', 'email' => 'merge@example.test', 'password' => 'Learning-test-12!', 'password_confirmation' => 'Learning-test-12!', 'confirmation_rules' => true])->assertRedirect(route('verification.notice'));
    $created = User::where('email', 'merge@example.test')->sole();
    expect($created->email_verified_at)->toBeNull();
    $this->get('/verify-email')->assertOk();
    $this->postJson('/daily/api/guest-sync', syncBatch([syncFixture($this, true, 2)]))->assertOk()->assertJsonCount(0, 'rejected');
    expect(LearningRun::where('user_id', $created->id)->count())->toBe(1);
});
