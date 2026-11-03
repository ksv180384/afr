import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { newGuestState, migrateGuestState, changeGuestProfile, addGuestRun, applyGuestOperation, guestRunView } from '../../resources/js/App/Learning/guestProgress.js';
import { GuestProgressRepository, GUEST_DATABASE, GUEST_DATABASE_VERSION } from '../../resources/js/App/Learning/guestRepository.js';
import { createGuestAdapter } from '../../resources/js/App/Learning/guestAdapter.js';

const templates = JSON.parse(readFileSync(new URL('../../resources/learning/templates/pre-a1-u01-examples.json',import.meta.url)));
const stamp = '2026-10-25T20:55:00.000Z';
const copy = structuredClone;
const seed = () => newGuestState({idFactory:randomUUID,now:stamp,timezone:'Europe/Moscow'});
const content = (snapshot) => ({snapshot,session_version_id:snapshot.id,checking_policy_version:1});
const append = (state, snapshot, tabId = 'first') => {
  const runId = randomUUID();
  addGuestRun(state,{source:content(snapshot),runId,presentationIds:snapshot.tasks.map(()=>randomUUID()),eventId:randomUUID(),tabId,now:stamp});
  return runId;
};
class MemoryTransactions {
  constructor(state) { this.state = copy(state); this.temporary = false; this.fail = false; }
  async read() { return copy(this.state); }
  async mutate(apply) {
    const next = copy(this.state);
    const result = apply(next);
    if (this.fail) throw new DOMException('Full','QuotaExceededError');
    this.state = next;
    return copy(result);
  }
}
const adapterFor = (repository,runId,tabId='first',onStatus=()=>{}) => createGuestAdapter({repository,runId,tabId,onStatus,idFactory:randomUUID,now:()=>stamp});
const correct = (task) => task.correct_choice_id ? {choice_id:task.correct_choice_id}
  : task.type === 'matching_pairs' ? {pairs:task.correct_pairs.map(({left_id,right_id})=>({left_id,right_id}))}
  : task.type === 'word_order' ? {token_ids:task.accepted_orders[0]} : {value:task.accepted_answers[0]};

test('guest storage migrations preserve snapshots outbox owner binding and receipts', () => {
  const old = seed();
  old.storage_schema_version = 1; delete old.sync_receipts; delete old.bound_user_id;
  const migrated = migrateGuestState(old);
  assert.equal(migrated.storage_schema_version,4);
  assert.deepEqual(migrated.sync_receipts,{});
  assert.equal(migrated.bound_user_id,null);
  assert.equal(migrated.guest_id,old.guest_id);
  assert.deepEqual(migrated.outbox,old.outbox);
  assert.equal(old.storage_schema_version,1);
  assert.throws(()=>migrateGuestState({...old,storage_schema_version:99}),/не поддерживается/);
  assert.equal(GUEST_DATABASE,'afr-guest-practice');
  assert.equal(GUEST_DATABASE_VERSION,4);
});

test('explicit temporary mode survives actions in memory and never reports browser persistence', async () => {
  const repository = new GuestProgressRepository({getIndexedDB:()=>undefined,idFactory:randomUUID,now:()=>stamp});
  await assert.rejects(repository.initialize());
  repository.useTemporary();
  const state = await repository.read();
  const runId = await repository.mutate((next)=>append(next,templates.sessions[0]));
  const notices = [];
  const adapter = adapterFor(repository,runId,'first',(notice)=>notices.push(notice));
  const view = await adapter.load();
  assert.equal(view.total,6);
  await adapter.help({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,event_type:'unknown'});
  assert.ok(notices.every((notice)=>notice.kind === 'temporary'));
  assert.equal((await repository.read()).guest_id,state.guest_id);
  const other = new GuestProgressRepository({getIndexedDB:()=>undefined,idFactory:randomUUID,now:()=>stamp});
  other.useTemporary();
  assert.notEqual((await other.read()).guest_id,state.guest_id);
});

