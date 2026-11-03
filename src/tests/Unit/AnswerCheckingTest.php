<?php

use App\Services\Learning\AnswerChecker;
use App\Services\Learning\AnswerCheckingException;
use App\Services\Learning\AnswerNormalizer;
use App\Services\Learning\CheckingPolicy;
use App\Services\Learning\PresentationEvaluator;
use App\Services\Learning\PresentationSnapshot;
use App\Services\Learning\SessionResultCalculator;
use App\Services\Learning\Validation\CanonicalJson;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

function answerCorpus(): array
{
    static $corpus;

    return $corpus ??= json_decode(file_get_contents(__DIR__.'/../Fixtures/Learning/answer-checking-v1.json'), true, 512, JSON_THROW_ON_ERROR);
}

function answerSnapshot(array $vector): array
{
    $corpus = answerCorpus();
    $task = $corpus['tasks'][$vector['task']];
    if (isset($vector['review_lexeme_key'])) {
        unset($task['objective_ids'], $task['skill'], $task['assessed_vocabulary_ids']);

        return PresentationSnapshot::forReview($task, $vector['review_lexeme_key']);
    }

    return ['task' => $task, 'lexemes' => array_map(fn (array $word): array => [...$word, 'lexeme_key' => $vector['lexeme_overrides'][$word['id']] ?? $word['lexeme_key']], $corpus['lexemes']), 'review_lexeme_key' => null];
}

function answerSequence(array $vector): array
{
    $evaluator = new PresentationEvaluator(new AnswerChecker);
    $state = PresentationEvaluator::initial('p1');
    $states = [];
    foreach ($vector['events'] as $event) {
        $state = $evaluator->apply(answerSnapshot($vector), $state, ['presentation_id' => 'p1', ...$event], 1);
        $states[] = $state;
    }

    return $states;
}

function answerSummaryStates(array $vector): array
{
    $states = [];
    for ($index = 0; $index < $vector['processed']; $index++) {
        $id = 'summary-'.$index;
        $states[] = [...PresentationEvaluator::initial($id), 'status' => 'answered', 'first_result' => [
            'checking_policy_version' => 1, 'presentation_id' => $id, 'is_first_answer' => true,
            'score' => $index < $vector['earned'] ? 1 : 0, 'technical_skip' => $index >= $vector['processed'] - $vector['skips'],
        ]];
    }

    return $states;
}

function answerCaptured(Closure $fn): array
{
    try {
        return $fn();
    } catch (AnswerCheckingException $exception) {
        return ['error' => $exception->errorCode->value];
    }
}

it('normalizes text using the common Unicode corpus', function (array $vector) {
    expect(AnswerNormalizer::normalize($vector['input']))->toBe($vector['expected']);
})->with(array_map(fn (array $v): array => [$v], answerCorpus()['normalization']));

it('checks every answer shape and author-defined alternative', function (array $vector) {
    $task = answerCorpus()['tasks'][$vector['task']];
    $copy = $task;
    $result = answerCaptured(fn (): array => (new AnswerChecker)->check($task, $vector['answer'], 1)->toArray());
    if (isset($vector['error'])) {
        expect($result)->toBe(['error' => $vector['error']]);
    } else {
        expect($result['correct'])->toBe($vector['expected']['correct']);
        if (isset($vector['expected']['pair_correct'])) {
            expect(array_column($result['pairs'], 'correct'))->toBe($vector['expected']['pair_correct']);
        }
    }
    expect($task)->toBe($copy);
})->with(array_map(fn (array $v): array => [$v], answerCorpus()['checks']));

it('keeps the first score and derives lexical effects for event sequences', function (array $vector) {
    $states = answerSequence($vector);
    $state = $states[array_key_last($states)];
    $result = $state['last_result'];
    $actual = ['score' => $result['score'], 'correct' => $result['correct'], 'first_correct' => $state['first_result']['correct'] ?? null,
        'first_independent_success' => $result['first_independent_success'], 'objective_evidence_eligible' => $result['objective_evidence_eligible'],
        'help_used' => $state['help_used'], 'effects' => array_column($result['assessed_lexemes'], 'effect')];
    if (isset($vector['expected']['status'])) {
        $actual['status'] = $state['status'];
    }
    if (isset($vector['expected']['first_technical_skip'])) {
        $actual['first_technical_skip'] = $state['first_result']['technical_skip'];
    }
    expect($actual)->toBe($vector['expected']);
    $first = null;
    foreach ($states as $item) {
        if ($item['first_result'] !== null) {
            $first ??= $item['first_result'];
            expect($item['first_result'])->toBe($first);
        }
        if ($item['last_result']['event_type'] === 'help') {
            expect($item['last_result']['solution'])->toBeNull()->and($item['last_result']['assessed_lexemes'])->toBe([]);
        }
    }
})->with(array_map(fn (array $v): array => [$v], answerCorpus()['sequences']));

