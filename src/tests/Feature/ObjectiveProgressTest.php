<?php

use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningCurriculumEnrollment;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningLevelEvent;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningObjectiveEvidence;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\LevelPromotionService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $this->actingAs($this->learner);
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-27 12:00:00', 'UTC'));
    $this->service = app(AccountRunService::class);
});
afterEach(fn () => $this->travelBack());

function objectiveInput(int $revision, array $values = []): array
{
    return ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $revision, ...$values];
}

function objectiveProgram($test, int $required = 1, ?int $covered = null, string $level = 'PRE_A1'): LearningCurriculum
{
    LearningLevel::where('code', $level)->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['level' => $level, 'published_at' => now('UTC')]);
    $ids = [];
    for ($i = 1; $i <= $required; $i++) {
        $ids[] = $level.'.GOAL.'.$i;
        LearningObjective::create(['stable_id' => end($ids), 'level' => $level, 'title' => 'Цель '.$i]);
    }
    $template = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    $sessions = [];
    foreach (['A', 'B', 'PRACTICE'] as $index => $suffix) {
        $payload = $template;
        $payload['id'] = $level.'-CONTROL-'.$suffix;
        $payload['level'] = $level;
        $payload['kind'] = $suffix === 'PRACTICE' ? 'daily' : 'checkpoint';
        $payload['prerequisite_ids'] = [];
        $payload['skills'] = ['vocabulary', 'listening'];
        $payload['objectives'] = array_map(fn ($id) => ['id' => $id, 'title' => 'Цель'], $ids);
        $payload['introductions'] = [];
        $payload['tasks'] = [];
        for ($i = 0; $i < 6; $i++) {
            $task = $template['tasks'][0];
            $task['id'] = 'Q'.($i + 1);
            $task['skill'] = $i % 2 === 0 ? 'vocabulary' : 'listening';
            $task['objective_ids'] = array_slice($ids, 0, $covered ?? $required);
            $payload['tasks'][] = $task;
        }
        $session = LearningSession::factory()->create(['external_id' => $payload['id'], 'level' => $level, 'unit_id' => $unit->id, 'sort_order' => $index + 1]);
        $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $payload,
            'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')]);
        $session->update(['active_version_id' => $version->id]);
        $sessions[] = $payload['id'];
    }

    return LearningCurriculum::factory()->create(['external_id' => $level.'-PROGRAM', 'level' => $level,
        'required_objective_ids' => $ids, 'checkpoint_session_ids' => array_slice($sessions, 0, 2),
        'status' => 'published', 'published_at' => now('UTC')]);
}

function objectiveStart($test, string $session = 'PRE_A1-CONTROL-A'): array
{
    $profile = $test->service->profile($test->learner);

    return $test->service->start($test->learner, objectiveInput($profile['revision'], ['kind' => 'extra', 'session_id' => $session]))['state'];
}

function objectiveFinish($test, array $state, int $correct = 6, ?int $technicalSkip = null, bool $correctWrong = false): array
{
    $runId = $state['run_id'];
    while (true) {
        $position = $state['position'];
        $input = objectiveInput($state['revision'], ['presentation_id' => $state['presentation']['presentation_id']]);
        if ($position === $technicalSkip) {
            $state = $test->service->event($test->learner, $runId, 'help', [...$input, 'event_type' => 'technical_skip', 'reason' => 'audio_unavailable'])['state'];
        } else {
            $state = $test->service->event($test->learner, $runId, 'answer', [...$input, 'event_type' => 'answer', 'answer' => ['choice_id' => $position <= $correct ? 'b' : 'a']])['state'];
            if ($correctWrong && $position > $correct) {
                $state = $test->service->event($test->learner, $runId, 'answer', objectiveInput($state['revision'], [
                    'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'b'],
                ]))['state'];
            }
        }
        if ($position === $state['total']) {
            break;
        }
        $state = $test->service->command($test->learner, $runId, 'advance', objectiveInput($state['revision'], ['presentation_id' => $state['presentation']['presentation_id']]))['state'];
    }

    return $test->service->command($test->learner, $runId, 'finish', objectiveInput($state['revision']));
}