test('guest passes both full examples, reloads between every step, and keeps stable history and UUIDs', async () => {
  const repository = new MemoryTransactions(seed());
  for (const snapshot of templates.sessions) {
    const runId = await repository.mutate((state)=>append(state,snapshot));
    let adapter = adapterFor(repository,runId);
    let view = await adapter.load();
    const presentations = copy(repository.state.runs[runId].presentations.map((item)=>item.presentation_id));
    const pinned = copy(repository.state.runs[runId].snapshot);
    for (let step=0; step<view.total; step++) {
      if (view.introductions.length) view = await adapter.load({introductionSeen:view.presentation.presentation_id});
      view = await adapter.answer({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,answer:correct(view.task)});
      assert.equal(view.presentation.first_result.score,1);
      adapter = adapterFor(repository,runId,'reload-'+step);
      view = await adapter.load();
      assert.deepEqual(view.presentation.answer,correct(view.task));
      if (step < view.total-1) view = await adapter.load({advanceFrom:view.presentation.presentation_id});
    }
    view = await adapter.finish();
    assert.equal(view.summary.independent_correct_steps,view.total);
    assert.equal(view.finished,true);
    assert.deepEqual(repository.state.runs[runId].snapshot,pinned);
    assert.deepEqual(repository.state.runs[runId].presentations.map((item)=>item.presentation_id),presentations);
    const after = await adapterFor(repository,runId,'last').load();
    assert.deepEqual(after.summary,view.summary);
  }
  const state = await repository.read();
  assert.equal(Object.keys(state.runs).length,2);
  assert.equal(Object.keys(state.vocabulary).length,12);
  assert.equal(new Set(state.outbox.map((event)=>event.client_event_id)).size,state.outbox.length);
  assert.deepEqual(state.outbox.map((event)=>event.sequence),state.outbox.map((_,index)=>index+1));
  assert.ok(state.outbox.every((event)=>event.sync_status === 'pending'));
  assert.ok(Object.values(state.runs)[1].record_sequence > Object.values(state.runs)[0].record_sequence);
});

test('assistance, introductions, unknown, technical skip and corrections use the shared policy', async () => {
  const repository = new MemoryTransactions(seed());
  const runId = append(repository.state,templates.sessions[0]);
  const adapter = adapterFor(repository,runId);
  let view = await adapter.load();
  view = await adapter.load({introductionSeen:view.presentation.presentation_id});
  assert.equal(view.presentation.help_used,false);
  view = await adapter.help({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,event_type:'help',help_kind:'audio_transcript'});
  view = await adapter.answer({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,answer:correct(view.task)});
  assert.equal(view.presentation.first_result.score,0);
  const restored = await adapterFor(repository,runId).load();
  assert.deepEqual(restored.assistance,['audio_transcript']);
  const other = new MemoryTransactions(seed());
  const otherId = append(other.state,templates.sessions[0]);
  const second = adapterFor(other,otherId);
  view = await second.load();
  view = await second.help({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,event_type:'technical_skip',reason:'audio_unavailable'});
  assert.equal(view.presentation.first_result.technical_skip,true);
  assert.equal(view.presentation.first_result.score,0);
  assert.ok(view.presentation.first_result.assessed_lexemes.every((item)=>item.effect === 'none'));
});

test('guest replay checks stable event ID before revision and refuses a different body', () => {
  const state = seed();
  const runId = append(state,templates.sessions[0]);
  const run = state.runs[runId];
  const input = {client_event_id:randomUUID(),expected_revision:1,client_occurred_at:stamp,presentation_id:run.presentations[0].presentation_id,event_type:'unknown'};
  const first = applyGuestOperation(state,runId,'first','help',input);
  const count = state.outbox.length;
  assert.deepEqual(applyGuestOperation(state,runId,'first','help',input),first);
  assert.equal(state.outbox.length,count);
  assert.throws(()=>applyGuestOperation(state,runId,'first','help',{...input,event_type:'technical_skip',reason:'audio_unavailable'}),/другим содержимым/);
});

test('storage failure cannot advance an answer or display a grade; retry accepts the same ID once', async () => {
  const repository = new MemoryTransactions(seed());
  const runId = append(repository.state,templates.sessions[0]);
  const notices = [];
  const adapter = adapterFor(repository,runId,'first',(notice)=>notices.push(notice));
  const view = await adapter.load();
  const input = {client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,answer:correct(view.task)};
  repository.fail = true;
  await assert.rejects(adapter.answer(input),{name:'QuotaExceededError'});
  assert.equal(repository.state.runs[runId].presentations[0].first_result,null);
  assert.equal(notices.at(-1).kind,'storage');
  repository.fail = false;
  const accepted = await adapter.answer(input);
  assert.equal(accepted.presentation.first_result.score,1);
  await adapter.answer(input);
  assert.equal(repository.state.outbox.filter((event)=>event.client_event_id===input.client_event_id).length,1);
});

test('only one tab can write a run; takeover preserves draft and accepted revision', async () => {
  const repository = new MemoryTransactions(seed());
  const runId = append(repository.state,templates.sessions[0]);
  const notices = [];
  const first = adapterFor(repository,runId,'first',(notice)=>notices.push(notice));
  let view = await first.load();
  await first.storeDraft({presentation_id:view.presentation.presentation_id,answer:correct(view.task)});
  const second = adapterFor(repository,runId,'second');
  view = await second.load();
  assert.deepEqual(view.local_draft,correct(view.task));
  await first.checkOwnership();
  assert.equal(notices.at(-1).kind,'conflict');
  await assert.rejects(first.help({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,event_type:'unknown'}),/другой вкладке/);
  assert.equal(repository.state.runs[runId].presentations[0].first_result,null);
  await second.help({client_event_id:randomUUID(),presentation_id:view.presentation.presentation_id,event_type:'unknown'});
  assert.equal(repository.state.runs[runId].revision,2);
  view = await first.acceptServerState();
  assert.equal(view.presentation.first_result.score,0);
});

