<?php

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\LearningAccessService;
use App\Services\Learning\PracticeUrls;
use Database\Seeders\Learning\LearningLevelSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    LearningLevel::whereIn('code', ['PRE_A1', 'A1'])->update(['published_at' => now('UTC')]);
    $this->learner = User::factory()->create();
    $template = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    $this->lessons = [];
    foreach ([['PRE_A1', 1, 2], ['PRE_A1', 2, 1], ['A1', 1, 1]] as [$level, $order, $count]) {
        $unit = LearningUnit::factory()->create(['level' => $level, 'sort_order' => $order, 'slug' => strtolower(str_replace('_', '-', $level)).'-unit-'.$order, 'published_at' => now('UTC')]);
        for ($i = 1; $i <= $count; $i++) {
            $id = $level.'-U'.$order.'-L'.$i;
            $payload = [...$template, 'id' => $id, 'level' => $level, 'unit_id' => $unit->external_id, 'order' => $i, 'prerequisite_ids' => []];
            $payload['public_page']['related_session_ids'] = [];
            $payload['public_page']['slug'] = strtolower(str_replace('_', '-', $id));
            $session = LearningSession::factory()->create(['external_id' => $id, 'level' => $level, 'unit_id' => $unit->id, 'sort_order' => $i, 'slug' => strtolower(str_replace('_', '-', $id))]);
            $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $payload, 'status' => 'published', 'published_at' => now('UTC')]);
            $session->update(['active_version_id' => $version->id]);
            $this->lessons[$id] = $session->fresh()->load('unit', 'activeVersion');
        }
    }
    $this->actingAs($this->learner);
});

function completeAccessLesson($test, string $id): void
{
    $session = $test->lessons[$id];
    LearningRun::factory()->create(['user_id' => $test->learner->id, 'session_version_id' => $session->active_version_id,
        'snapshot' => $session->activeVersion->payload, 'kind' => 'extra', 'status' => 'completed', 'finished_at' => now('UTC')]);
}

it('locks later sessions topics and levels before entry including direct links', function () {
    $access = app(LearningAccessService::class)->forUser($this->learner);
    expect($access['sessions']['PRE_A1-U1-L1']['locked'])->toBeFalse()
        ->and($access['sessions']['PRE_A1-U1-L2']['locked'])->toBeTrue()
        ->and($access['units'][$this->lessons['PRE_A1-U2-L1']->unit->external_id]['locked'])->toBeTrue()
        ->and($access['levels']['A1']['locked'])->toBeTrue();
    $this->get('/practice')->assertInertia(fn (Assert $page) => $page->where('catalog.cards.0.locked', false)->where('catalog.cards.1.locked', true));
    foreach ([PracticeUrls::session($this->lessons['PRE_A1-U1-L2']), PracticeUrls::unit($this->lessons['PRE_A1-U2-L1']->unit), '/practice/a1', '/daily?session=PRE_A1-U2-L1'] as $path) {
        $this->get($path)->assertRedirect('/practice');
    }
    $this->assertDatabaseCount('learning_runs', 0);
});

it('unlocks the next lesson then the next topic then the next level only on completion', function () {
    completeAccessLesson($this, 'PRE_A1-U1-L1');
    $service = app(LearningAccessService::class);
    $state = $service->forUser($this->learner);
    expect($state['sessions']['PRE_A1-U1-L2']['locked'])->toBeFalse()->and($state['sessions']['PRE_A1-U2-L1']['locked'])->toBeTrue();
    completeAccessLesson($this, 'PRE_A1-U1-L2');
    $state = $service->forUser($this->learner);
    expect($state['sessions']['PRE_A1-U2-L1']['locked'])->toBeFalse()->and($state['levels']['A1']['locked'])->toBeTrue();
    completeAccessLesson($this, 'PRE_A1-U2-L1');
    expect($service->forUser($this->learner)['levels']['A1']['locked'])->toBeFalse();
    $this->get('/practice/a1')->assertOk();
});

it('keeps completion and replay after a new version while earlier lessons remain unfinished', function () {
    completeAccessLesson($this, 'A1-U1-L1');
    $session = $this->lessons['A1-U1-L1'];
    $payload = [...$session->activeVersion->payload, 'version' => 2, 'title' => 'Обновлённое занятие'];
    $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'version' => 2, 'payload' => $payload, 'status' => 'published', 'published_at' => now('UTC')]);
    $session->update(['active_version_id' => $version->id]);
    $state = app(LearningAccessService::class)->forUser($this->learner);
    expect($state['sessions']['A1-U1-L1']['completed'])->toBeTrue()->and($state['sessions']['A1-U1-L1']['locked'])->toBeFalse();
    $this->get(PracticeUrls::session($session))->assertOk();
    $this->get('/daily?session=A1-U1-L1')->assertOk();
    $this->postJson('/daily/api/runs', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
        'expected_revision' => 1, 'kind' => 'extra', 'session_id' => 'A1-U1-L1'])
        ->assertOk()->assertJsonPath('state.session_version_id', $version->id);
});

it('does not let missing author prerequisites bypass the route order', function () {
    $input = ['client_event_id' => (string) Illuminate\Support\Str::uuid(), 'expected_revision' => 1, 'kind' => 'extra', 'session_id' => 'PRE_A1-U2-L1'];
    $this->postJson('/daily/api/runs', $input)->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    $this->assertDatabaseCount('learning_runs', 0);
});

