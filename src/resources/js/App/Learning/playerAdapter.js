import { checkingPolicy } from './types/index.js';
import { initialPresentation, evaluateEvent, calculateSessionResult, taskSnapshot } from './answerChecker.js';

const methods = ['start', 'answer', 'help', 'finish', 'load'];
export const assertPlayerAdapter = (adapter) => {
  if (!adapter || methods.some((name) => typeof adapter[name] !== 'function')) throw new TypeError('Player adapter requires start/answer/help/finish/load.');
  return adapter;
};

export const createTransportAdapter = ({ mode, actions, saveDraft }) => {
  if (!['account', 'guest'].includes(mode)) throw new TypeError('Invalid player mode.');
  assertPlayerAdapter(actions);
  const adapter = { mode };
  for (const name of methods) adapter[name] = async (...args) => actions[name](...args);
  if (typeof saveDraft === 'function') adapter.saveDraft = saveDraft;
  return Object.freeze(adapter);
};

export const createLocalPlayerAdapter = ({ mode, loadSession, saved, persist, policyVersion = 1, idFactory = () => crypto.randomUUID() }) => {
  checkingPolicy(policyVersion);
  let session;
  let states = [];
  let index = saved?.index ?? 0;
  let started = saved?.started ?? false;
  let finished = false;
  const introductionsSeen = new Set();
  const ledger = new Map();
  const ensure = async () => {
    if (!session) {
      const loaded = await loadSession();
      if (!loaded || !Array.isArray(loaded.tasks) || !Array.isArray(loaded.vocabulary)) throw new Error('Не удалось загрузить занятие.');
      session = structuredClone(loaded);
      states = saved?.states ?? session.tasks.map(() => initialPresentation(idFactory()));
    }
  };
  const view = () => {
    const task = session.tasks[index] ?? null;
    const presentation = states[index] ?? null;
    return structuredClone({
      mode, started, finished, session: { id: session.id, title: session.title, description: session.description, level: session.level,
        duration_minutes: session.duration_minutes, pronunciation_notes: session.pronunciation_notes ?? [] },
      task, presentation, position: index + 1, total: session.tasks.length,
      introductions: (session.introductions ?? []).filter((intro) => intro.before_task_id === task?.id),
      introduction_seen: introductionsSeen.has(presentation?.presentation_id),
      vocabulary: session.vocabulary, summary: calculateSessionResult(states, session.tasks.length, policyVersion),
    });
  };
  const submit = async (input, kind) => {
    await ensure();
    const { client_event_id, ...event } = input;
    if (!client_event_id) throw new Error('Отсутствует ID события.');
    const fingerprint = JSON.stringify(event);
    if (ledger.has(client_event_id)) {
      const previous = ledger.get(client_event_id);
      if (previous.fingerprint !== fingerprint) throw new Error('ID события уже использован с другим содержимым.');
      return structuredClone(previous.view);
    }
    if (!started || finished || !states[index]) throw new Error('Занятие не ожидает ответа.');
    if (input.presentation_id !== states[index].presentation_id) throw new Error('Обновите текущее задание.');
    if ((kind === 'answer') !== (event.event_type === 'answer')) throw new Error('Некорректный метод события.');
    const nextStates = states.slice();
    nextStates[index] = evaluateEvent(taskSnapshot(session, session.tasks[index].id), states[index], event, policyVersion);
    if (kind === 'answer') nextStates[index].answer = structuredClone(event.answer);
    persist?.({states: nextStates, index, started, finished});
    states = nextStates;
    const result = view();
    ledger.set(client_event_id, { fingerprint, view: structuredClone(result) });
    return result;
  };
  return Object.freeze({
    mode,
    async load({ advanceFrom, introductionSeen } = {}) {
      await ensure();
      if (advanceFrom && states[index]?.presentation_id === advanceFrom) {
        if (!states[index].first_result) throw new Error('Сначала обработайте текущий шаг.');
        const nextIndex = Math.min(index + 1, states.length - 1);
        persist?.({states, index: nextIndex, started, finished});
        index = nextIndex;
      }
      if (introductionSeen && states[index]?.presentation_id === introductionSeen) introductionsSeen.add(introductionSeen);
      return view();
    },
    async start() {
      await ensure();
      persist?.({states, index, started: true, finished});
      started = true;
      return view();
    },
    async answer(input) { return submit({ ...input, event_type: 'answer' }, 'answer'); },
    async help(input) { return submit(input, 'help'); },
    async finish() {
      await ensure();
      if (!calculateSessionResult(states, states.length, policyVersion).all_steps_processed) throw new Error('Обработайте все шаги перед завершением.');
      persist?.({states, index, started, finished: true});
      finished = true;
      return view();
    },
  });
};

export const createPreviewAdapter = options => createLocalPlayerAdapter({ ...options, mode: 'preview' });
