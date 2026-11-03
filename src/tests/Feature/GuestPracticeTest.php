<?php

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    $package = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $this->unit = LearningUnit::factory()->create(['external_id' => $package['units'][0]['id'], 'sort_order' => 1, 'published_at' => now('UTC')]);
    $this->sessions = [];
    foreach ($package['sessions'] as $session) {
        $row = LearningSession::factory()->create(['external_id' => $session['id'], 'unit_id' => $this->unit->id, 'sort_order' => $session['order']]);
        $version = LearningSessionVersion::factory()->create(['session_id' => $row->id, 'payload' => $session, 'checksum' => CanonicalJson::hash($session), 'status' => 'published', 'published_at' => now('UTC')]);
        $row->update(['active_version_id' => $version->id]);
        $this->sessions[] = $row;
    }
});

it('serves only published guest content in stable order without creating anonymous progress or users', function () {
    $users = User::count();
    $this->get('/daily/api/content', ['Accept' => 'text/html'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(2, 'sessions')->assertJsonPath('sessions.0.id', 'PRE_A1-U01-L01')->assertJsonPath('sessions.1.id', 'PRE_A1-U01-L02');
    $response = $this->get('/daily/api/content/PRE_A1-U01-L01')->assertOk()->assertJsonPath('checking_policy_version', 1)
        ->assertJsonPath('snapshot.id', 'PRE_A1-U01-L01')->assertJsonCount(6, 'snapshot.tasks');
    expect($response->json('snapshot.tasks.0.correct_choice_id'))->toBeString();
    $this->get('/daily')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Daily/Index', false)->where('authUser', null)->where('enabled', true));
    $this->get('/daily/guest/20000000-0000-4000-8000-000000000001')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('runId', null)->where('guestRunId', '20000000-0000-4000-8000-000000000001'));
    expect(User::count())->toBe($users);
    expect(LearningRun::count())->toBe(0);
    foreach (['learning_profiles', 'learning_run_operations', 'learning_attempts', 'daily_assignments', 'personal_vocabulary'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

it('does not expose draft archived unpublished or private media through guest reads', function () {
    $this->sessions[0]->activeVersion->update(['status' => 'draft']);
    $this->get('/daily/api/content/PRE_A1-U01-L01', ['Accept' => 'text/html'])->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->get('/daily/api/content')->assertJsonCount(1, 'sessions');
    $this->sessions[1]->update(['archived_at' => now()]);
    $this->get('/daily/api/content')->assertJsonCount(0, 'sessions');
    $this->get('/daily/api/content/PRE_A1-U01-L02')->assertNotFound();
    $this->get('/daily/api/content/PRE_A1-U01-L02/media/20000000-0000-4000-8000-000000000001')->assertNotFound();
});

it('honors level and unit publication and feature flag for anonymous readers', function () {
    $this->unit->update(['published_at' => null]);
    $this->get('/daily/api/content')->assertJsonCount(0, 'sessions');
    $this->unit->update(['published_at' => now()]);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => null]);
    $this->get('/daily/api/content')->assertJsonCount(0, 'sessions');
    config(['daily_practice.enabled' => false]);
    $this->get('/daily/api/content')->assertStatus(503)->assertJsonPath('code', 'disabled');
    $this->get('/daily/api/content/PRE_A1-U01-L01')->assertStatus(503)->assertJsonPath('code', 'disabled');
});

it('keeps account endpoints protected while guests read public teaching snapshots', function () {
    $this->get('/daily/api/runs/20000000-0000-4000-8000-000000000001')->assertUnauthorized();
    $this->get('/daily/api/profile')->assertUnauthorized();
    $this->get('/daily/api/content')->assertOk();
    $this->actingAs(User::factory()->create())->get('/daily/guest/20000000-0000-4000-8000-000000000001')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('runId', null)->where('authUser.id', auth()->id()));
});
