<?php

use App\Enums\Learning\RunStatus;
use App\Models\Learning\LearningActivityDay;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningPresentation;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\AnswerCheckingException;
use App\Services\Learning\CheckingPolicy;
use App\Services\Learning\PresentationSnapshot;
use App\Services\Learning\RunAnswerService;
use App\Services\Learning\SessionResultCalculator;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $this->session = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/pre-a1-u01-examples.json')), true)['sessions'][0];
    $this->version = LearningSessionVersion::factory()->create(['payload' => $this->session, 'checksum' => CanonicalJson::hash($this->session)]);
    $this->run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $this->version->id]);
    $this->presentations = [];
    foreach ($this->session['tasks'] as $index => $task) {
        $this->presentations[] = LearningPresentation::factory()->create([
            'run_id' => $this->run->id, 'position' => $index + 1, 'task_id' => $task['id'],
            'snapshot' => PresentationSnapshot::forTask($this->session, $task['id']),
            'choice_order' => array_column($task['choices'] ?? [], 'id'),
        ]);
    }
    $this->answers = app(RunAnswerService::class);
});

function validTaskAnswer(array $task): array
{
    return match ($task['type']) {
        'single_choice' => ['choice_id' => $task['correct_choice_id']],
        'fill_blank' => $task['input_mode'] === 'choice' ? ['choice_id' => $task['correct_choice_id']] : ['value' => $task['accepted_answers'][0]],
        'short_answer' => ['value' => $task['accepted_answers'][0]],
        'word_order' => ['token_ids' => $task['accepted_orders'][0]],
        'matching_pairs' => ['pairs' => array_map(fn (array $pair): array => ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']], $task['correct_pairs'])],
    };
}

function learnerEvent(LearningRun $run, LearningPresentation $presentation, string $type = 'answer', array $fields = []): array
{
    return ['client_event_id' => (string) Str::uuid(), 'presentation_id' => $presentation->id,
        'expected_revision' => $run->fresh()->revision, 'event_type' => $type,
        ...($type === 'answer' ? ['answer' => validTaskAnswer($presentation->snapshot['task'])] : []), ...$fields];
}

function checkingErrorCode(Closure $fn): string
{
    try {
        $fn();
    } catch (AnswerCheckingException $exception) {
        return $exception->errorCode->value;
    }

    return 'no_error';
}

it('stores the first answer and policy-derived result without finishing the run or changing vocabulary', function () {
    $event = learnerEvent($this->run, $this->presentations[0], 'answer', ['client_occurred_at' => '2000-01-01T03:00:00+03:00']);
    $receipt = $this->answers->accept($this->learner, $this->run->id, $event);
    $attempt = LearningAttempt::find($receipt['attempt_id']);
    expect($receipt['result']['correct'])->toBeTrue()
        ->and($receipt['result']['first_independent_success'])->toBeTrue()
        ->and($receipt['result']['score'])->toBe(1)
        ->and($receipt['result']['objective_evidence_eligible'])->toBeTrue()
        ->and($attempt->is_first_answer)->toBeTrue()
        ->and($attempt->received_at->getTimezone()->getName())->toBe('UTC')
        ->and($attempt->client_occurred_at->toIso8601String())->toBe('2000-01-01T00:00:00+00:00')
        ->and($this->run->refresh()->status)->toBe(RunStatus::InProgress)
        ->and($this->run->result)->toBeNull()
        ->and($this->run->checking_policy_snapshot)->toBe(CheckingPolicy::forVersion(1)->rules)
        ->and(PersonalVocabulary::count())->toBe(0)
        ->and(LearningActivityDay::count())->toBe(0);
});

it('keeps help first wrong answer and correction distinct without replacing the first score', function () {
    $presentation = $this->presentations[0];
    $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $presentation, 'help', ['help_kind' => 'audio_transcript']));
    $task = $presentation->snapshot['task'];
    $wrong = collect($task['choices'])->first(fn (array $choice): bool => $choice['id'] !== $task['correct_choice_id'])['id'];
    $first = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $presentation, 'answer', ['answer' => ['choice_id' => $wrong]]));
    $corrected = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $presentation));
    expect($first['result']['correct'])->toBeFalse()->and($first['result']['help_used'])->toBeTrue()
        ->and(array_column($first['result']['assessed_lexemes'], 'effect'))->each->toBe('error');
    expect($corrected['result']['correct'])->toBeTrue()->and($corrected['result']['score'])->toBe(0)
        ->and($corrected['result']['is_first_answer'])->toBeFalse()
        ->and($corrected['result']['objective_evidence_eligible'])->toBeFalse()
        ->and(array_column($corrected['result']['assessed_lexemes'], 'effect'))->each->toBe('none');
    expect($presentation->firstAttempt->id)->toBe($first['attempt_id'])
        ->and(LearningAttempt::where('is_first_answer', true)->count())->toBe(1)
        ->and(LearningAttempt::whereNull('is_first_answer')->count())->toBe(2)
        ->and($this->answers->result($this->learner, $this->run->id)['score_numerator'])->toBe(0);
});