it('pins the published curriculum on start while progress reads never create state or promote', function () {
    $program = objectiveProgram($this);
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('progress.required', 1)->assertJsonPath('progress.mastered', 0);
    expect(LearningCurriculumEnrollment::count())->toBe(0)->and(LearningLevelEvent::count())->toBe(0);
    objectiveStart($this);
    expect(LearningCurriculumEnrollment::sole()->curriculum_version_id)->toBe($program->id);
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('progress.mastered', 0);
    expect(LearningObjectiveEvidence::count())->toBe(0);
});

it('uses stable session and task identities across versions and ignores corrections and repeat of one question', function () {
    objectiveProgram($this);
    objectiveFinish($this, objectiveStart($this), 1, null, true);
    expect(LearningObjectiveEvidence::count())->toBe(1);
    $session = LearningSession::where('external_id', 'PRE_A1-CONTROL-A')->sole();
    $payload = $session->activeVersion->payload;
    $payload['title'] = 'Новая редакция того же контроля';
    $new = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'version' => 2, 'payload' => $payload,
        'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')]);
    $session->update(['active_version_id' => $new->id]);
    objectiveFinish($this, objectiveStart($this), 1);
    $progress = app(LevelPromotionService::class)->progress($this->learner);
    expect($progress['goals'][0]['distinct_tasks'])->toBe(1)->and($progress['goals'][0]['distinct_runs'])->toBe(2)->and($progress['mastered'])->toBe(0);
    objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'), 1);
    expect(app(LevelPromotionService::class)->progress($this->learner)['mastered'])->toBe(1)
        ->and(LearningObjectiveEvidence::count())->toBe(3)->and(LearningLevelEvent::count())->toBe(0);
});

it('requires at least ten of twelve goals without rounding and promotes exactly one level', function (int $covered, bool $promotes) {
    objectiveProgram($this, 12, $covered);
    objectiveFinish($this, objectiveStart($this));
    $this->travel(1)->days();
    $finish = objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'));
    expect(LearningProfile::sole()->getRawOriginal('current_level'))->toBe($promotes ? 'A1' : 'PRE_A1');
    expect(LearningLevelEvent::where('source', 'automatic')->count())->toBe($promotes ? 1 : 0);
    if ($promotes) {
        expect($finish['state']['summary']['promotion']['reason']['mastered'])->toBe(10)
            ->and($finish['state']['summary']['promotion']['reason']['required'])->toBe(12);
    } else {
        expect(app(LevelPromotionService::class)->progress($this->learner)['required_to_master'])->toBe(10);
    }
})->with([[9, false], [10, true]]);

it('uses independent integer checkpoint scores and refuses every technical skip', function (int $correct, ?int $skip, bool $qualifies) {
    objectiveProgram($this);
    $finish = objectiveFinish($this, objectiveStart($this), $correct, $skip);
    expect($finish['state']['summary']['checkpoint']['qualified'])->toBe($qualifies);
    expect(LearningLevelEvent::count())->toBe(0);
})->with([[4, null, false], [5, null, true], [6, 6, false]]);

it('requires two different controls and local days rather than server dates or repeated controls', function () {
    objectiveProgram($this);
    LearningProfile::factory()->create(['user_id' => $this->learner->id, 'timezone' => 'Europe/Moscow']);
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-27 23:00:00', 'UTC'));
    objectiveFinish($this, objectiveStart($this));
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-28 00:01:00', 'UTC'));
    objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'));
    $progress = app(LevelPromotionService::class)->progress($this->learner);
    expect($progress['checkpoint_count'])->toBe(2)->and($progress['checkpoint_days'])->toBe(1)->and($progress['checkpoints_met'])->toBeFalse();
    $this->travel(1)->days();
    objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'));
    expect(LearningProfile::sole()->getRawOriginal('current_level'))->toBe('A1');
});

