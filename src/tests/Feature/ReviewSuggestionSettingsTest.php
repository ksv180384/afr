<?php

use App\Models\Learning\LearningProfile;
use App\Models\User\User;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $this->actingAs($this->learner);
});

function reviewSettingsInput(int $revision = 1, array $fields = []): array
{
    return ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $revision, 'action' => 'snooze_today', ...$fields];
}

function reviewGuestBatch(array $extra = []): array
{
    return ['guest_id' => (string) Str::uuid(), 'episode_id' => null, 'batch_id' => (string) Str::uuid(),
        'level_events' => [], 'runs' => [], 'marks' => [], ...$extra];
}

it('reads safe defaults without persisting a new profile and changes only review settings', function () {
    $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('persisted', false)
        ->assertJsonPath('review_suggestions.enabled', true)->assertJsonPath('review_suggestions.can_suggest', true);
    $this->assertDatabaseCount('learning_profiles', 0);
    $receipt = $this->patchJson('/daily/api/review-settings', reviewSettingsInput(1, ['action' => 'toggle', 'enabled' => false]))
        ->assertOk()->assertJsonPath('profile.current_level', 'PRE_A1')->assertJsonPath('profile.review_suggestions.enabled', false)->json();
    $this->getJson('/daily/api/profile')->assertJsonPath('revision', 2)->assertJsonPath('review_suggestions.enabled', false);
    $this->assertDatabaseCount('learning_level_events', 0);
    $this->assertDatabaseCount('personal_vocabulary', 0);
    $this->assertDatabaseCount('learning_runs', 0);
});

it('uses next local midnight across DST instead of an elapsed day', function ($now, $timezone, $until) {
    $this->travelTo(Carbon\Carbon::parse($now));
    LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'B1', 'timezone' => $timezone]);
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput())->assertOk()
        ->assertJsonPath('profile.review_suggestions.snoozed_until', $until)->assertJsonPath('profile.current_level', 'B1');
    $this->travelTo(Carbon\Carbon::parse($until)->subSecond());
    $this->getJson('/daily/api/profile')->assertJsonPath('review_suggestions.can_suggest', false);
    $this->travelTo(Carbon\Carbon::parse($until));
    $this->getJson('/daily/api/profile')->assertJsonPath('review_suggestions.can_suggest', true);
})->with([
    ['2026-03-28T23:00:00+00:00', 'Europe/Paris', '2026-03-29T22:00:00+00:00'],
    ['2026-10-24T22:00:00+00:00', 'Europe/Paris', '2026-10-25T23:00:00+00:00'],
    ['2026-11-06T12:00:00+00:00', 'Europe/Moscow', '2026-11-06T21:00:00+00:00'],
]);

it('preserves a refusal through disabling re-enabling and duplicate delivery after midnight', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T12:00:00Z'));
    $input = reviewSettingsInput();
    $original = $this->patchJson('/daily/api/review-settings', $input)->assertOk()->json();
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput(2, ['action' => 'toggle', 'enabled' => false]))->assertOk();
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput(3, ['action' => 'toggle', 'enabled' => true]))->assertOk()
        ->assertJsonPath('profile.review_suggestions.snoozed_until', '2026-11-06T21:00:00+00:00')->assertJsonPath('profile.review_suggestions.can_suggest', false);
    $this->travelTo(Carbon\Carbon::parse('2026-11-07T12:00:00Z'));
    $this->patchJson('/daily/api/review-settings', $input)->assertExactJson($original);
    $this->getJson('/daily/api/profile')->assertJsonPath('revision', 4)->assertJsonPath('review_suggestions.can_suggest', true);
    $this->assertDatabaseCount('learning_run_operations', 3);
});

it('rejects stale devices changed payloads foreign owners and level fields', function () {
    $input = reviewSettingsInput(1, ['action' => 'toggle', 'enabled' => false]);
    $foreign = User::factory()->create();
    $wrongOwner = ['X-Practice-Owner' => (string) $foreign->id];
    $this->patchJson('/daily/api/review-settings', $input, $wrongOwner)->assertForbidden();
    $this->patchJson('/daily/api/profile', [
        'client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'current_level' => 'A1', 'timezone' => 'UTC',
    ], $wrongOwner)->assertForbidden();
    $this->patchJson('/daily/api/review-settings', $input, ['X-Practice-Owner' => (string) $this->learner->id])->assertOk();
    $this->patchJson('/daily/api/review-settings', [...$input, 'enabled' => true])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput())->assertConflict()
        ->assertJsonPath('code', 'revision_conflict')->assertJsonPath('state.profile.revision', 2);
    foreach (['user_id' => 99, 'current_level' => 'B1', 'timezone' => 'UTC'] as $field => $value) {
        $this->patchJson('/daily/api/review-settings', reviewSettingsInput(2, [$field => $value]))->assertUnprocessable();
    }
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput(2, ['action' => 'toggle', 'enabled' => 'false']))->assertUnprocessable();
    $this->actingAs(User::factory()->create())->getJson('/daily/api/profile')->assertJsonPath('review_suggestions.enabled', true);
    expect(LearningProfile::count())->toBe(1);
});