it('treats technical skip as a processed step without language errors', function () {
    $receipt = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $this->presentations[0], 'technical_skip', ['reason' => 'audio_unavailable']));
    expect($receipt['result']['correct'])->toBeNull()
        ->and(array_column($receipt['result']['assessed_lexemes'], 'effect'))->each->toBe('none');
    $summary = $this->answers->result($this->learner, $this->run->id);
    expect($summary['technical_skips'])->toBe(1)->and($summary['processed_steps'])->toBe(1)->and($summary['score_denominator'])->toBe(6)
        ->and((new SessionResultCalculator)->meetsCheckpointThreshold($summary, 0))->toBeFalse();
});

it('calculates a complete session independently of finish and keeps every step in the denominator', function () {
    foreach ($this->presentations as $presentation) {
        $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $presentation));
    }
    $summary = $this->answers->result($this->learner, $this->run->id);
    expect($summary['score_numerator'])->toBe(6)->and($summary['score_denominator'])->toBe(6)
        ->and($summary['all_steps_processed'])->toBeTrue()
        ->and($summary['score_percent'])->toEqual(100)
        ->and($this->run->refresh()->status)->toBe(RunStatus::InProgress)
        ->and($this->run->finished_at)->toBeNull()
        ->and($this->run->result)->toBeNull();
    expect((new SessionResultCalculator)->meetsCheckpointThreshold($summary, 80))->toBeTrue();
});

it('replays the original receipt after other events or completed status without mutating results', function () {
    $event = learnerEvent($this->run, $this->presentations[0]);
    $receipt = $this->answers->accept($this->learner, $this->run->id, $event);
    $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $this->presentations[1], 'help', ['help_kind' => 'hint']));
    expect($this->answers->accept($this->learner, $this->run->id, $event))->toBe($receipt);
    $summary = $this->answers->result($this->learner, $this->run->id);
    $this->run->refresh()->update(['status' => 'completed', 'result' => $summary, 'finished_at' => now('UTC')]);
    expect($this->answers->accept($this->learner, $this->run->id, $event))->toBe($receipt)
        ->and(LearningAttempt::count())->toBe(2)
        ->and($this->run->refresh()->result)->toBe($summary);
    $new = learnerEvent($this->run, $this->presentations[0]);
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $this->run->id, $new)))->toBe('revision_conflict');
});

it('rejects reused event IDs with a different body and stale revisions', function () {
    $event = learnerEvent($this->run, $this->presentations[0]);
    $this->answers->accept($this->learner, $this->run->id, $event);
    $changed = [...$event, 'expected_revision' => 2];
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $this->run->id, $changed)))->toBe('idempotency_conflict');
    $other = [...learnerEvent($this->run, $this->presentations[1]), 'expected_revision' => 1];
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $this->run->id, $other)))->toBe('revision_conflict')
        ->and(LearningAttempt::count())->toBe(1)
        ->and($this->presentations[1]->refresh()->status)->toBe('pending');
});