it('keeps the enrollment denominator and policy when a larger program and new server defaults appear', function () {
    $program = objectiveProgram($this);
    objectiveFinish($this, objectiveStart($this));
    $program->update(['status' => 'archived', 'archived_at' => now('UTC')]);
    LearningCurriculum::factory()->create(['external_id' => $program->external_id, 'version' => 2, 'level' => 'PRE_A1',
        'required_objective_ids' => ['NEW.1', 'NEW.2'], 'checkpoint_session_ids' => $program->checkpoint_session_ids, 'status' => 'published', 'published_at' => now('UTC')]);
    config(['daily_practice.promotion.current_policy_version' => 99, 'daily_practice.promotion.policies.1.checkpoint_score_percent' => 100]);
    $this->travel(1)->days();
    $finish = objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'), 5);
    expect(LearningCurriculumEnrollment::sole()->curriculum_version_id)->toBe($program->id)
        ->and($finish['state']['summary']['promotion']['new_level'])->toBe('A1')
        ->and($finish['state']['summary']['promotion']['reason']['required'])->toBe(1);
});

it('does not undo a manual downgrade on GET or ordinary finish or old finish replay and waits for a new control', function () {
    objectiveProgram($this);
    objectiveFinish($this, objectiveStart($this));
    $this->travel(1)->days();
    $original = objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'));
    $profile = $this->service->profile($this->learner);
    $this->service->profileUpdate($this->learner, objectiveInput($profile['revision'], ['current_level' => 'PRE_A1', 'timezone' => 'UTC']));
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('profile.current_level', 'PRE_A1');
    objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-PRACTICE'));
    $this->service->command($this->learner, $original['state']['run_id'], 'finish', objectiveInput($original['state']['revision']));
    expect(LearningProfile::sole()->getRawOriginal('current_level'))->toBe('PRE_A1');
    $this->travel(1)->seconds();
    objectiveFinish($this, objectiveStart($this));
    expect(LearningProfile::sole()->getRawOriginal('current_level'))->toBe('A1')
        ->and(LearningLevelEvent::where('source', 'automatic')->count())->toBe(2);
});

it('disables promotion without a program or available controls and C2 has no next level', function () {
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('progress.enabled', false);
    objectiveProgram($this, 1, null, 'C2');
    LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'C2']);
    // An existing C2 learner may repeat completed controls even with earlier levels absent from this fixture.
    foreach (['C2-CONTROL-A', 'C2-CONTROL-B'] as $id) {
        $version = LearningSession::where('external_id', $id)->sole()->activeVersion;
        \App\Models\Learning\LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $version->id,
            'snapshot' => $version->payload, 'kind' => 'extra', 'status' => 'completed', 'finished_at' => now('UTC')]);
    }
    objectiveFinish($this, objectiveStart($this, 'C2-CONTROL-A'));
    $this->travel(1)->days();
    objectiveFinish($this, objectiveStart($this, 'C2-CONTROL-B'));
    expect(app(LevelPromotionService::class)->progress($this->learner)['next_level'])->toBeNull()
        ->and(LearningProfile::sole()->getRawOriginal('current_level'))->toBe('C2')->and(LearningLevelEvent::count())->toBe(0);
});
it('never creates objective evidence for helped answers and preserves evidence on an ordinary wrong repeat', function () {
    objectiveProgram($this);
    $state = objectiveStart($this);
    $state = $this->service->event($this->learner, $state['run_id'], 'help', objectiveInput($state['revision'], [
        'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'help', 'help_kind' => 'audio_transcript',
    ]))['state'];
    objectiveFinish($this, $state, 1);
    expect(LearningObjectiveEvidence::count())->toBe(0);
    objectiveFinish($this, objectiveStart($this), 2);
    objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'), 2);
    expect(app(LevelPromotionService::class)->progress($this->learner)['mastered'])->toBe(1);
    objectiveFinish($this, objectiveStart($this), 0);
    expect(app(LevelPromotionService::class)->progress($this->learner)['mastered'])->toBe(1)
        ->and(LearningLevelEvent::count())->toBe(0);
});

