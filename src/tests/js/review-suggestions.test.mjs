import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { nextLocalMidnight, changeGuestReviewSettings, reviewSuggestionsView } from '../../resources/js/App/Learning/reviewSuggestions.js';
import { newGuestState, migrateGuestState } from '../../resources/js/App/Learning/guestProgress.js';
import { freezeGuestBatch, acknowledgeGuestBatch, hasGuestWork } from '../../resources/js/App/Learning/guestSync.js';
import { createReviewSettingsController } from '../../resources/js/App/Learning/reviewSettingsController.js';

const now='2026-11-06T12:00:00.000Z';
const seed=()=>newGuestState({idFactory:randomUUID,now,timezone:'Europe/Moscow'});
const input=(revision,fields={})=>({client_event_id:randomUUID(),expected_revision:revision,action:'snooze_today',...fields});
const account=(id=7)=>({owner_user_id:id,revision:1,current_level:'B1',timezone:'Europe/Moscow',review_suggestions:{available:true,enabled:true,snoozed_until:null,can_suggest:true}});

for(const [instant,timezone,until] of [
  ['2026-03-28T23:00:00Z','Europe/Paris','2026-03-29T22:00:00+00:00'],
  ['2026-10-24T22:00:00Z','Europe/Paris','2026-10-25T23:00:00+00:00'],
  ['2026-11-06T12:00:00Z','Europe/Moscow','2026-11-06T21:00:00+00:00'],
  ['2018-11-04T02:59:00Z','America/Sao_Paulo','2018-11-04T03:00:00+00:00'],
]) test(`calendar refusal ends at local day boundary ${instant} ${timezone}`,()=>assert.equal(nextLocalMidnight(instant,timezone),until));

test('v4 migration preserves all progress words queues and ownership while adding defaults',()=>{
  const original=seed();original.storage_schema_version=4;delete original.review_suggestions;delete original.review_settings_receipts;
  original.runs={kept:{id:'kept',snapshot:{tasks:[{id:'Q01'}]},answer:{choice_id:'c1'}}};original.vocabulary={word:{next_review_at:'2027-01-01'}};original.bound_user_id=8;
  const result=migrateGuestState(original);
  assert.equal(result.storage_schema_version,6);assert.deepEqual(result.runs,original.runs);assert.deepEqual(result.vocabulary,{word:{...original.vocabulary.word,last_independent_success_at:null}});assert.deepEqual(result.outbox,original.outbox);assert.equal(result.bound_user_id,8);
  assert.deepEqual(result.review_suggestions,{enabled:true,snoozed_until:null,revision:1});assert.equal(original.storage_schema_version,4);
});

test('local settings survive reload preserve level words and refusal and replay before revision checks',()=>{
  let state=seed();state.vocabulary={word:{next_review_at:'2027-01-01'}};const before=structuredClone(state);const request=input(1);
  const receipt=changeGuestReviewSettings(state,request,now);assert.equal(receipt.snoozed_until,'2026-11-06T21:00:00+00:00');
  state=migrateGuestState(JSON.parse(JSON.stringify(state)));
  changeGuestReviewSettings(state,input(2,{action:'toggle',enabled:false}),now);changeGuestReviewSettings(state,input(3,{action:'toggle',enabled:true}),now);
  assert.equal(state.review_suggestions.snoozed_until,receipt.snoozed_until);assert.equal(reviewSuggestionsView(state.review_suggestions,now).can_suggest,false);
  assert.deepEqual(changeGuestReviewSettings(state,request,'2026-11-07T12:00:00Z'),receipt);assert.equal(state.review_suggestions.revision,4);
  assert.equal(reviewSuggestionsView(state.review_suggestions,'2026-11-06T21:00:00Z').can_suggest,true);
  assert.equal(state.current_level,before.current_level);assert.equal(state.profile_revision,before.profile_revision);assert.deepEqual(state.outbox,before.outbox);assert.deepEqual(state.vocabulary,before.vocabulary);
  assert.throws(()=>changeGuestReviewSettings(state,{...request,action:'toggle',enabled:false},now),e=>e.code==='idempotency_conflict');
  assert.throws(()=>changeGuestReviewSettings(state,input(1),now),e=>e.code==='revision_conflict');
  state.bound_user_id=7;assert.throws(()=>changeGuestReviewSettings(state,input(4),now),e=>e.code==='bound_owner');
});

test('a settings-only guest refusal seals exact bytes and never transfers the enabled flag',()=>{
  const state=seed();changeGuestReviewSettings(state,input(1),'2099-11-06T12:00:00Z');state.review_suggestions.enabled=false;
  assert.equal(hasGuestWork(state),true);const batch=freezeGuestBatch(state,7,randomUUID,account());
  assert.deepEqual(batch.payload.level_events,[]);assert.equal(batch.payload.review_snooze.expected_revision,1);assert.equal(batch.payload.review_snooze.timezone,'Europe/Moscow');assert.ok(!('enabled' in batch.payload.review_snooze));
  assert.deepEqual(freezeGuestBatch(state,7,randomUUID,{...account(),revision:99}),batch);
  const receipt={owner_user_id:7,guest_id:state.guest_id,batch_id:batch.payload.batch_id,episode_id:randomUUID(),accepted:[],rejected:[],mapping:{},profile:account(),review_snooze_applied:false};
  acknowledgeGuestBatch(state,batch,receipt);assert.equal(state.sync.review_snooze_acknowledged_until,batch.payload.review_snooze.until);
  assert.equal(freezeGuestBatch(state,7,randomUUID,account()),null);assert.equal(state.review_suggestions.enabled,false);
});