it('rejects foreign ownership or presentation bindings without revealing another run', function () {
    $event = learnerEvent($this->run, $this->presentations[0]);
    $other = User::factory()->create();
    expect(checkingErrorCode(fn () => $this->answers->accept($other, $this->run->id, $event)))->toBe('not_found')
        ->and(checkingErrorCode(fn () => $this->answers->accept(null, $this->run->id, $event)))->toBe('unauthenticated')
        ->and(checkingErrorCode(fn () => $this->answers->result($other, $this->run->id)))->toBe('not_found');
    $foreign = LearningRun::factory()->create(['user_id' => $this->learner->id]);
    $presentation = LearningPresentation::factory()->create(['run_id' => $foreign->id]);
    $event['presentation_id'] = $presentation->id;
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $this->run->id, $event)))->toBe('not_found')
        ->and(LearningAttempt::count())->toBe(0);
});

it('rejects invalid input atomically and then accepts a valid first answer', function (string $kind) {
    $event = learnerEvent($this->run, $this->presentations[0]);
    match ($kind) {
        'client_score' => $event['score'] = 1,
        'client_correct' => $event['answer']['correct'] = true,
        'client_needs_review' => $event['needs_review'] = false,
        'client_policy' => $event['checking_policy_version'] = 999,
        'foreign_choice' => $event['answer'] = ['choice_id' => 'foreign'],
        'no_answer' => $event['answer'] = [],
        'wrong_uuid' => $event['client_event_id'] = 'bad',
        'wrong_revision_type' => $event['expected_revision'] = true,
        'date_overflow' => $event['client_occurred_at'] = '2026-02-30T00:00:00Z',
        'bad_date' => $event['client_occurred_at'] = 'tomorrow',
        'bad_utf8' => $event['answer']['value'] = "\xFF",
    };
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $this->run->id, $event)))->toBe('validation_failed')
        ->and(LearningAttempt::count())->toBe(0)
        ->and($this->run->refresh()->revision)->toBe(1)
        ->and($this->presentations[0]->refresh()->firstAttempt)->toBeNull();
    $valid = learnerEvent($this->run, $this->presentations[0]);
    $receipt = $this->answers->accept($this->learner, $this->run->id, $valid);
    expect($receipt['result']['first_independent_success'])->toBeTrue();
})->with(['client_score', 'client_correct', 'client_needs_review', 'client_policy', 'foreign_choice', 'no_answer', 'wrong_uuid', 'wrong_revision_type', 'date_overflow', 'bad_date', 'bad_utf8']);

it('uses the pinned policy and fails closed for unsupported or mismatched snapshots', function () {
    config(['daily_practice.checking.current_version' => 999]);
    $receipt = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $this->presentations[0]));
    expect($receipt['result']['checking_policy_version'])->toBe(1);
    foreach ([['checking_policy_version' => 2], ['checking_policy_snapshot' => ['forged' => true]]] as $attributes) {
        $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $this->version->id, ...$attributes]);
        $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'snapshot' => $this->presentations[0]->snapshot]);
        expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $run->id, learnerEvent($run, $presentation))))->toBe('unsupported_checking_policy');
    }
});

it('does not change the denominator when presentations are missing', function () {
    $this->presentations[5]->delete();
    $summary = $this->answers->result($this->learner, $this->run->id);
    expect($summary['score_denominator'])->toBe(6)->and($summary['processed_steps'])->toBe(0)->and($summary['all_steps_processed'])->toBeFalse();
});

it('rejects a presentation snapshot that does not match its position in the run', function () {
    $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $this->version->id]);
    $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'position' => 1, 'snapshot' => $this->presentations[1]->snapshot]);
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $run->id, learnerEvent($run, $presentation))))->toBe('unavailable')
        ->and(LearningAttempt::count())->toBe(0);
});

it('keeps UTC event instants and the local calendar date in a non-UTC application', function () {
    $previous = date_default_timezone_get();
    date_default_timezone_set('Europe/Moscow');
    try {
        $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $this->version->id, 'local_date' => '2026-10-24']);
        $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'task_id' => $this->session['tasks'][0]['id'], 'snapshot' => $this->presentations[0]->snapshot]);
        $this->answers->accept($this->learner, $run->id, learnerEvent($run, $presentation));
        expect($run->refresh()->local_date->format('Y-m-d'))->toBe('2026-10-24')
            ->and($presentation->firstAttempt->received_at->getTimezone()->getName())->toBe('UTC');
    } finally {
        date_default_timezone_set($previous);
    }
});

