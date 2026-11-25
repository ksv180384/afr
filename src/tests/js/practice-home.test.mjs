import test from 'node:test';
import assert from 'node:assert/strict';
import { reactive } from 'vue';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { createPracticeHomeStore } from '../../resources/js/App/Learning/practiceHomeStore.js';
import { newGuestState } from '../../resources/js/App/Learning/guestProgress.js';
const stamp='2026-10-26T09:00:00.000Z';
const snapshot=JSON.parse(readFileSync(new URL('../../resources/practice/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
const deferred=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
class Repository {
  constructor(){this.state=newGuestState({idFactory:randomUUID,now:stamp,timezone:'Europe/Moscow'});}
  async archiveBound(){}
  async initialize(){}
  async read(){return structuredClone(this.state);}
  async mutate(apply){const copy=structuredClone(this.state);const result=apply(copy);this.state=copy;return structuredClone(result);}
}
const lesson = {id:snapshot.id, title:snapshot.title, level:'PRE_A1', prerequisite_ids:[], duration_minutes:5};
const outline = [
  {id:'PRE_A1', title:'С нуля', published:true, units:[{id:'first', title:'Первая тема', sessions:[lesson]}]},
  {id:'B1', title:'B1', published:false, units:[]},
];
const guestTransport={
  async catalog(){return {sessions:[lesson], access_outline:outline};},
  async session(){return {snapshot,session_version_id:1,checking_policy_version:1,access_outline:outline};},
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

test('guest home exposes both saved vocabulary runs when one is paused', async () => {
  const repository = new Repository();
  repository.state.runs = {
    test: {id:'test', kind:'vocabulary_test', status:'paused', started_at:stamp, record_sequence:1, snapshot},
    review: {id:'review', kind:'review', status:'in_progress', started_at:stamp, record_sequence:2, snapshot},
  };
  const store = make({guestRepository:repository});
  await store.refresh(null);
  assert.equal(store.state.home.active_run.id,'review');
  assert.deepEqual(store.state.home.vocabulary_runs,{review:'review',vocabulary_test:'test'});
});

test('two simultaneous guest starts share one attempt and a later start replaces it',async()=>{
  const repository=new Repository();const store=make({guestRepository:repository});
  await store.refresh(null);
  const first=store.start(null);const second=store.start(null);
  assert.equal(first,second);
  const path=await first;
  assert.match(path,/^\/daily\/guest\//);
  assert.equal(Object.keys(repository.state.runs).length,1);
  assert.equal(repository.state.outbox.filter((event)=>event.action==='start').length,1);
  assert.notEqual(await store.start(null),path);
  assert.equal(Object.keys(repository.state.runs).length,1);
  assert.equal(repository.state.outbox.filter((event)=>event.action==='start').length,1);
});

test('late account reads never replace a different actor and GET refresh never starts a run',async()=>{
  const gate=deferred();let calls=0;let writes=0;
  const store=make({transport:{today(){calls++;return calls===1?gate.promise:Promise.resolve(home(8));},async history(){return {data:[]};},async call(){writes++;}}});
  const old=store.refresh(7);await store.refresh(8);gate.resolve(home(7));await old;
  assert.equal(store.state.owner,8);assert.equal(store.state.home.profile.owner_user_id,8);assert.equal(writes,0);
});

test('offline catalog and lesson failures create no new attempt',async()=>{
  const repository=new Repository();let online=true;
  const store=make({guestRepository:repository,guestTransport:{...guestTransport,async catalog(){if(!online)throw new Error('offline');return guestTransport.catalog();}}});
  await store.refresh(null);const path=await store.start(null);online=false;
  await store.refresh(null);
  assert.match(store.state.home.content_error,/Нет соединения/);
  await assert.rejects(store.start(null),/Нет соединения/);
  assert.deepEqual(Object.keys(repository.state.runs), [path.split('/').pop()]);
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
test('guest practice restarts lessons on the selected level after switching levels', async () => {
  const b1Snapshot = {...snapshot, id: 'B1-FIRST', level: 'B1'};
  const b1Lesson = {...lesson, id: b1Snapshot.id, level: 'B1'};
  const accessOutline = [outline[0], {
    id: 'B1', title: 'B1', published: true,
    units: [{id: 'b1-first', title: 'Первая тема Б1', sessions: [b1Lesson]}],
  }];
  const store = make({
    guestTransport: {
      async catalog() { return {sessions: [lesson, b1Lesson], access_outline: accessOutline}; },
      async session(id) {
        return {...await guestTransport.session(), access_outline: accessOutline,
          snapshot: id === b1Lesson.id ? b1Snapshot : snapshot};
      },
    },
  });
  await store.changeLevel(null, 'B1');
  const b1Path = await store.start(null);
  assert.equal(store.state.home.candidate.level, 'B1');

  await store.changeLevel(null, 'PRE_A1');
  assert.equal(store.state.home.active_run, null);
  const beginnerPath = await store.start(null);
  assert.notEqual(beginnerPath, b1Path);
  assert.equal(store.state.home.candidate.level, 'PRE_A1');

  await store.changeLevel(null, 'B1');
  assert.notEqual(await store.start(null), b1Path);
  assert.equal(Object.keys(store.state.guest.runs).length, 2);
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
  await store.loadHistory(7);
  assert.equal(todayCalls,1);
  assert.equal(historyCalls,1);
  assert.equal(store.state.history.current_page,1);
});


test('selected routes replace late responses and a second reader reuses the same view', async () => {
  const old = deferred();
  let calls = 0;
  const store = make({transport: {
    today({unit}) {
      calls++;
      return unit === 'old' ? old.promise : Promise.resolve({...home(), route:{unit:{id:unit}}});
    },
  }});
  const first = store.load(7, 1, {selection:{unit:'old'}});
  const options = {selection:{unit:'new'}};
  const current = store.load(7, 1, options);
  assert.equal(store.load(7, 1, options), current);
  await current;
  old.resolve({...home(), route:{unit:{id:'old'}}});
  assert.equal(await first, null);
  const cached = store.cachedHome(7, options.selection);
  assert.equal(cached.route.unit.id, 'new');
  assert.equal((await store.load(7, 1, options)).home, cached);
  assert.equal(store.cachedHome(7, {unit:'old'}), null);
  assert.equal(store.cachedHome(8, options.selection), null);
  assert.equal(calls, 2);
});

test('account start uses the loaded profile and invalidates the view after acceptance', async () => {
  let queued;
  let profileCalls = 0;
  let todayCalls = 0;
  const repository = {
    async pending() { return queued ? [queued] : []; },
    async enqueue(owner, run, action, logical, request) {
      queued = {local_id:1, action, run_id:run, client_event_id:logical.client_event_id, request};
      return queued;
    },
    async seal() { return queued; },
    async mark() { queued = undefined; },
  };
  const store = make({accountRepository:repository, transport:{
    async today() { todayCalls++; return home(); },
    async profile() { profileCalls++; return home().profile; },
    async call(action, run, request) {
      assert.equal(request.expected_owner_user_id, 7);
      assert.equal(request.expected_revision, 1);
      return {accepted_event_id:request.client_event_id, state:{owner_user_id:7, run_id:'accepted'}};
    },
  }});
  await store.load(7);
  assert.equal(await store.start(7), '/daily/run/accepted');
  assert.equal(profileCalls, 0);
  assert.equal(store.state.ready, false);
  await store.load(7);
  assert.equal(todayCalls, 2);
});

test('invalidation discards an in-flight view and clears cached history', async () => {
  const old = deferred();
  let calls = 0;
  const store = make({transport:{
    today() { return ++calls === 1 ? old.promise : Promise.resolve(home(7,'B1')); },
    async history() { return {current_page:1,data:[{id:'history'}]}; },
  }});
  await store.loadHistory(7);
  const pending = store.load(7);
  store.invalidate(7);
  await store.load(7);
  old.resolve(home());
  assert.equal(await pending, null);
  assert.equal(store.state.home.profile.current_level, 'B1');
  assert.equal(store.state.history, null);
});

test('guest direct topic separates lower-level access from completion and omits unrelated lesson lists', async () => {
  const repository = new Repository();
  repository.state.current_level = 'B1';
  const store = make({guestRepository:repository});
  const selected = await store.load(null, 1, {selection:{unit:'first'}});
  assert.equal(selected.home.route.level.id, 'PRE_A1');
  assert.equal(selected.home.route.lessons[0].status, 'available');
  assert.equal(selected.home.route.lessons[0].review_only, true);
  assert.equal(selected.home.candidate, null);
  assert.equal(selected.home.route.contents[0].units[0].session_count, 1);
  assert.equal(selected.home.route.topics[0].lessons[0].id, snapshot.id);
  assert.equal(selected.home.route.topics[0].lessons[0].status, 'available');
  assert.equal(Object.hasOwn(selected.home.route.contents[0].units[0], 'sessions'), false);
  const empty = await store.load(null, 1, {selection:{level:'B1'}});
  assert.equal(empty.home.route.level.status, 'unavailable');
  assert.deepEqual(empty.home.route.lessons, []);
});

test('cached route is refreshed after midnight in the profile timezone', async () => {
  let instant = '2026-10-26T20:59:00.000Z';
  let loads = 0;
  const store = make({
    now: () => instant,
    transport: {async today() {
      const result = home();
      result.profile.timezone = 'Europe/Moscow';
      result.local_date = ++loads === 1 ? '2026-10-26' : '2026-10-27';
      return result;
    }},
  });
  assert.equal((await store.load(7)).home.local_date, '2026-10-26');
  await store.load(7);
  assert.equal(loads, 1);
  instant = '2026-10-26T21:01:00.000Z';
  assert.equal((await store.load(7)).home.local_date, '2026-10-27');
  assert.equal(loads, 2);
});

test('guest search distinguishes lower-level access from completed lessons', async () => {
  const repository = new Repository();
  const lockedLesson = {...lesson, id:'locked-lesson', title:'Вторая тема'};
  const catalog = {
    sessions: [lesson, lockedLesson],
    access_outline: [{...outline[0], units:[{...outline[0].units[0], sessions:[lesson, lockedLesson]}]}, outline[1]],
  };
  const store = make({guestRepository:repository, guestTransport:{...guestTransport, catalog:async () => catalog}});
  await store.load(null);
  assert.equal(store.searchGuest('ВТОРАЯ', false).items[0].status, 'locked');
  repository.state.current_level = 'B1';
  await store.refresh(null);
  assert.equal(store.searchGuest('ВТОРАЯ', false).items[0].status, 'available');
  assert.deepEqual(store.searchGuest('ВТОРАЯ', true).items, []);
});

test('a lost route start receipt keeps the selected lesson and event key when retried', async () => {
  let queued;
  const requests = [];
  const repository = {
    async pending() { return queued ? [queued] : []; },
    async enqueue(owner, run, action, logical, request) {
      queued = {local_id:1, action, run_id:run, client_event_id:logical.client_event_id, request};
      return queued;
    },
    async seal() { return queued; },
    async mark() { queued = undefined; },
  };
  const store = make({accountRepository:repository, transport:{
    async today() { return home(); },
    async call(action, run, request) {
      requests.push(request);
      if (requests.length === 1) throw new Error('lost acknowledgement');
      return {accepted_event_id:request.client_event_id, state:{owner_user_id:7, run_id:'accepted'}};
    },
  }});
  const first = store.start(7, 'selected-lesson');
  assert.equal(first, store.start(7, 'selected-lesson'));
  await assert.rejects(first);
  assert.equal(await store.start(7, 'selected-lesson'), '/daily/run/accepted');
  assert.equal(requests[0].route_start, true);
  assert.equal(requests[0].session_id, 'selected-lesson');
  assert.deepEqual(requests[0], requests[1]);
});

test('guest route starts and restarts each selected available lesson separately', async () => {
  const second = {...lesson, id: 'PRE_A1-SECOND'};
  const accessOutline = [
    {...outline[0], units: [{...outline[0].units[0], sessions: [lesson, second]}]},
    {id: 'A1', title: 'A1', published: false, units: []},
  ];
  const loads = [];
  const store = make({guestTransport: {
    async catalog() { return {sessions: [lesson, second], access_outline: accessOutline}; },
    async session(id) {
      loads.push(id);
      return {...await guestTransport.session(), snapshot: {...snapshot, id}, access_outline: accessOutline};
    },
  }});
  await store.changeLevel(null, 'A1');
  await store.refresh(null, 1, {selection: {level: 'PRE_A1'}});
  const firstPath = await store.start(null, lesson.id);
  assert.equal(store.state.home.route.lessons[1].status, 'available');
  const secondPath = await store.start(null, second.id);
  assert.notEqual(secondPath, firstPath);
  assert.notEqual(await store.start(null, lesson.id), firstPath);
  assert.notEqual(await store.start(null, second.id), secondPath);
  assert.deepEqual(loads, [lesson.id, second.id, lesson.id, second.id]);
  assert.equal(Object.keys(store.state.guest.runs).length, 2);
});

test('starting a filtered review persists plain word IDs from the reactive plan', async () => {
  let queued;
  const store = make({
    accountRepository: {
      async pending() { return queued ? [queued] : []; },
      async enqueue(owner, run, action, logical, request) {
        queued = {local_id:1, action, run_id:run, client_event_id:logical.client_event_id, request:structuredClone(request)};
        return queued;
      },
      async seal() { return queued; },
      async mark() { queued = undefined; },
    },
    transport: {
      async profile() { return home().profile; },
      async call(action, run, input) {
        assert.equal(input.review_mode, 'manual');
        assert.deepEqual(input.word_ids, [27, 31]);
        return {accepted_event_id:input.client_event_id, state:{owner_user_id:7, run_id:'review'}};
      },
    },
  });
  assert.equal(await store.startReview(7, {wordIds:reactive([27, 31])}), '/daily/run/review');
});
