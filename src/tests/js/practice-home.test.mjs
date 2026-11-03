import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { createPracticeHomeStore } from '../../resources/js/App/Learning/practiceHomeStore.js';
import { newGuestState } from '../../resources/js/App/Learning/guestProgress.js';
const stamp='2026-10-26T09:00:00.000Z';
const snapshot=JSON.parse(readFileSync(new URL('../../resources/learning/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
const deferred=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
class Repository {
  constructor(){this.state=newGuestState({idFactory:randomUUID,now:stamp,timezone:'Europe/Moscow'});}
  async archiveBound(){}
  async initialize(){}
  async read(){return structuredClone(this.state);}
  async mutate(apply){const copy=structuredClone(this.state);const result=apply(copy);this.state=copy;return structuredClone(result);}
}
const guestTransport={
  async catalog(){return {sessions:[{id:snapshot.id,title:snapshot.title,level:'PRE_A1',prerequisite_ids:[],duration_minutes:5}]};},
  async session(){return {snapshot,session_version_id:1,checking_policy_version:1};},
};
const home=(owner=7,level='PRE_A1')=>({profile:{owner_user_id:owner,current_level:level,timezone:'UTC',revision:1},candidate:{id:snapshot.id},run:null});
const make=(extra={})=>createPracticeHomeStore({transport:{async today(){return home();},async history(){return {data:[]};}},
  guestTransport,guestRepository:new Repository(),accountRepository:{},idFactory:randomUUID,now:()=>stamp,...extra});

test('guest home stays neutral until storage is read and concurrent refreshes share one read',async()=>{
  const gate=deferred();const repository=new Repository();repository.state.current_level='B1';
  let reads=0;repository.read=async()=>{reads++;return gate.promise;};
  const store=make({guestRepository:repository});
  const first=store.refresh(null);const second=store.refresh(null);
  assert.equal(first,second);assert.equal(store.state.home,null);assert.equal(store.state.ready,false);
  gate.resolve(repository.state);
  await first;
  assert.equal(reads,1);assert.equal(store.state.home.profile.current_level,'B1');
  assert.equal(Object.keys(repository.state.runs).length,0);
});

test('two simultaneous guest start actions create one run and later actions continue its stable ID',async()=>{
  const repository=new Repository();const store=make({guestRepository:repository});
  await store.refresh(null);
  const first=store.start(null);const second=store.start(null);
  assert.equal(first,second);
  const path=await first;
  assert.match(path,/^\/daily\/guest\//);
  assert.equal(Object.keys(repository.state.runs).length,1);
  assert.equal(repository.state.outbox.filter((event)=>event.action==='start').length,1);
  assert.equal(await store.start(null),path);
  assert.equal(Object.keys(repository.state.runs).length,1);
});

test('late account reads never replace a different actor and GET refresh never starts a run',async()=>{
  const gate=deferred();let calls=0;let writes=0;
  const store=make({transport:{today(){calls++;return calls===1?gate.promise:Promise.resolve(home(8));},async history(){return {data:[]};},async call(){writes++;}}});
  const old=store.refresh(7);await store.refresh(8);gate.resolve(home(7));await old;
  assert.equal(store.state.owner,8);assert.equal(store.state.home.profile.owner_user_id,8);assert.equal(writes,0);
});

test('offline catalog refresh preserves the saved continuation and failure to load new content creates nothing',async()=>{
  const repository=new Repository();let online=true;
  const store=make({guestRepository:repository,guestTransport:{...guestTransport,async catalog(){if(!online)throw new Error('offline');return guestTransport.catalog();}}});
  await store.refresh(null);const path=await store.start(null);online=false;
  await store.refresh(null);
  assert.match(store.state.home.content_error,/Нет соединения/);
  assert.equal(await store.start(null),path);assert.equal(Object.keys(repository.state.runs).length,1);
  const empty=new Repository();
  const failed=make({guestRepository:empty,guestTransport:{...guestTransport,async session(){throw new Error('offline');}}});
  await failed.refresh(null);
  await assert.rejects(failed.start(null),/offline/);
  assert.equal(Object.keys(empty.state.runs).length,0);
});

test('profile changes retry the same sealed nonce after a lost acknowledgement and keep the timezone',async()=>{
  let level='PRE_A1';const bodies=[];
  const store=make({transport:{
    async today(){return home(7,level);},async history(){return {data:[]};},async profile(){return {owner_user_id:7};},
    async updateProfile(input){bodies.push(structuredClone(input));level=input.current_level;if(bodies.length===1)throw new Error('lost ack');return {profile:home(7,level).profile};},
  }});
  await store.refresh(7);
  await assert.rejects(store.changeLevel(7,'A1'),/lost ack/);
  assert.equal(store.state.profilePending,true);
  await store.changeLevel(7,'B1');
  assert.deepEqual(bodies[0],bodies[1]);assert.equal(level,'A1');assert.equal(bodies[0].timezone,'UTC');assert.equal(store.state.profilePending,false);
});

test('a failed account refresh retains its cache with an explicit error and a new guest never sees that cache',async()=>{
  let online=true;
  const store=make({transport:{async today(){if(!online)throw new Error('offline');return home(7,'B1');},async history(){return {data:[]};}}});
  await store.refresh(7);online=false;
  await assert.rejects(store.refresh(7),/offline/);
  assert.equal(store.state.home.profile.current_level,'B1');assert.match(store.state.error,/offline/);
  await store.refresh(null);
  assert.equal(store.state.owner,null);assert.equal(store.state.home.profile.current_level,'PRE_A1');
});

test('guest level changes preserve all existing run identities and snapshots',async()=>{
  const repository=new Repository();const store=make({guestRepository:repository});
  await store.refresh(null);await store.start(null);
  const runs=structuredClone(repository.state.runs);
  await store.changeLevel(null,'A1');
  assert.equal(store.state.home.profile.current_level,'A1');
  assert.deepEqual(repository.state.runs,runs);
});
test('a changed server identity blocks profile writes from a stale account screen',async()=>{
  let writes=0;
  const store=make({transport:{async today(){return home(7);},async history(){return {data:[]};},
    async profile(){return {owner_user_id:8};},async updateProfile(){writes++;}}});
  await store.refresh(7);
  await assert.rejects(store.changeLevel(7,'B1'),/исходный аккаунт/);
  assert.equal(writes,0);
});

test('today can load without requesting unopened history, which is fetched when requested', async () => {
  let todayCalls = 0; let historyCalls = 0;
  const store = make({transport:{
    async today(){ todayCalls++; return home(7); },
    async history(){ historyCalls++; return {data:[],current_page:1}; },
  }});
  await store.refresh(7,1,{includeHistory:false});
  assert.equal(todayCalls,1);
  assert.equal(historyCalls,0);
  assert.equal(store.state.history,null);
  await store.refresh(7);
  assert.equal(historyCalls,1);
  assert.equal(store.state.history.current_page,1);
});