test('run keeps original timezone dates and published snapshot across profile changes and offline resume', async () => {
  const state = seed();
  const source = copy(templates.sessions[0]);
  const runId = append(state,source);
  source.title = 'Changed externally';
  changeGuestProfile(state,{client_event_id:randomUUID(),expected_revision:1,current_level:'A1',timezone:'Asia/Tokyo',client_occurred_at:'2026-10-26T22:00:00Z'});
  const repository = new MemoryTransactions(state);
  const adapter = adapterFor(repository,runId);
  let view = await adapter.load();
  assert.equal(view.timezone,'Europe/Moscow');
  assert.equal(view.local_date,'2026-10-25');
  assert.notEqual(view.session.title,source.title);
  await adapter.saveDraft({presentation_id:view.presentation.presentation_id,answer:{choice_id:'b'}});
  view = await adapterFor(repository,runId,'next').load();
  assert.equal(view.resume,true);
  assert.deepEqual(view.local_draft,{choice_id:'b'});
  assert.notEqual(state.level_selection_event_id,state.outbox[0].client_event_id);
  assert.throws(()=>append(repository.state,templates.sessions[1]),/предыдущие занятия/);
});

test('future steps and premature finish reject atomically; guest data never reads account cache', async () => {
  const repository = new MemoryTransactions(seed());
  const runId = append(repository.state,templates.sessions[0]);
  const adapter = adapterFor(repository,runId);
  await adapter.load();
  const before = copy(repository.state);
  await assert.rejects(adapter.finish(),/все шаги/);
  await assert.rejects(adapter.help({client_event_id:randomUUID(),presentation_id:repository.state.runs[runId].presentations[1].presentation_id,event_type:'unknown'}),/шаг изменился/);
  assert.deepEqual(repository.state,before);
  assert.equal(repository.state.bound_user_id,null);
  assert.deepEqual(repository.state.sync_receipts,{});
  assert.equal(repository.state.owner_user_id,undefined);
  assert.equal(guestRunView(repository.state.runs[runId]).mode,'guest');
});

test('blocked notification channel does not prevent storage recovery or the focus ownership check', async () => {
  const repository = new GuestProgressRepository({getIndexedDB:()=>undefined,channelFactory:()=>{ throw new DOMException('Blocked','SecurityError'); }});
  const unsubscribe = repository.subscribe('tab',()=>{});
  await assert.rejects(repository.initialize());
  assert.equal(repository.channel,null);
  unsubscribe();
  repository.close();
});

test('guest draft autosave survives navigation without pausing, scoring or adding an outbox event', async () => {
  const state=seed(); const runId=append(state,templates.sessions[0]);
  const repository=new MemoryTransactions(state);
  const notices=[];
  const adapter=adapterFor(repository,runId,'first',value=>notices.push(value));
  const view=await adapter.load();
  notices.length=0;
  const before=copy(repository.state);
  await adapter.storeDraft({presentation_id:view.presentation.presentation_id,answer:{choice_id:'b'}});
  assert.deepEqual(notices.map(entry=>entry.kind),['draft']);
  const restored=await adapterFor(repository,runId,'next').load();
  assert.deepEqual(restored.local_draft,{choice_id:'b'});
  assert.equal(restored.status,'in_progress');
  assert.equal(repository.state.runs[runId].revision,before.runs[runId].revision);
  assert.deepEqual(repository.state.outbox,before.outbox);
  assert.equal(repository.state.runs[runId].presentations[0].first_result,null);
});

test('explicit extra session from public catalog keeps the selected level and still checks prerequisites', () => {
  const state = seed();
  const snapshot = copy(templates.sessions[0]); snapshot.level = 'A1';
  const input = {source:content(snapshot),runId:randomUUID(),presentationIds:snapshot.tasks.map(randomUUID),eventId:randomUUID(),tabId:'public',now:stamp};
  assert.throws(()=>addGuestRun(state,input),/предыдущие/);
  addGuestRun(state,{...input,kind:'extra'});
  assert.equal(state.current_level,'PRE_A1');
  assert.equal(state.runs[input.runId].snapshot.level,'A1');
  const blocked=copy(snapshot); blocked.prerequisite_ids=['NOT_COMPLETED'];
  assert.throws(()=>addGuestRun(state,{...input,source:content(blocked),runId:randomUUID(),kind:'extra'}),/предыдущие/);
});
