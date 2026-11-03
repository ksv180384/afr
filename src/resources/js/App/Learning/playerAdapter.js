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

export const createPreviewAdapter = ({ loadSession, policyVersion = 1, idFactory = () => crypto.randomUUID() }) => {
  checkingPolicy(policyVersion);
  let session;
  let states = [];
  let index = 0;
  let started = false;
  let finished = false;
  const introductionsSeen = new Set();
  const ledger = new Map();
  const ensure = async () => {
    if (!session) {
      const loaded = await loadSession();
      if (!loaded || !Array.isArray(loaded.tasks) || !Array.isArray(loaded.vocabulary)) throw new Error('Не удалось загрузить занятие.');
      session = structuredClone(loaded);
      states = session.tasks.map(() => initialPresentation(idFactory()));
    }
  };
  const view = () => {
    const task = session.tasks[index] ?? null;
    const presentation = states[index] ?? null;
    return structuredClone({
      mode: 'preview', started, finished, session: { id: session.id, title: session.title, description: session.description, level: session.level,
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
    states[index] = evaluateEvent(taskSnapshot(session, session.tasks[index].id), states[index], event, policyVersion);
    const result = view();
    ledger.set(client_event_id, { fingerprint, view: structuredClone(result) });
    return result;
  };
  return Object.freeze({
    mode: 'preview',
    async load({ advanceFrom, introductionSeen } = {}) {
      await ensure();
      if (advanceFrom && states[index]?.presentation_id === advanceFrom) {
        if (!states[index].first_result) throw new Error('Сначала обработайте текущий шаг.');
        if (index < states.length - 1) index++;
      }
      if (introductionSeen && states[index]?.presentation_id === introductionSeen) introductionsSeen.add(introductionSeen);
      return view();
    },
    async start() { await ensure(); started = true; return view(); },
    async answer(input) { return submit({ ...input, event_type: 'answer' }, 'answer'); },
    async help(input) { return submit(input, 'help'); },
    async finish() {
      await ensure();
      if (!calculateSessionResult(states, states.length, policyVersion).all_steps_processed) throw new Error('Обработайте все шаги перед завершением.');
      finished = true;
      return view();
    },
  });
};