it('grades an existing run against its old snapshot after another version becomes active', function () {
    $payload = $this->session;
    $payload['version'] = 2;
    $task = &$payload['tasks'][0];
    $task['correct_choice_id'] = collect($task['choices'])->first(fn (array $choice): bool => $choice['id'] !== $task['correct_choice_id'])['id'];
    $new = LearningSessionVersion::factory()->create(['session_id' => $this->version->session_id, 'version' => 2, 'payload' => $payload, 'status' => 'published', 'published_at' => now('UTC')]);
    $this->version->session->update(['active_version_id' => $new->id]);
    $receipt = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $this->presentations[0]));
    expect($receipt['result']['correct'])->toBeTrue()->and($receipt['result']['score'])->toBe(1)
        ->and($this->run->refresh()->session_version_id)->toBe($this->version->id);
});

it('rejects an inconsistent persisted task identity', function () {
    $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $this->version->id]);
    $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'task_id' => 'OTHER', 'snapshot' => $this->presentations[0]->snapshot]);
    expect(checkingErrorCode(fn () => $this->answers->accept($this->learner, $run->id, learnerEvent($run, $presentation))))->toBe('unavailable');
});

it('signals a new lexical error on a wrong correction without rewriting an earlier independent score', function () {
    $presentation = $this->presentations[0];
    $first = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $presentation));
    $task = $presentation->snapshot['task'];
    $wrong = collect($task['choices'])->first(fn (array $choice): bool => $choice['id'] !== $task['correct_choice_id'])['id'];
    $correction = $this->answers->accept($this->learner, $this->run->id, learnerEvent($this->run, $presentation, 'answer', ['answer' => ['choice_id' => $wrong]]));
    expect($correction['result']['score'])->toBe(1)->and($correction['result']['first_independent_success'])->toBeFalse()
        ->and(array_column($correction['result']['assessed_lexemes'], 'effect'))->each->toBe('error');
    expect($presentation->firstAttempt->result['correct'])->toBeTrue()
        ->and($presentation->firstAttempt->id)->toBe($first['attempt_id'])
        ->and($this->answers->result($this->learner, $this->run->id)['score_numerator'])->toBe(1);
});

it('persists all five task types including both fill modes from the complete author fixture', function () {
    $session = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/task-types.json')), true)['sessions'][0];
    $version = LearningSessionVersion::factory()->create(['payload' => $session]);
    $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $version->id]);
    foreach ($session['tasks'] as $index => $task) {
        $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'task_id' => $task['id'], 'position' => $index + 1,
            'snapshot' => PresentationSnapshot::forTask($session, $task['id'])]);
        $receipt = $this->answers->accept($this->learner, $run->id, learnerEvent($run, $presentation));
        expect($receipt['result']['correct'])->toBeTrue()->and($receipt['result']['score'])->toBe(1);
    }
    expect($this->answers->result($this->learner, $run->id)['score_numerator'])->toBe(6);
});

it('checks a pinned vocabulary review item without issuing session objective evidence', function () {
    $word = $this->session['vocabulary'][0];
    $task = $word['review_items'][0];
    $snapshot = PresentationSnapshot::forReview($task, $word['lexeme_key']);
    $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => null, 'kind' => 'vocabulary_test', 'snapshot' => ['items' => [$snapshot]]]);
    $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'task_id' => $task['id'], 'snapshot' => $snapshot]);
    $receipt = $this->answers->accept($this->learner, $run->id, learnerEvent($run, $presentation));
    expect($receipt['result']['assessed_lexemes'])->toBe([['lexeme_key' => $word['lexeme_key'], 'correct' => true, 'effect' => 'success']])
        ->and($receipt['result']['objective_evidence_eligible'])->toBeFalse()
        ->and($this->answers->result($this->learner, $run->id)['score_denominator'])->toBe(1);
});
