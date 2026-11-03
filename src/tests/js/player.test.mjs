import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createPreviewAdapter, createTransportAdapter } from '../../resources/js/App/Learning/playerAdapter.js';
import { createAudioPlayer } from '../../resources/js/App/Learning/audioPlayer.js';
import { emptyAnswer, answerComplete, introductionPages, insertedValue } from '../../resources/js/App/Learning/playerUi.js';

const packages = ['pre-a1-u01-examples', 'task-types'].map((name) => JSON.parse(readFileSync(new URL('../../resources/learning/templates/' + name + '.json', import.meta.url))));
let serial = 0;
const adapterFor = (session) => createPreviewAdapter({ loadSession: async () => session, idFactory: () => 'presentation-' + ++serial });
const input = (view, extra = {}) => ({ client_event_id: 'event-' + ++serial, presentation_id: view.presentation.presentation_id, ...extra });
const correct = (task) => task.type === 'matching_pairs' ? { pairs: task.correct_pairs.map(({ left_id, right_id }) => ({ left_id, right_id })) }
  : task.type === 'word_order' ? { token_ids: task.accepted_orders[0] }
    : task.correct_choice_id ? { choice_id: task.correct_choice_id } : { value: task.accepted_answers[0] };

for (const session of packages.flatMap(({ sessions }) => sessions)) {
  test('complete arbitrary preview without global storage or transport: ' + session.id, async () => {
    const original = structuredClone(session);
    const adapter = adapterFor(session);
    let view = await adapter.load();
    assert.equal(view.started, false);
    view = await adapter.start();
    for (const task of session.tasks) {
      assert.equal(view.task.id, task.id);
      if (view.introductions.length) {
        view = await adapter.load({ introductionSeen: view.presentation.presentation_id });
        assert.equal(view.presentation.help_used, false);
      }
      const answer = correct(task);
      assert.equal(answerComplete(task, emptyAnswer(task)), false);
      assert.equal(answerComplete(task, answer), true);
      const request = input(view, { answer });
      view = await adapter.answer(request);
      assert.equal(view.presentation.first_result.correct, true);
      assert.equal(view.presentation.first_result.score, 1);
      assert.deepEqual(await adapter.answer(request), view);
      const oldId = view.presentation.presentation_id;
      if (view.position < view.total) {
        view = await adapter.load({ advanceFrom: oldId });
        const position = view.position;
        assert.equal((await adapter.load({ advanceFrom: oldId })).position, position);
      }
    }
    assert.equal(view.finished, false);
    view = await adapter.finish();
    assert.equal(view.finished, true);
    assert.equal(view.summary.independent_correct_steps, session.tasks.length);
    assert.deepEqual(await adapter.finish(), view);
    assert.deepEqual(session, original);
  });
}

test('transcript and reference are assistance; introductions are free; help has no solution', async () => {
  const adapter = adapterFor(packages[0].sessions[0]);
  let view = await adapter.start();
  view = await adapter.load({ introductionSeen: view.presentation.presentation_id });
  assert.equal(view.presentation.help_used, false);
  view = await adapter.help(input(view, { event_type: 'help', help_kind: 'audio_transcript' }));
  assert.equal(view.presentation.help_used, true);
  assert.equal(view.presentation.last_result.solution, null);
  assert.equal(view.presentation.first_result, null);
  view = await adapter.answer(input(view, { answer: correct(view.task) }));
  assert.equal(view.presentation.first_result.correct, true);
  assert.equal(view.presentation.first_result.score, 0);
  assert.equal(view.presentation.first_result.objective_evidence_eligible, false);
});

test('unknown and technical skip are distinct; stale requests and conflicting IDs reject', async () => {
  const adapter = adapterFor(packages[0].sessions[0]);
  let view = await adapter.start();
  const request = input(view, { event_type: 'technical_skip', reason: 'audio_unavailable' });
  view = await adapter.help(request);
  assert.equal(view.presentation.first_result.technical_skip, true);
  assert.ok(view.presentation.first_result.assessed_lexemes.every(({ effect }) => effect !== 'error'));
  await assert.rejects(adapter.help({ ...request, event_type: 'unknown' }));
  view = await adapter.load({ advanceFrom: view.presentation.presentation_id });
  await assert.rejects(adapter.answer(input({ presentation: { presentation_id: 'stale' } }, { answer: correct(view.task) })));
  view = await adapter.help(input(view, { event_type: 'unknown' }));
  assert.equal(view.presentation.first_result.technical_skip, false);
  assert.ok(view.presentation.first_result.solution);
  await assert.rejects(adapter.finish());
});