it('matches the shared guest route policy including completion replay', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/learning-access.json')), true);
    foreach ($fixture['cases'] as $case) {
        $states = app(LearningAccessService::class)->states($fixture['outline'], $case['completed']);
        foreach (['levels', 'units', 'sessions'] as $group) {
            $open = array_keys(array_filter($states[$group], fn ($state) => ! $state['locked']));
            expect($open)->toBe($case[$group]);
        }
    }
});

function accessAddLevel($test, string $level, int $count): void
{
    LearningLevel::where('code', $level)->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['level' => $level, 'sort_order' => 1, 'published_at' => now('UTC')]);
    $template = $test->lessons['PRE_A1-U1-L1']->activeVersion->payload;
    for ($i = 1; $i <= $count; $i++) {
        $id = $level.'-NEW-'.$i;
        $payload = [...$template, 'id' => $id, 'level' => $level, 'unit_id' => $unit->external_id, 'order' => $i, 'prerequisite_ids' => []];
        $payload['public_page']['slug'] = strtolower($id);
        $session = LearningSession::factory()->create(['external_id' => $id, 'level' => $level, 'unit_id' => $unit->id, 'sort_order' => $i, 'slug' => strtolower($id)]);
        $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $payload, 'status' => 'published', 'published_at' => now('UTC')]);
        $session->update(['active_version_id' => $version->id]);
        $test->lessons[$id] = $session->fresh()->load('unit', 'activeVersion');
    }
}

function accessFinishLesson($test, string $id): array
{
    $revision = $test->getJson('/daily/api/profile')->assertOk()->json('revision');
    $state = $test->postJson('/daily/api/runs', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
        'expected_revision' => $revision, 'kind' => 'extra', 'session_id' => $id])->assertOk()->json('state');
    while (true) {
        $state = $test->postJson('/daily/api/runs/'.$state['run_id'].'/help', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
            'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown'])->assertOk()->json('state');
        if ($state['position'] === $state['total']) {
            break;
        }
        $state = $test->postJson('/daily/api/runs/'.$state['run_id'].'/advance', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
            'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id']])->assertOk()->json('state');
    }
    $finish = ['client_event_id' => (string) Illuminate\Support\Str::uuid(), 'expected_revision' => $state['revision']];
    $receipt = $test->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $finish)->assertOk()->json();
    $test->postJson('/daily/api/runs/'.$state['run_id'].'/finish', $finish)->assertOk()->assertExactJson($receipt);

    return $receipt['state'];
}

it('starts at the profile level and opens lower levels without marking them complete', function () {
    accessAddLevel($this, 'B1', 2);
    $this->patchJson('/daily/api/profile', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
        'expected_revision' => 1, 'current_level' => 'B1', 'timezone' => 'UTC'])->assertOk();
    $state = app(LearningAccessService::class)->forUser($this->learner);
    foreach (['PRE_A1-U1-L1', 'PRE_A1-U1-L2', 'PRE_A1-U2-L1', 'A1-U1-L1'] as $id) {
        expect($state['sessions'][$id]['locked'])->toBeFalse()->and($state['sessions'][$id]['completed'])->toBeFalse()
            ->and($state['sessions'][$id]['review_only'])->toBeTrue();
        $this->get('/daily?session='.$id)->assertOk();
    }
    expect($state['sessions']['B1-NEW-1']['locked'])->toBeFalse()->and($state['sessions']['B1-NEW-2']['locked'])->toBeTrue();
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate.id', 'B1-NEW-1');
    $this->assertDatabaseCount('learning_runs', 0);
});

it('raises the profile after the current published level is complete exactly once and ignores lower level repeats', function () {
    accessAddLevel($this, 'B1', 2);
    accessAddLevel($this, 'B2', 1);
    $this->patchJson('/daily/api/profile', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
        'expected_revision' => 1, 'current_level' => 'B1', 'timezone' => 'UTC'])->assertOk();
    accessFinishLesson($this, 'B1-NEW-1');
    $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('current_level', 'B1');
    $last = accessFinishLesson($this, 'B1-NEW-2');
    expect($last['summary']['promotion']['new_level'])->toBe('B2');
    $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('current_level', 'B2');
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('candidate.id', 'B2-NEW-1');
    accessFinishLesson($this, 'PRE_A1-U1-L2');
    $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('current_level', 'B2');
    expect(\App\Models\Learning\LearningLevelEvent::where('source', 'automatic')->count())->toBe(1);
});

it('does not raise a profile on a read or incomplete level and never advances beyond C2', function () {
    accessAddLevel($this, 'C2', 1);
    $this->patchJson('/daily/api/profile', ['client_event_id' => (string) Illuminate\Support\Str::uuid(),
        'expected_revision' => 1, 'current_level' => 'C2', 'timezone' => 'UTC'])->assertOk();
    $this->get('/practice')->assertOk();
    $this->getJson('/daily/api/profile')->assertJsonPath('current_level', 'C2');
    accessFinishLesson($this, 'C2-NEW-1');
    $this->getJson('/daily/api/profile')->assertJsonPath('current_level', 'C2');
    expect(\App\Models\Learning\LearningLevelEvent::where('source', 'automatic')->count())->toBe(0);
});

it('keeps an empty profile level closed and distinguishes missing content from progression locks', function () {
    \App\Models\Learning\LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'B1']);
    LearningLevel::where('code', 'B1')->update(['published_at' => now('UTC')]);
    $state = app(LearningAccessService::class)->forUser($this->learner);
    expect($state['levels']['B1']['locked'])->toBeTrue()
        ->and($state['levels']['B1']['reason'])->toBe('Занятия ещё не опубликованы.')
        ->and($state['levels']['A1']['locked'])->toBeFalse();
    $this->get('/practice/b1')->assertRedirect('/practice');
});