it('does not confuse different controls on one day or repeated one control on two days with a qualifying pair', function (bool $sameSession) {
    objectiveProgram($this);
    objectiveFinish($this, objectiveStart($this));
    if ($sameSession) {
        $this->travel(1)->days();
    }
    objectiveFinish($this, objectiveStart($this, $sameSession ? 'PRE_A1-CONTROL-A' : 'PRE_A1-CONTROL-B'));
    expect(app(LevelPromotionService::class)->progress($this->learner)['checkpoints_met'])->toBeFalse()
        ->and(LearningLevelEvent::count())->toBe(0);
})->with([true, false]);

it('requires vocabulary and a second independently demonstrated skill in the chosen checkpoint pair', function () {
    objectiveProgram($this);
    foreach (LearningSession::with('activeVersion')->get() as $session) {
        $payload = $session->activeVersion->payload;
        foreach ($payload['tasks'] as &$task) {
            $task['skill'] = 'vocabulary';
        }
        unset($task);
        $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'version' => 2,
            'payload' => $payload, 'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')]);
        $session->update(['active_version_id' => $version->id]);
    }
    objectiveFinish($this, objectiveStart($this));
    $this->travel(1)->days();
    objectiveFinish($this, objectiveStart($this, 'PRE_A1-CONTROL-B'));
    expect(app(LevelPromotionService::class)->progress($this->learner)['checkpoints_met'])->toBeFalse()
        ->and(LearningProfile::sole()->getRawOriginal('current_level'))->toBe('PRE_A1');
});

it('retains mastery while disabling promotion when a required checkpoint or its catalog is archived', function (bool $archiveUnit) {
    objectiveProgram($this);
    objectiveFinish($this, objectiveStart($this));
    $this->travel(1)->days();
    $state = objectiveStart($this, 'PRE_A1-CONTROL-B');
    $session = LearningSession::where('external_id', 'PRE_A1-CONTROL-A')->sole();
    ($archiveUnit ? $session->unit : $session)->update(['archived_at' => now('UTC')]);
    objectiveFinish($this, $state);
    $progress = app(LevelPromotionService::class)->progress($this->learner);
    expect($progress['enabled'])->toBeFalse()->and($progress['mastered'])->toBe(1)->and(LearningLevelEvent::count())->toBe(0);
})->with([false, true]);