it('enforces authentication CSRF and feature flag on the new mutation', function () {
    auth()->forgetGuards(); $this->app['auth']->guard()->logout();
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput())->assertUnauthorized();
    $this->actingAs($this->learner);
    $this->app->detectEnvironment(fn () => 'local');
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput())->assertStatus(419);
    $this->app->detectEnvironment(fn () => 'testing');
    config(['daily_practice.enabled' => false]);
    $this->patchJson('/daily/api/review-settings', reviewSettingsInput())->assertStatus(503);
    $this->assertDatabaseCount('learning_profiles', 0);
});

it('keeps old-schema profile reads and updates usable until the additive migration is applied', function () {
    $migration = require database_path('migrations/2026_11_06_120000_add_review_suggestions_to_learning_profiles.php');
    $migration->down();
    try {
        $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('review_suggestions.available', false);
        $this->patchJson('/daily/api/review-settings', reviewSettingsInput())->assertStatus(503);
        $this->postJson('/daily/api/guest-sync', reviewGuestBatch(['review_snooze' => ['until' => '2026-11-06T21:00:00+00:00', 'timezone' => 'Europe/Moscow', 'expected_revision' => 1]]))->assertOk()->assertJsonPath('review_snooze_applied', false);
        $this->assertDatabaseCount('learning_profiles', 0);
        $this->patchJson('/daily/api/profile', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'current_level' => 'A1', 'timezone' => 'UTC'])->assertOk();
    } finally { $migration->up(); }
    $this->getJson('/daily/api/profile')->assertJsonPath('review_suggestions.available', true)->assertJsonPath('review_suggestions.enabled', true);
});

it('merges only an unconflicted guest refusal and never guest enabled preferences', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T12:00:00Z'));
    $profile = LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'B1', 'timezone' => 'Europe/Moscow']);
    $input = reviewGuestBatch(['review_snooze' => ['until' => '2026-11-06T21:00:00+00:00', 'timezone' => 'Europe/Moscow', 'expected_revision' => 1]]);
    $receipt = $this->postJson('/daily/api/guest-sync', $input)->assertOk()->assertJsonPath('review_snooze_applied', true)->assertJsonPath('profile.current_level', 'B1')->json();
    $this->postJson('/daily/api/guest-sync', $input)->assertExactJson($receipt);
    expect($profile->fresh()->revision)->toBe(2);
    $this->assertDatabaseCount('learning_level_events', 0); $this->assertDatabaseCount('personal_vocabulary', 0);
    $this->actingAs(User::factory()->create())->postJson('/daily/api/guest-sync', [...$input, 'batch_id' => (string) Str::uuid()])->assertForbidden();
});

it('leaves disabled newer or already snoozed account preferences in control', function ($attributes, $revision) {
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T12:00:00Z'));
    $profile = LearningProfile::factory()->create(['user_id' => $this->learner->id, ...$attributes]);
    $before = $profile->fresh()->getAttributes();
    $input = reviewGuestBatch(['review_snooze' => ['until' => '2026-11-06T21:00:00+00:00', 'timezone' => 'Europe/Moscow', 'expected_revision' => $revision]]);
    $this->postJson('/daily/api/guest-sync', $input)->assertOk()->assertJsonPath('review_snooze_applied', false);
    expect($profile->fresh()->getAttributes())->toBe($before);
})->with([
    [['review_suggestions_enabled' => false], 1],
    [['revision' => 3], 1],
    [['review_suggestions_snoozed_until' => '2026-11-06 20:00:00'], 1],
]);

it('ignores expired or extended guest deadlines and validates their envelope', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T12:00:00Z'));
    foreach (['2026-11-05T21:00:00+00:00', '2026-11-08T21:00:00+00:00'] as $until) {
        $this->postJson('/daily/api/guest-sync', reviewGuestBatch(['review_snooze' => ['until' => $until, 'timezone' => 'Europe/Moscow', 'expected_revision' => 1]]))->assertOk()->assertJsonPath('review_snooze_applied', false);
    }
    $this->postJson('/daily/api/guest-sync', reviewGuestBatch(['review_snooze' => ['until' => 'bad', 'timezone' => 'invalid', 'expected_revision' => 0]]))->assertUnprocessable();
    $this->postJson('/daily/api/guest-sync', reviewGuestBatch(['review_snooze' => ['until' => '2026-11-06T21:00:00+00:00', 'timezone' => 'UTC', 'expected_revision' => 1, 'enabled' => true]]))->assertUnprocessable();
});
