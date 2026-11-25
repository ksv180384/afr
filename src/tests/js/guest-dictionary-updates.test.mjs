import test from 'node:test';
import assert from 'node:assert/strict';
import { refreshGuestDictionary } from '../../resources/js/App/Learning/guestDictionaryUpdates.js';

test('removes the last studied source and closes only reviews with outstanding unavailable tasks', async () => {
  const task = {id:'question', type:'short_answer', accepted_answers:['salut']};
  const source = (id, session) => ({session_version_id:id, session_id:session, snapshot:{review_items:[task]}});
  const words = {
    shared:{lexeme_key:'shared', streak:3, sources:[source(1,'A'), source(2,'B')]},
    removed:{lexeme_key:'removed', sources:[source(1,'A')]},
  };
  const run = (key, answered = false) => ({kind:'review', status:'paused', revision:2,
    snapshot:{review_items:[{review_lexeme_key:key, task}]}, presentations:[{first_result:answered ? {correct:true} : null}]});
  const state = {vocabulary:words, runs:{keep:run('shared'), cancel:run('removed'), answered:run('removed', true)}};
  const repository = {mutate:async apply => apply(state)};
  const changed = {lexeme_key:'shared', example:{fr:'Salut !'}, review_items:[{accepted_answers:['salut'], type:'short_answer', id:'question'}]};
  const result = await refreshGuestDictionary(repository, {dictionaryUpdates:async () =>
    ({removed:['A'], updated:[{session:'B', checksum:'new', vocabulary:[changed]}]})}, state);
  assert.equal(result.vocabulary.removed, undefined);
  assert.equal(result.vocabulary.shared.streak, 3);
  assert.deepEqual(result.vocabulary.shared.sources.map(row => row.session_id), ['B']);
  assert.equal(result.vocabulary.shared.snapshot.example.fr, 'Salut !');
  assert.equal(result.runs.keep.status, 'paused');
  assert.equal(result.runs.answered.status, 'paused');
  assert.equal(result.runs.cancel.status, 'cancelled');
  const before = structuredClone(result);
  await refreshGuestDictionary(repository, {dictionaryUpdates:async () => {throw Error('offline');}}, state);
  assert.deepEqual(state, before);
});
