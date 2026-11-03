import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { normalizeAnswer, checkAnswer, initialPresentation, evaluateEvent, calculateSessionResult, meetsCheckpointThreshold, reviewSnapshot } from '../../resources/js/App/Learning/answerChecker.js';

export const corpus = JSON.parse(readFileSync(new URL('../Fixtures/Learning/answer-checking-v1.json', import.meta.url), 'utf8'));

export const corpusSnapshot = (vector) => {
  const task = structuredClone(corpus.tasks[vector.task]);
  if (vector.review_lexeme_key) {
    delete task.objective_ids;
    delete task.skill;
    delete task.assessed_vocabulary_ids;
    return reviewSnapshot(task, vector.review_lexeme_key);
  }
  return { task, lexemes: corpus.lexemes.map((word) => ({ ...word, lexeme_key: vector.lexeme_overrides?.[word.id] ?? word.lexeme_key })), review_lexeme_key: null };
};

export const sequenceStates = (vector) => {
  const snapshot = corpusSnapshot(vector);
  let state = initialPresentation('p1');
  return vector.events.map((event) => {
    state = evaluateEvent(snapshot, state, { presentation_id: 'p1', ...event }, corpus.version);
    return structuredClone(state);
  });
};

export const summaryStates = (vector) => Array.from({ length: vector.processed }, (_, index) => {
  const id = 'summary-' + index;
  return { ...initialPresentation(id), status: 'answered', first_result: {
    checking_policy_version: 1, presentation_id: id, is_first_answer: true, score: index < vector.earned ? 1 : 0,
    technical_skip: index >= vector.processed - vector.skips,
  } };
});

const captured = (fn) => {
  try { return fn(); } catch (error) { return { error: error.code ?? 'invalid_state' }; }
};

export const runCorpus = () => ({
  normalization: corpus.normalization.map(({ input }) => normalizeAnswer(input)),
  checks: corpus.checks.map((vector) => captured(() => checkAnswer(corpus.tasks[vector.task], vector.answer, corpus.version))),
  sequences: corpus.sequences.map(sequenceStates),
  invalid_events: corpus.invalid_events.map((vector) => captured(() => evaluateEvent(corpusSnapshot(vector), initialPresentation('p1'), { presentation_id: 'p1', ...vector.event }, 1))),
  summaries: corpus.summaries.map((vector) => {
    const result = calculateSessionResult(summaryStates(vector), vector.total, corpus.version);
    return { result, eligible: meetsCheckpointThreshold(result, vector.threshold) };
  }),
});

if (process.argv[1] === fileURLToPath(import.meta.url)) process.stdout.write(JSON.stringify(runCorpus()));