test('lost acknowledgement retries the original request without resending level or timezone',async()=>{
  let profile=account();const requests=[];let lost=true;const transport={profile:async()=>structuredClone(profile),updateReviewSettings:async request=>{
    requests.push(structuredClone(request));profile={...profile,revision:2,review_suggestions:{...profile.review_suggestions,enabled:false}};
    if(lost){lost=false;throw new Error('offline');}return {profile};
  }};
  const controller=createReviewSettingsController({transport,getOwner:()=>7,idFactory:randomUUID});await controller.load();
  await assert.rejects(controller.save('toggle',false),/offline/);assert.equal(controller.state.pending,true);
  await controller.save();assert.deepEqual(requests[0],requests[1]);assert.equal(requests[1].expected_revision,1);assert.ok(!('current_level' in requests[0]));assert.ok(!('timezone' in requests[0]));
  assert.equal(controller.state.profile.review_suggestions.enabled,false);assert.equal(controller.state.pending,false);
});

test('a stale device refreshes a revision conflict and requires a fresh explicit choice',async()=>{
  let profile=account();let calls=0;const controller=createReviewSettingsController({idFactory:randomUUID,getOwner:()=>7,transport:{profile:async()=>structuredClone(profile),updateReviewSettings:async()=>{
    calls++;profile={...profile,revision:2,review_suggestions:{...profile.review_suggestions,enabled:false}};throw Object.assign(new Error('conflict'),{response:{status:409}});
  }}});
  await controller.load();await assert.rejects(controller.save('toggle',true),/Повторите выбор/);assert.equal(calls,1);assert.equal(controller.state.pending,false);assert.equal(controller.state.profile.review_suggestions.enabled,false);
});

test('account change or wrong authenticated identity prevents the settings write',async()=>{
  let owner=7;let calls=0;let reads=0;
  const controller=createReviewSettingsController({idFactory:randomUUID,getOwner:()=>owner,transport:{profile:async()=>{reads++;if(reads===2)owner=8;return account();},updateReviewSettings:async()=>{calls++;}}});
  await controller.load();await assert.rejects(controller.save('toggle',false),/Аккаунт изменился/);assert.equal(calls,0);
  const wrong=createReviewSettingsController({idFactory:randomUUID,getOwner:()=>7,transport:{profile:async()=>account(8)}});await assert.rejects(wrong.load(),/исходный аккаунт/);
});

test('guest controller persists in its supplied repository without profile or run mutations',async()=>{
  let local=seed();const before=structuredClone(local);const repository={read:async()=>structuredClone(local),mutate:async apply=>{const next=structuredClone(local);const result=apply(next);local=next;return result;}};
  const c=createReviewSettingsController({guestRepository:repository,getOwner:()=>null,idFactory:randomUUID,now:()=>now});await c.load();await c.save('snooze_today');
  const reload=createReviewSettingsController({guestRepository:repository,getOwner:()=>null,now:()=>now});await reload.load();assert.equal(reload.state.profile.review_suggestions.can_suggest,false);reload.state.error='old storage error';await reload.load();assert.equal(reload.state.error,'');
  assert.equal(local.profile_revision,before.profile_revision);assert.deepEqual(local.runs,before.runs);assert.deepEqual(local.outbox,before.outbox);
});


test('settings-only login before the migration keeps preferences without sending a fake level event',()=>{
  const state=seed();changeGuestReviewSettings(state,input(1),'2099-11-06T12:00:00Z');const outbox=structuredClone(state.outbox);
  const profile={...account(),review_suggestions:{available:false,enabled:false,snoozed_until:null,can_suggest:false}};
  assert.equal(freezeGuestBatch(state,7,randomUUID,profile),null);assert.equal(state.sync.owner_user_id,null);assert.deepEqual(state.outbox,outbox);
  assert.ok(freezeGuestBatch(state,7,randomUUID,account()).payload.review_snooze);
});

test('a sealed refusal batch is recoverable even after its local deadline expires',()=>{
  const state=seed();changeGuestReviewSettings(state,input(1),'2099-11-06T12:00:00Z');const batch=freezeGuestBatch(state,7,randomUUID,account());
  const realNow=Date.now;Date.now=()=>new Date('2099-11-07T12:00:00Z').getTime();
  try {assert.equal(hasGuestWork(state),true);assert.deepEqual(freezeGuestBatch(state,7,randomUUID,account()),batch);}
  finally {Date.now=realNow;}
});
