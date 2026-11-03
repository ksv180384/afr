import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { learningAccess } from '../../resources/js/App/Learning/learningAccess.js';
import { addGuestRun, newGuestState, applyGuestOperation } from '../../resources/js/App/Learning/guestProgress.js';
const fixture = JSON.parse(readFileSync(new URL('../Fixtures/learning-access.json', import.meta.url)));
test('guest access opens lessons topics and levels sequentially while keeping completed lessons open', () => {
  for (const sample of fixture.cases) {
    const states = learningAccess(fixture.outline, sample.completed);
    for (const kind of ['levels','units','sessions']) {
      assert.deepEqual(Object.keys(states[kind]).filter(id=>!states[kind][id].locked),sample[kind]);
    }
  }
});
test('a missing previous level never unlocks the next one', () => {
  const outline = structuredClone(fixture.outline);
  outline[0].units = [];
  assert.equal(learningAccess(outline,[]).levels.A1.locked,true);
});
test('an extra guest run cannot skip topics or levels when the selected profile has not reached that level', () => {
  const state = newGuestState({idFactory:randomUUID,now:'2026-10-31T09:00:00Z',timezone:'UTC'});
  const original = JSON.parse(readFileSync(new URL('../../resources/learning/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
  const source = {snapshot:{...original,id:'S4',level:'A1',prerequisite_ids:[]},checking_policy_version:1,session_version_id:1,access_outline:fixture.outline};
  state.current_level = 'PRE_A1';
  const input = {source,runId:randomUUID(),presentationIds:original.tasks.map(randomUUID),eventId:randomUUID(),tabId:randomUUID(),now:'2026-10-31T09:00:00Z',kind:'extra'};
  assert.throws(()=>addGuestRun(state,input),/Сначала завершите/);
  assert.deepEqual(state.runs,{});
  state.runs.old = {status:'completed',kind:'extra',snapshot:{id:'S4'}};
  assert.doesNotThrow(()=>addGuestRun(state,input));
});

test('the profile opens lower levels for review without recording false completion', () => {
  const states = learningAccess(fixture.outline,[],'A1');
  for(const id of ['S1','S2','S3']) {
    assert.equal(states.sessions[id].locked,false);
    assert.equal(states.sessions[id].completed,false);
    assert.equal(states.sessions[id].review_only,true);
  }
  assert.equal(states.sessions.S4.locked,false);
  assert.equal(states.sessions.S4.review_only,false);
});

test('guest completion raises the selected level once and preserves a syncable profile event', () => {
  const state = newGuestState({idFactory:randomUUID,now:'2026-10-31T09:00:00Z',timezone:'UTC'});
  state.current_level = 'A1';
  const original = JSON.parse(readFileSync(new URL('../../resources/learning/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
  const source = {snapshot:{...original,id:'S4',level:'A1',prerequisite_ids:[]},checking_policy_version:1,session_version_id:1,access_outline:fixture.outline};
  const runId = randomUUID(), tabId = randomUUID();
  addGuestRun(state,{source,runId,promotionEventId:randomUUID(),presentationIds:original.tasks.map(randomUUID),eventId:randomUUID(),tabId,now:'2026-10-31T09:00:00Z',kind:'extra'});
  const run = state.runs[runId];
  while(true) {
    applyGuestOperation(state,runId,tabId,'help',{client_event_id:randomUUID(),expected_revision:run.revision,
      presentation_id:run.presentations[run.cursor].presentation_id,event_type:'unknown',client_occurred_at:'2026-10-31T09:01:00Z'});
    if(run.cursor===run.presentations.length-1) break;
    applyGuestOperation(state,runId,tabId,'advance',{client_event_id:randomUUID(),expected_revision:run.revision,
      presentation_id:run.presentations[run.cursor].presentation_id,client_occurred_at:'2026-10-31T09:01:00Z'});
  }
  const input = {client_event_id:randomUUID(),expected_revision:run.revision,client_occurred_at:'2026-10-31T09:02:00Z'};
  applyGuestOperation(state,runId,tabId,'finish',input);
  assert.equal(state.current_level,'A2');
  assert.equal(run.result.promotion.new_level,'A2');
  const revision = state.profile_revision;
  applyGuestOperation(state,runId,tabId,'finish',input);
  assert.equal(state.profile_revision,revision);
  assert.equal(state.outbox.at(-1).action,'level_selection');
});

test('an empty selected level stays closed with a publication explanation', () => {
  const outline = structuredClone(fixture.outline);
  outline[1].units = [];
  const state = learningAccess(outline,[],'A1');
  assert.equal(state.levels.A1.locked,true);
  assert.equal(state.levels.A1.reason,'Занятия ещё не опубликованы.');
  assert.equal(state.levels.PRE_A1.locked,false);
});