test('retry load, detached snapshots, empty state and no browser globals', async () => {
  let calls = 0;
  const adapter = createPreviewAdapter({ idFactory: () => 'test', loadSession: async () => {
    if (++calls === 1) throw new Error('offline');
    return { tasks: [], vocabulary: [], title: 'Empty' };
  } });
  await assert.rejects(adapter.load(), /offline/);
  const view = await adapter.load();
  assert.equal(view.total, 0);
  assert.equal(view.summary.all_steps_processed, false);
  view.session.title = 'changed';
  assert.equal((await adapter.load()).session.title, 'Empty');
  await assert.rejects(adapter.finish());
});

test('account and guest delegate without client grading or implicit storage', async () => {
  for (const mode of ['account', 'guest']) {
    const calls = [];
    const actions = Object.fromEntries(['load', 'start', 'answer', 'help', 'finish'].map((name) => [name, async (...args) => {
      calls.push([name, args]); if (name === 'answer') throw new Error('network'); return { mode };
    }]));
    const adapter = createTransportAdapter({ mode, actions });
    assert.deepEqual(await adapter.load(), { mode });
    await assert.rejects(adapter.answer({ answer: 'draft' }), /network/);
    assert.deepEqual(calls.map(([name]) => name), ['load', 'answer']);
    assert.equal(adapter.saveDraft, undefined);
  }
});

test('introduction paging and French symbol replacement', () => {
  const words = Array.from({ length: 9 }, (_, id) => ({ id }));
  const pages = introductionPages([{ vocabulary_ids: words.map(({ id }) => id) }], words);
  assert.deepEqual(pages.map(({ words }) => words.length), [4, 4, 1]);
  assert.deepEqual(pages.map(({ showText }) => showText), [true, false, false]);
  assert.deepEqual(insertedValue('cafe', 'é', 3, 4), { value: 'café', caret: 4 });
});

const speechEnv = (voices = []) => {
  let timeout;
  let listener;
  const utterances = [];
  let cancellations = 0;
  const env = {
    setTimeout: (fn) => { timeout = fn; return 1; }, clearTimeout: () => {},
    SpeechSynthesisUtterance: function (text) { this.text = text; },
    speechSynthesis: {
      getVoices: () => voices,
      addEventListener: (name, fn) => { listener = fn; },
      removeEventListener: () => { listener = null; },
      speak: (utterance) => { utterances.push(utterance); },
      cancel: () => { cancellations++; utterances.at(-1)?.onerror(); },
    },
  };
  return { env, utterances, timeout: () => timeout(), voicesChanged: () => listener?.(), cancellations: () => cancellations };
};

test('French voice only; async voices; slow/replay/stop and late events', () => {
  const voices = [{ lang: 'en-US' }];
  const fake = speechEnv(voices);
  const states = [];
  const player = createAudioPlayer({ environment: () => fake.env });
  player.play({ text: 'Bonjour' }, { slow: true, onState: ({ state }) => states.push(state) });
  assert.equal(fake.utterances.length, 0);
  voices.push({ lang: 'fr-FR' });
  fake.voicesChanged();
  const utterance = fake.utterances[0];
  assert.equal(utterance.lang, 'fr-FR');
  assert.equal(utterance.rate, 0.65);
  utterance.onstart();
  player.stop();
  utterance.onend();
  assert.deepEqual(states, ['loading', 'playing', 'idle']);
  assert.equal(fake.cancellations(), 1);
  player.play({ text: 'Salut' });
  assert.equal(fake.utterances.length, 2);
});

test('missing French voice and timeout cancel pending speech; no fallback to English', () => {
  for (const voices of [[{ lang: 'en-US' }], [{ lang: 'fr-FR' }]]) {
    const fake = speechEnv(voices);
    const states = [];
    createAudioPlayer({ environment: () => fake.env }).play({ text: 'Bonjour' }, { onState: ({ state }) => states.push(state) });
    fake.timeout();
    fake.voicesChanged();
    assert.deepEqual(states, ['loading', 'error']);
    assert.equal(fake.utterances.length, voices[0].lang === 'fr-FR' ? 1 : 0);
    assert.equal(fake.cancellations(), voices[0].lang === 'fr-FR' ? 1 : 0);
  }
});

test('asset failures and stalled playback surface an error', async () => {
  let timeout;
  let media;
  class Audio {
    constructor(url) { this.url = url; media = this; }
    play() { return Promise.resolve(); }
    pause() { this.paused = true; }
    load() {}
    removeAttribute() {}
  }
  const states = [];
  const player = createAudioPlayer({ resolveAsset: () => '/private/audio', environment: () => ({
    Audio, setTimeout: (fn) => { timeout = fn; return 1; }, clearTimeout: () => {},
  }) });
  player.play({ asset_id: 'id' }, { slow: true, onState: ({ state }) => states.push(state) });
  assert.equal(media.playbackRate, 0.7);
  media.onplaying();
  media.onstalled();
  timeout();
  assert.deepEqual(states, ['loading', 'playing', 'error']);
  assert.equal(media.paused, true);
});
