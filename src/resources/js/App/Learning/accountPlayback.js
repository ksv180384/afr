import { evaluateEvent, initialPresentation } from './answerChecker.js';
import { checkingPolicy } from './types/index.js';

export const accountPlayback = (view) => {
  const pack = view.playback;
  if (!Array.isArray(pack?.steps) || pack.steps.length !== view.total) return null;
  try { checkingPolicy(view.checking_policy_version); }
  catch (error) { if (error instanceof RangeError) return null; throw error; }
  return pack;
};

const displayTask = (step, view) => {
  const { correct_choice_id, correct_pairs, accepted_answers, accepted_orders, completed_phrases, hint, ...task } = step.task;
  task.has_hint = Boolean(hint);
  if (hint && (view.assistance.includes('hint') || view.presentation.first_result)) task.hint = hint;
  if (step.review_lexeme_key) {
    task.vocabulary_ids = view.vocabulary.filter(word => word.lexeme_key === step.review_lexeme_key).map(word => word.id);
  }
  return task;
};

const goTo = (view, pack, position, corrections) => {
  const step = pack.steps[position - 1];
  const presentation = initialPresentation(step.presentation_id);
  const next = {...view, position, corrections, presentation, assistance:[], introduction_seen:false, local_draft:null,
    introductions:pack.introductions.filter(intro => intro.before_task_id === step.task.id)};
  return {...next, task:displayTask(step, next)};
};

const startRound = (view, pack, positions, round, fixed) => goTo(view, pack, positions[0], {
  positions, remaining_positions:[], round, fixed_last_round:fixed, started:false, position:1, total:positions.length, can_finish:false,
});

const advance = (view, pack) => {
  const corrections = view.corrections;
  if (!corrections) return view.presentation.first_result && view.position < view.total
    ? goTo(view, pack, view.position + 1, null) : null;
  if (!Array.isArray(corrections.positions)) return null;
  if (!corrections.started) return {...view, corrections:{...corrections, started:true}};
  if (!view.presentation.first_result || corrections.can_finish) return null;
  const remaining = view.presentation.first_result.correct
    ? corrections.remaining_positions : [...corrections.remaining_positions, view.position];
  if (corrections.position === corrections.total) {
    return startRound(view, pack, remaining, corrections.round + 1, corrections.total - remaining.length);
  }
  return goTo(view, pack, corrections.positions[corrections.position], {
    ...corrections, position:corrections.position + 1, remaining_positions:remaining, can_finish:false,
  });
};

const grade = (view, step, event) => {
  const correcting = Boolean(view.corrections);
  if (correcting && (!view.corrections.started || event.event_type === 'technical_skip')) return null;
  const previous = view.presentation;
  // Every step in corrections already has an incorrect first result with a score of zero.
  const original = correcting ? {...previous, first_result:{score:0}} : previous;
  const state = evaluateEvent({...step, lexemes:view.vocabulary}, original, event, view.checking_policy_version);
  const helped = event.event_type === 'help';
  const presentation = correcting
    ? {...previous, ...(!helped && {first_result:state.last_result, last_result:state.last_result, answer:event.answer ?? null})}
    : {...state, answer:event.answer ?? previous.answer ?? null};
  const assistance = helped ? [...new Set([...view.assistance, event.help_kind])] : view.assistance;
  let next = {...view, presentation, assistance, restore_assistance:false};
  if (correcting && !helped) {
    const corrections = view.corrections;
    next.corrections = {...corrections,
      can_finish: corrections.position === corrections.total && !corrections.remaining_positions.length && state.last_result.correct === true};
  }
  if (!correcting && state.last_result.is_first_answer) {
    const result = state.first_result;
    const processed = view.summary.processed_steps + 1;
    const earned = view.summary.score_numerator + result.score;
    next.summary = {...view.summary, processed_steps:processed, independent_correct_steps:earned, score_numerator:earned,
      score_percent:earned * 100 / view.total, technical_skips:view.summary.technical_skips + Number(result.technical_skip),
      all_steps_processed:processed === view.total};
    if (['daily','extra'].includes(view.kind) && result.correct === false) {
      next.mistake_positions = [...view.mistake_positions, view.position];
      next.mistakes_count = next.mistake_positions.length;
    }
  }
  return {...next, task:displayTask(step, next)};
};

export const projectAccountAction = (view, pack, action, logical) => {
  if (!view || !pack || !view.started || view.finished) return null;
  const step = pack.steps[view.position - 1];
  if (!step || step.presentation_id !== view.presentation?.presentation_id) return null;
  const { client_event_id, client_occurred_at, presentation_id, ...fields } = logical;
  if (presentation_id && presentation_id !== step.presentation_id) return null;
  let next;
  if (action === 'advance') next = advance(view, pack);
  if (action === 'introduction') next = {...view, introduction_seen:true};
  if (action === 'pause') next = {...view, status:'paused', started:false, resume:true};
  if (['answer','help'].includes(action)) next = grade(view, step, {presentation_id, ...fields});
  if (action === 'reviewMistakes' && !view.corrections && view.summary.all_steps_processed && view.mistake_positions.length) {
    next = startRound({...view,mistake_positions:[]}, pack, view.mistake_positions, 1, 0);
  }
  return next ? {...next, revision:view.revision + 1} : null;
};

// Persist only the fields needed to detect a divergent response, never the solution.
export const playbackPrediction = (view) => {
  const { first_result:first, last_result:last } = view.presentation ?? {};
  const corrections = view.corrections;
  return [view.revision, view.status, view.presentation?.presentation_id ?? null, view.mistakes_count,
    view.summary?.processed_steps, view.summary?.score_numerator, view.summary?.technical_skips,
    first?.correct ?? null, last?.correct ?? null, last?.event_type ?? null,
    corrections ? [corrections.started, corrections.round, corrections.position, corrections.can_finish, corrections.positions, corrections.remaining_positions] : null];
};
