<?php

use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
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
    $template = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
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
        $version = lessonWithContent(['session_id' => $session->id, 'payload' => $payload,
            'checksum' => CanonicalJson::hash($payload), 'status' => 'published', 'published_at' => now('UTC')]);
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

it('raises the level after all route lessons even when an editorial program is published', function () {
    objectiveProgram($this);
    $finish = fn (string $id) => objectiveFinish($this, objectiveStart($this, $id), 0)['state'];

    $first = $finish('PRE_A1-CONTROL-A');
    expect($first['summary']['promotion'])->toBeNull();
    $second = $finish('PRE_A1-CONTROL-B');
    expect($second['summary']['promotion'])->toBeNull();

    $last = $finish('PRE_A1-CONTROL-PRACTICE');
    expect($last['summary']['promotion']['new_level'])->toBe('A1');
    $this->getJson('/daily/api/profile')->assertJsonPath('current_level', 'A1');
});

it('continues to withhold playback answer keys for a control lesson', function () {
    objectiveProgram($this);
    $state = objectiveStart($this);

    $this->getJson('/daily/api/runs/'.$state['run_id'].'?playback=1')->assertOk()->assertJsonMissingPath('playback');
});