it('rechecks imported objective answers and checkpoints and repacking does not duplicate promotion', function (bool $badClock) {
    objectiveProgram($this);
    LearningProfile::factory()->create(['user_id' => $this->learner->id]);
    $this->travel(4)->days();
    $guest = (string) Str::uuid();
    $sequence = 1;
    $bundles = [];
    foreach (['A', 'B'] as $index => $suffix) {
        $version = LearningSession::where('external_id', 'PRE_A1-CONTROL-'.$suffix)->sole()->activeVersion;
        $run = (string) Str::uuid();
        $stamp = now('UTC')->subDays(3 - $index)->toIso8601String();
        $presentations = array_map(fn () => (string) Str::uuid(), $version->payload['tasks']);
        $events = [['client_event_id' => (string) Str::uuid(), 'sequence' => $sequence++, 'action' => 'start', 'run_id' => $run, 'client_occurred_at' => $stamp]];
        $revision = 1;
        foreach ($presentations as $i => $presentation) {
            $events[] = ['client_event_id' => (string) Str::uuid(), 'sequence' => $sequence++, 'action' => 'answer', 'event_type' => 'answer',
                'run_id' => $run, 'expected_revision' => $revision++, 'presentation_id' => $presentation, 'answer' => ['choice_id' => 'b'], 'client_occurred_at' => $stamp];
            if ($i < 5) {
                $events[] = ['client_event_id' => (string) Str::uuid(), 'sequence' => $sequence++, 'action' => 'advance', 'run_id' => $run,
                    'expected_revision' => $revision++, 'presentation_id' => $presentation, 'client_occurred_at' => $stamp];
            }
        }
        $events[] = ['client_event_id' => (string) Str::uuid(), 'sequence' => $sequence++, 'action' => 'finish', 'run_id' => $run, 'expected_revision' => $revision, 'client_occurred_at' => $stamp];
        $bundles[] = ['guest_run_id' => $run, 'kind' => 'extra', 'session_version_id' => $version->id, 'snapshot' => $version->payload,
            'checking_policy_version' => 1, 'presentation_ids' => $presentations, 'timezone' => 'UTC', 'local_date' => substr($stamp, 0, 10), 'started_at' => $stamp, 'events' => $events];
    }
    if ($badClock) {
        $last = count($bundles[1]['events']) - 1;
        $bundles[1]['events'][$last]['client_occurred_at'] = now('UTC')->addDay()->toIso8601String();
    }
    $input = ['guest_id' => $guest, 'episode_id' => null, 'batch_id' => (string) Str::uuid(), 'level_events' => [], 'marks' => [], 'runs' => $bundles];
    $this->postJson('/daily/api/guest-sync', $input)->assertOk()->assertJsonCount(0, 'rejected');
    expect(LearningProfile::sole()->getRawOriginal('current_level'))->toBe($badClock ? 'PRE_A1' : 'A1')
        ->and(LearningLevelEvent::where('source', 'automatic')->count())->toBe($badClock ? 0 : 1)
        ->and(LearningObjectiveEvidence::count())->toBe(12);
    $input['batch_id'] = (string) Str::uuid();
    $this->postJson('/daily/api/guest-sync', $input)->assertOk()->assertJsonCount(0, 'rejected');
    expect(LearningLevelEvent::where('source', 'automatic')->count())->toBe($badClock ? 0 : 1)
        ->and(LearningObjectiveEvidence::count())->toBe(12);
})->with([false, true]);

it('cannot promote from a control started before a manual level change', function () {
    objectiveProgram($this);
    objectiveFinish($this, objectiveStart($this));
    $this->travel(1)->days();
    $oldControl = objectiveStart($this, 'PRE_A1-CONTROL-B');
    foreach (['A1', 'PRE_A1'] as $level) {
        $profile = $this->service->profile($this->learner);
        $this->service->profileUpdate($this->learner, objectiveInput($profile['revision'], ['current_level' => $level, 'timezone' => 'UTC']));
    }
    objectiveFinish($this, $oldControl);
    expect(LearningProfile::sole()->getRawOriginal('current_level'))->toBe('PRE_A1')
        ->and(LearningLevelEvent::where('source', 'automatic')->count())->toBe(0);
});

it('preserves objective identities and evidence as immutable records and enrollment policies', function () {
    $program = objectiveProgram($this);
    $objective = LearningObjective::sole();
    expect(fn () => $objective->update(['stable_id' => 'REPLACEMENT']))->toThrow(\LogicException::class);
    objectiveFinish($this, objectiveStart($this), 1);
    $evidence = LearningObjectiveEvidence::sole();
    expect(fn () => $evidence->update(['eligible' => false]))->toThrow(\LogicException::class);
    $this->getJson('/daily/api/today')->assertOk()->assertJsonPath('progress.policy_version', 1);
    $enrollment = LearningCurriculumEnrollment::sole();
    expect(fn () => $enrollment->update(['promotion_policy_version' => 999]))->toThrow(\LogicException::class);
});
