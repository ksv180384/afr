import assert from 'node:assert/strict';
import test from 'node:test';
import { corpus, corpusSnapshot, sequenceStates, summaryStates } from './answer-checking-corpus.mjs';
import { normalizeAnswer, checkAnswer, initialPresentation, evaluateEvent, calculateSessionResult, meetsCheckpointThreshold, taskSnapshot } from '../../resources/js/App/Learning/answerChecker.js';

for (const vector of corpus.normalization) {
  test('normalization: ' + vector.input, () => assert.equal(normalizeAnswer(vector.input), vector.expected));
}
for (const vector of corpus.checks) {
  test('answer: ' + vector.name, () => {
    const original = structuredClone(corpus.tasks[vector.task]);
    if (vector.error) {
      assert.throws(() => checkAnswer(original, vector.answer, 1), (error) => error.code === vector.error);
    } else {
      const result = checkAnswer(original, vector.answer, 1);
      assert.equal(result.correct, vector.expected.correct);
      if (vector.expected.pair_correct) assert.deepEqual(result.pairs.map(({ correct }) => correct), vector.expected.pair_correct);
    }
    assert.deepEqual(original, corpus.tasks[vector.task]);
  });
}
for (const vector of corpus.sequences) {
  test('events: ' + vector.name, () => {
    const states = sequenceStates(vector);
    const state = states.at(-1);
    const result = state.last_result;
    const actual = { score: result.score, correct: result.correct, first_correct: state.first_result?.correct ?? null,
      first_independent_success: result.first_independent_success, objective_evidence_eligible: result.objective_evidence_eligible,
      help_used: state.help_used, effects: result.assessed_lexemes.map(({ effect }) => effect) };
    if (vector.expected.status) actual.status = state.status;
    if (vector.expected.first_technical_skip) actual.first_technical_skip = state.first_result.technical_skip;
    assert.deepEqual(actual, vector.expected);
    const first = states.find(({ first_result }) => first_result !== null)?.first_result;
    if (first) for (const item of states.filter(({ first_result }) => first_result !== null)) assert.deepEqual(item.first_result, first);
    for (const item of states) {
      if (item.last_result.event_type === 'help') {
        assert.equal(item.last_result.solution, null);
        assert.deepEqual(item.last_result.assessed_lexemes, []);
      }
    }
  });
}
for (const vector of corpus.invalid_events) {
  test('invalid event: ' + vector.name, () => {
    const state = initialPresentation('p1');
    assert.throws(() => evaluateEvent(corpusSnapshot(vector), state, { presentation_id: 'p1', ...vector.event }, 1), (error) => error.code === 'validation_failed');
    assert.deepEqual(state, initialPresentation('p1'));
  });
}
for (const vector of corpus.summaries) {
  test('score ratio: ' + JSON.stringify(vector), () => {
    const result = calculateSessionResult(summaryStates(vector), vector.total, 1);
    assert.equal(result.score_numerator, vector.earned);
    assert.equal(result.score_denominator, vector.total);
    assert.equal(result.processed_steps, vector.processed);
    assert.equal(meetsCheckpointThreshold(result, vector.threshold), vector.eligible);
  });
}
test('unknown versions fail closed and no browser globals are needed', () => {
  assert.equal(typeof window, 'undefined');
  assert.throws(() => checkAnswer(corpus.tasks.choice, { choice_id: 'a' }, 2), (error) => error.code === 'unsupported_checking_policy');
  assert.throws(() => evaluateEvent(corpusSnapshot({ task: 'choice' }), initialPresentation('p1'), { presentation_id: 'p1', event_type: 'unknown' }, 2));
  assert.throws(() => calculateSessionResult([], 0, 2));
});
test('input lengths Unicode and forged/duplicate states are rejected', () => {
  assert.throws(() => checkAnswer(corpus.tasks.short, { value: 'a'.repeat(20001) }, 1));
  assert.throws(() => checkAnswer(corpus.tasks.short, { value: '\ud800' }, 1));
  const state = sequenceStates(corpus.sequences.find(({ name }) => name === 'first correct'))[0];
  assert.throws(() => calculateSessionResult([state, state], 2, 1));
  state.first_result.score = 2;
  assert.throws(() => calculateSessionResult([state], 1, 1));
});
test('snapshot builders copy content and bind vocabulary explicitly', () => {
  const session = { tasks: [corpus.tasks.choice], vocabulary: corpus.lexemes };
  const snapshot = taskSnapshot(session, 'choice');
  snapshot.task.choices[0].text = 'Changed';
  assert.equal(session.tasks[0].choices[0].text, 'Bonjour');
  assert.throws(() => taskSnapshot(session, 'missing'));
});
