import { checkingPolicy } from './types/index.js';

export class AnswerCheckingError extends Error {
  constructor(code, message) {
    super(message);
    this.code = code;
  }
}

const invalid = (message = 'Ответ неполный или содержит недопустимые поля.') => {
  throw new AnswerCheckingError('validation_failed', message);
};

const policy = (version) => {
  try {
    return checkingPolicy(version);
  } catch {
    throw new AnswerCheckingError('unsupported_checking_policy', 'Версия правил проверки недоступна.');
  }
};

const keys = (value, expected) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)
    || JSON.stringify(Object.keys(value).sort()) !== JSON.stringify([...expected].sort())) {
    invalid();
  }
};

export const normalizeSurface = (value) => {
  if (typeof value !== 'string' || /[\uD800-\uDFFF]/u.test(value)) invalid('Требуется корректный текст UTF-8.');
  return value.replaceAll('\u2019', "'").normalize('NFC')
    .replace(/[\u0009-\u000D\u0020\u0085\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000\uFEFF]+/gu, ' ')
    .replace(/^ +| +$/g, '');
};

export const normalizeAnswer = (value) => normalizeSurface(value).toLowerCase().replace(/^[\p{P} ]+|[\p{P} ]+$/gu, '');

const permutation = (actual, expected) => {
  if (!Array.isArray(actual) || actual.length !== expected.length || actual.some((id) => typeof id !== 'string')
    || JSON.stringify([...actual].sort()) !== JSON.stringify([...expected].sort())) {
    invalid('Карточки или пары неполные, повторены либо относятся к другому заданию.');
  }
};

export const solutionFor = (task) => {
  let answer;
  if (task.type === 'single_choice' || (task.type === 'fill_blank' && task.input_mode === 'choice')) {
    answer = { choice_id: task.correct_choice_id };
  } else if (task.type === 'short_answer' || (task.type === 'fill_blank' && task.input_mode === 'text')) {
    answer = { accepted_answers: task.accepted_answers };
  } else if (task.type === 'word_order') {
    answer = { accepted_orders: task.accepted_orders };
  } else if (task.type === 'matching_pairs') {
    answer = { pairs: task.correct_pairs.map(({ left_id, right_id }) => ({ left_id, right_id })) };
  } else {
    throw new AnswerCheckingError('unavailable', 'Неподдерживаемый тип задания.');
  }
  return structuredClone({ ...answer, completed_phrases: task.completed_phrases ?? [], pronunciation_notes: task.pronunciation_notes ?? [] });
};

export const checkAnswer = (task, answer, version) => {
  policy(version);
  const solution = solutionFor(task);
  if (task.type === 'single_choice' || (task.type === 'fill_blank' && task.input_mode === 'choice')) {
    keys(answer, ['choice_id']);
    if (typeof answer.choice_id !== 'string' || !task.choices.some(({ id }) => id === answer.choice_id)) invalid();
    return { correct: answer.choice_id === task.correct_choice_id, pairs: [], solution };
  }
  if (task.type === 'short_answer' || (task.type === 'fill_blank' && task.input_mode === 'text')) {
    keys(answer, ['value']);
    if (typeof answer.value !== 'string' || [...answer.value].length > 20000 || normalizeAnswer(answer.value) === '') invalid();
    return { correct: task.accepted_answers.some((text) => normalizeAnswer(text) === normalizeAnswer(answer.value)), pairs: [], solution };
  }
  if (task.type === 'word_order') {
    keys(answer, ['token_ids']);
    permutation(answer.token_ids, task.tokens.map(({ id }) => id));
    const text = new Map(task.tokens.map(({ id, text }) => [id, normalizeSurface(text)]));
    const actual = answer.token_ids.map((id) => text.get(id));
    const correct = task.accepted_orders.some((order) => JSON.stringify(order.map((id) => text.get(id))) === JSON.stringify(actual));
    return { correct, pairs: [], solution };
  }
  keys(answer, ['pairs']);
  if (!Array.isArray(answer.pairs)) invalid();
  for (const pair of answer.pairs) {
    keys(pair, ['left_id', 'right_id']);
    if (typeof pair.left_id !== 'string' || typeof pair.right_id !== 'string') invalid();
  }
  permutation(answer.pairs.map(({ left_id }) => left_id), task.left_items.map(({ id }) => id));
  permutation(answer.pairs.map(({ right_id }) => right_id), task.right_items.map(({ id }) => id));
  const submitted = new Map(answer.pairs.map(({ left_id, right_id }) => [left_id, right_id]));
  const pairs = task.correct_pairs.map((pair) => ({
    left_id: pair.left_id, right_id: submitted.get(pair.left_id), correct: submitted.get(pair.left_id) === pair.right_id,
    assessed_vocabulary_ids: [...pair.assessed_vocabulary_ids],
  }));
  return { correct: pairs.every(({ correct }) => correct), pairs, solution };
};

export const initialPresentation = (presentationId) => ({
  presentation_id: presentationId, status: 'pending', help_used: false, first_result: null, last_result: null,
});