it('rejects invalid events without changing presentation state', function (array $vector) {
    $state = PresentationEvaluator::initial('p1');
    $result = answerCaptured(fn (): array => (new PresentationEvaluator(new AnswerChecker))->apply(answerSnapshot($vector), $state, ['presentation_id' => 'p1', ...$vector['event']], 1));
    expect($result)->toBe(['error' => 'validation_failed'])->and($state)->toBe(PresentationEvaluator::initial('p1'));
})->with(array_map(fn (array $v): array => [$v], answerCorpus()['invalid_events']));

it('uses all assessed steps as denominator and exact integer threshold comparison', function (array $vector) {
    $calculator = new SessionResultCalculator;
    $result = $calculator->calculate(answerSummaryStates($vector), $vector['total'], 1);
    expect($result['score_numerator'])->toBe($vector['earned'])
        ->and($result['score_denominator'])->toBe($vector['total'])
        ->and($result['processed_steps'])->toBe($vector['processed'])
        ->and($calculator->meetsCheckpointThreshold($result, $vector['threshold']))->toBe($vector['eligible']);
})->with(array_map(fn (array $v): array => [$v], answerCorpus()['summaries']));

it('rejects unavailable policies malformed Unicode and duplicate or forged first scores', function () {
    expect(fn () => (new AnswerChecker)->check(answerCorpus()['tasks']['choice'], ['choice_id' => 'a'], 2))->toThrow(AnswerCheckingException::class);
    expect(fn () => (new AnswerChecker)->check(answerCorpus()['tasks']['short'], ['value' => str_repeat('a', 20001)], 1))->toThrow(AnswerCheckingException::class);
    expect(fn () => (new AnswerChecker)->check(answerCorpus()['tasks']['short'], ['value' => "\xFF"], 1))->toThrow(AnswerCheckingException::class);
    $state = answerSequence(collect(answerCorpus()['sequences'])->firstWhere('name', 'first correct'))[0];
    expect(fn () => (new SessionResultCalculator)->calculate([$state, $state], 2, 1))->toThrow(InvalidArgumentException::class);
    $state['first_result']['score'] = 2;
    expect(fn () => (new SessionResultCalculator)->calculate([$state], 1, 1))->toThrow(InvalidArgumentException::class);
    expect(CheckingPolicy::forVersion(1)->runAttributes())->toBe(['checking_policy_version' => 1, 'checking_policy_snapshot' => CheckingPolicy::forVersion(1)->rules]);
});

it('matches the complete JavaScript results including all transitions and pair details', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('Node is required for additional PHP/JS parity testing.');
    }
    $process = new Process([$node, dirname(__DIR__).'/js/answer-checking-corpus.mjs']);
    $process->mustRun();
    $js = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    $corpus = answerCorpus();
    $calculator = new SessionResultCalculator;
    $php = [
        'normalization' => array_map(fn (array $v): string => AnswerNormalizer::normalize($v['input']), $corpus['normalization']),
        'checks' => array_map(fn (array $v): array => answerCaptured(fn (): array => (new AnswerChecker)->check($corpus['tasks'][$v['task']], $v['answer'], 1)->toArray()), $corpus['checks']),
        'sequences' => array_map(answerSequence(...), $corpus['sequences']),
        'invalid_events' => array_map(fn (array $v): array => answerCaptured(fn (): array => (new PresentationEvaluator(new AnswerChecker))->apply(answerSnapshot($v), PresentationEvaluator::initial('p1'), ['presentation_id' => 'p1', ...$v['event']], 1)), $corpus['invalid_events']),
        'summaries' => array_map(function (array $v) use ($calculator): array {
            $result = $calculator->calculate(answerSummaryStates($v), $v['total'], 1);

            return ['result' => $result, 'eligible' => $calculator->meetsCheckpointThreshold($result, $v['threshold'])];
        }, $corpus['summaries']),
    ];
    foreach ($php as $group => $cases) {
        foreach ($cases as $index => $case) {
            expect(CanonicalJson::hash($case), $group.' case '.$index)->toBe(CanonicalJson::hash($js[$group][$index]));
        }
    }
});
