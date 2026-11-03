import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { newGuestState, addGuestRun, applyGuestOperation } from '../../resources/js/App/Learning/guestProgress.js';
import { freezeGuestBatch as freezeBatch, acknowledgeGuestBatch, syncGuestRepository as syncRepository, retryRejectedGuestEvents } from '../../resources/js/App/Learning/guestSync.js';
const snapshot=JSON.parse(readFileSync(new URL('../../resources/learning/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
const stamp='2026-10-25T10:00:00.000Z';
const freezeGuestBatch=(state,owner)=>freezeBatch(state,owner,randomUUID);
const syncGuestRepository=(options)=>syncRepository({...options,idFactory:randomUUID});
const seed=()=>{
  const state=newGuestState({idFactory:randomUUID,now:stamp,timezone:'Europe/Moscow'});
  const runId=randomUUID();
  addGuestRun(state,{source:{snapshot,session_version_id:1,checking_policy_version:1},runId,presentationIds:snapshot.tasks.map(()=>randomUUID()),eventId:randomUUID(),tabId:'tab',now:stamp});
  return state;
};
class Repository {
  constructor(state){this.state=structuredClone(state);this.temporary=false;this.fail=false;}
  async read(){return structuredClone(this.state);}
  async mutate(action){const state=structuredClone(this.state);const result=action(state);if(this.fail)throw new Error('quota');this.state=state;return structuredClone(result);}
}
const receipt=(batch,extra={})=>({owner_user_id:7,guest_id:batch.guest_id,episode_id:'episode',batch_id:batch.batch_id,
  accepted:[...batch.level_events,...batch.runs.flatMap((run)=>run.events),...batch.marks].map((event)=>event.client_event_id),rejected:[],
  mapping:Object.fromEntries(batch.runs.map((run)=>[run.guest_run_id,{server_run_id:'server-'+run.guest_run_id,presentation_map:Object.fromEntries(run.presentation_ids.map((id)=>[id,'server-'+id])),server_revision:1}])),...extra});
const accountRepository={async saveDraft(){}};

test('sealed episode batches retain exact IDs and bytes after a timeout and only acknowledged events leave the outbox',async()=>{
  const repository=new Repository(seed());let calls=0;const sent=[];let committed;
  const transport={profile:async()=>({owner_user_id:7}),sync:async(body)=>{sent.push(JSON.stringify(body));calls++;if(calls===1){committed=receipt(body);throw new Error('timeout');}return committed;}};
  await assert.rejects(syncGuestRepository({ownerUserId:7,repository,transport,accountRepository}));
  assert.ok(repository.state.outbox.length);
  assert.equal(repository.state.sync.batches.length,1);
  await syncGuestRepository({ownerUserId:7,repository,transport,accountRepository});
  assert.equal(sent[0],sent[1]);assert.equal(repository.state.outbox.length,0);
  assert.equal(repository.state.bound_user_id,7);
});

test('an action arriving during transfer is sealed into the next batch and retains its event ID',async()=>{
  const repository=new Repository(seed());const sent=[];let tailId;
  const transport={profile:async()=>({owner_user_id:7}),sync:async(body)=>{
    sent.push(structuredClone(body));
    if(sent.length===1) await repository.mutate((state)=>{
      const run=Object.values(state.runs)[0];tailId=randomUUID();
      applyGuestOperation(state,run.id,'tab','help',{client_event_id:tailId,expected_revision:1,client_occurred_at:stamp,presentation_id:run.presentations[0].presentation_id,event_type:'unknown'});
    });
    return receipt(body);
  }};
  await syncGuestRepository({ownerUserId:7,repository,transport,accountRepository});
  assert.equal(sent.length,2);
  assert.ok(sent[1].runs[0].events.some((event)=>event.client_event_id===tailId));
  assert.ok(!sent[0].runs[0].events.some((event)=>event.client_event_id===tailId));
  assert.equal(repository.state.outbox.length,0);
});

test('partial rejection retains original events with explanations and explicit retry uses a new batch but the same event IDs',async()=>{
  const repository=new Repository(seed());let partial=true;const sent=[];
  const transport={profile:async()=>({owner_user_id:7}),sync:async(body)=>{
    sent.push(structuredClone(body));const result=receipt(body);
    if(partial) {result.accepted=body.level_events.map((event)=>event.client_event_id);result.rejected=[{guest_run_id:body.runs[0].guest_run_id,event_ids:body.runs[0].events.map((event)=>event.client_event_id),message:'version missing'}];result.mapping={};}
    return result;
  }};
  const first=await syncGuestRepository({ownerUserId:7,repository,transport,accountRepository});
  assert.equal(first.rejected.length,1);assert.equal(repository.state.sync.rejected[0].message,'version missing');
  assert.equal(repository.state.outbox[0].sync_status,'rejected');
  await repository.mutate(retryRejectedGuestEvents);partial=false;
  await syncGuestRepository({ownerUserId:7,repository,transport,accountRepository});
  assert.notEqual(sent[0].batch_id,sent[1].batch_id);
  assert.equal(sent[0].runs[0].events[0].client_event_id,sent[1].runs[0].events[0].client_event_id);
  assert.equal(repository.state.outbox.length,0);
});

for(const code of [401,419]) test('authentication error '+code+' retains the exact pending batch for recovery',async()=>{
  const repository=new Repository(seed());let fail=true;const sent=[];
  const transport={profile:async()=>({owner_user_id:7}),sync:async(body)=>{sent.push(JSON.stringify(body));if(fail)throw Object.assign(new Error('auth'),{response:{status:code}});return receipt(body);}};
  await assert.rejects(syncGuestRepository({ownerUserId:7,repository,transport,accountRepository}));
  fail=false;
  await syncGuestRepository({ownerUserId:7,repository,transport,accountRepository});
  assert.equal(sent[0],sent[1]);
});

test('another owner never receives the bound or sealed source and a wrong receipt cannot clear it',async()=>{
  const state=seed();const batch=freezeGuestBatch(state,7);
  assert.throws(()=>freezeGuestBatch(state,8),/исходный аккаунт/);
  const repository=new Repository(state);let posts=0;
  await assert.rejects(syncGuestRepository({ownerUserId:8,repository,transport:{profile:async()=>({owner_user_id:8}),sync:async()=>posts++},accountRepository}));
  assert.equal(posts,0);
  const before=structuredClone(state);
  assert.throws(()=>acknowledgeGuestBatch(state,batch,{...receipt(batch.payload),owner_user_id:8}),/не подтвердил/);
  assert.deepEqual(state,before);
});

test('storage failure prevents sending and the latest unsubmitted draft goes only to its owner and mapped presentation',async()=>{
  const repository=new Repository(seed());repository.fail=true;let posts=0;
  const transport={profile:async()=>({owner_user_id:7}),sync:async(body)=>{posts++;return receipt(body);}};
  await assert.rejects(syncGuestRepository({ownerUserId:7,repository,transport,accountRepository}));
  assert.equal(posts,0);
  repository.fail=false;
  const run=Object.values(repository.state.runs)[0];
  run.drafts[run.presentations[0].presentation_id]={choice_id:'b'};
  const drafts=[];
  await syncGuestRepository({ownerUserId:7,repository,transport,accountRepository:{saveDraft:async(...args)=>drafts.push(args)}});
  assert.equal(posts,1);assert.equal(drafts[0][0],7);assert.equal(drafts[0][1],'server-'+run.id);
  assert.equal(drafts[0][2],'server-'+run.presentations[0].presentation_id);assert.deepEqual(drafts[0][3],{choice_id:'b'});
});

test('a previously rejected run keeps its explanation when another batch succeeds',()=>{
  const state=seed();const first=freezeGuestBatch(state,7);
  const badIds=first.payload.runs[0].events.map((event)=>event.client_event_id);
  acknowledgeGuestBatch(state,first,receipt(first.payload,{accepted:first.payload.level_events.map((event)=>event.client_event_id),mapping:{},rejected:[{event_ids:badIds,message:'bad run'}]}));
  const nextEvent={client_event_id:randomUUID(),sequence:99,action:'level_selection',current_level:'A1',timezone:'UTC',client_occurred_at:stamp};
  state.outbox.push(nextEvent);
  const second=freezeGuestBatch(state,7);
  acknowledgeGuestBatch(state,second,receipt(second.payload));
  assert.equal(state.sync.rejected[0].message,'bad run');assert.equal(state.outbox[0].sync_status,'rejected');
});