export const evaluateEvent = (snapshot, previousState, event, version) => {
  policy(version);
  const state = structuredClone(previousState);
  const type = event.event_type;
  const expectedKeys = {
    answer: ['event_type', 'presentation_id', 'answer'],
    help: ['event_type', 'presentation_id', 'help_kind'],
    unknown: ['event_type', 'presentation_id'],
    technical_skip: ['event_type', 'presentation_id', 'reason'],
  };
  if (!Object.hasOwn(expectedKeys, type)) invalid('Неизвестное учебное событие.');
  keys(event, expectedKeys[type]);
  if (event.presentation_id !== state.presentation_id) invalid();
  const task = snapshot.task;
  const first = state.first_result === null;
  if (type === 'help' && (!['hint', 'audio_transcript', 'reference'].includes(event.help_kind)
    || (event.help_kind === 'audio_transcript' && !task.prompt?.audio))) invalid();
  if (type === 'technical_skip' && (event.reason !== 'audio_unavailable' || (!task.prompt?.audio && task.skill !== 'listening'))) invalid();
  const check = type === 'answer' ? checkAnswer(task, event.answer, version) : null;
  const independent = first && !state.help_used && check?.correct === true;
  const grading = type !== 'help';
  const pairs = check?.pairs ?? [];
  const assessed = new Map();
  if (grading) {
    const map = new Map(snapshot.lexemes.map(({ id, lexeme_key }) => [id, lexeme_key]));
    const items = task.type === 'matching_pairs' && check !== null ? pairs
      : [{ assessed_vocabulary_ids: task.assessed_vocabulary_ids ?? [], correct: check?.correct ?? false }];
    for (const item of items) {
      const lexemeKeys = snapshot.review_lexeme_key === null
        ? item.assessed_vocabulary_ids.map((id) => {
          if (!map.has(id)) throw new Error('Unknown assessed vocabulary snapshot.');
          return map.get(id);
        }) : [snapshot.review_lexeme_key];
      for (const key of lexemeKeys) {
        const effect = type === 'technical_skip' ? 'none'
          : (!first ? (type === 'answer' && !item.correct ? 'error' : 'none')
            : ((type === 'unknown' || !item.correct) ? 'error' : (!state.help_used ? 'success' : 'none')));
        const entry = { lexeme_key: key, correct: type === 'technical_skip' ? null : Boolean(item.correct), effect };
        if (!assessed.has(key) || (assessed.get(key).correct !== false && entry.correct === false)) assessed.set(key, entry);
      }
    }
  }
  const result = {
    checking_policy_version: version, presentation_id: state.presentation_id, task_id: task.id,
    event_type: type, is_first_answer: first && grading,
    correct: type === 'answer' ? check.correct : (type === 'unknown' ? false : null),
    first_independent_success: independent, help_used: state.help_used || (first && type === 'help'), score: state.first_result?.score ?? (independent ? 1 : 0),
    technical_skip: type === 'technical_skip' && first,
    feedback: type === 'technical_skip' ? { message: 'Звук недоступен. Шаг пропущен без языковой ошибки.', pronunciation_notes: [] }
      : (grading ? { message: task.feedback[check?.correct ? 'correct' : 'incorrect'], pronunciation_notes: structuredClone(task.feedback.pronunciation_notes ?? []) } : null),
    solution: grading ? (check?.solution ?? solutionFor(task)) : null,
    pairs, assessed_lexemes: [...assessed.values()],
    objective_evidence_eligible: independent && (task.objective_ids?.length ?? 0) > 0 && task.skill !== undefined,
    objective_ids: structuredClone(task.objective_ids ?? []), skill: task.skill ?? null,
  };
  if (first && type === 'help') state.help_used = true;
  if (first && grading) {
    state.first_result = structuredClone(result);
    state.status = type === 'technical_skip' ? 'skipped' : 'answered';
  }
  state.last_result = result;
  return state;
};

export const calculateSessionResult = (states, totalSteps, version) => {
  policy(version);
  if (!Number.isInteger(totalSteps) || totalSteps < 0 || totalSteps > 1000 || states.length > totalSteps) throw new RangeError('Invalid number of assessed steps.');
  const seen = new Set();
  let processed = 0;
  let earned = 0;
  let skips = 0;
  for (const state of states) {
    if (seen.has(state.presentation_id)) throw new RangeError('Duplicate presentation.');
    seen.add(state.presentation_id);
    const first = state.first_result;
    if (first !== null) {
      if (first.presentation_id !== state.presentation_id || first.checking_policy_version !== version || first.is_first_answer !== true || ![0, 1].includes(first.score)) throw new RangeError('Invalid first answer result.');
      processed++;
      earned += first.score;
      skips += first.technical_skip ? 1 : 0;
    }
  }
  return { checking_policy_version: version, total_steps: totalSteps, processed_steps: processed,
    independent_correct_steps: earned, score_numerator: earned, score_denominator: totalSteps,
    score_percent: totalSteps === 0 ? null : earned * 100 / totalSteps,
    technical_skips: skips, all_steps_processed: totalSteps > 0 && processed === totalSteps };
};

export const meetsCheckpointThreshold = (result, threshold) => {
  if (!Number.isInteger(threshold) || threshold < 0 || threshold > 100) throw new RangeError('Invalid score threshold.');
  return result.all_steps_processed && result.technical_skips === 0
    && result.score_numerator * 100 >= threshold * result.score_denominator;
};

export const taskSnapshot = (session, taskId) => {
  const task = session.tasks.find(({ id }) => id === taskId);
  if (!task) throw new AnswerCheckingError('unavailable', 'Задание отсутствует в снимке сессии.');
  return structuredClone({ task, lexemes: session.vocabulary.map(({ id, lexeme_key }) => ({ id, lexeme_key })), review_lexeme_key: null });
};

export const reviewSnapshot = (reviewItem, lexemeKey) => {
  if (typeof lexemeKey !== 'string' || !lexemeKey || !['single_choice', 'short_answer'].includes(reviewItem.type)) throw new AnswerCheckingError('unavailable', 'Недоступен проверяемый шаблон повторения.');
  return structuredClone({ task: reviewItem, lexemes: [], review_lexeme_key: lexemeKey });
};
