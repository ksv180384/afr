import test from 'node:test';
import assert from 'node:assert/strict';
import { createAccountAdapter, deliverAccountQueue, startAccountRun } from '../../resources/js/App/Learning/accountAdapter.js';
import { logicalKey } from '../../resources/js/App/Learning/accountRepository.js';
const clone = (value) => structuredClone(value);
const error = (status, code = 'unavailable') => Object.assign(new Error('failure'), {response:{status,data:{code,message:'failure'}}});

class MemoryRepository {
  rows = [];
  drafts = new Map();
  failWrites = false;
  async pending(owner,run) { return clone(this.rows.filter(r => r.owner_user_id === owner && (run === undefined || r.run_id === run) && !['acked','dismissed'].includes(r.status)).sort((a,b)=>(a.queue_order ?? a.local_id)-(b.queue_order ?? b.local_id))); }
  async enqueue(owner,run,action,logical,request,prediction) {
    if(this.failWrites) throw new Error('quota');
    const old = this.rows.find(r => r.client_event_id === logical.client_event_id);
    if(old) {
      assert.equal(old.logical_hash,logicalKey(logical));
      assert.equal(old.owner_user_id,owner);
      return clone(old);
    }
    const row = {local_id:this.rows.length+1,owner_user_id:owner,run_id:run,action,client_event_id:logical.client_event_id,logical_hash:logicalKey(logical),request:clone(request),prediction:clone(prediction),status:'queued'};
    this.rows.push(row); return clone(row);
  }
  async seal(owner,id,revision) {
    if(this.failWrites) throw new Error('quota');
    const row=this.rows.find(r=>r.local_id===id && r.owner_user_id===owner);
    if(row.status==='queued') { if(revision!==undefined) row.request.expected_revision=revision; row.status='sent'; }
    return clone(row);
  }
  async mark(owner,id,status,receipt) {
    if(this.failWrites) throw new Error('quota');
    const row=this.rows.find(r=>r.local_id===id && r.owner_user_id===owner); row.status=status;
    if(receipt) row.acknowledgement={run_id:receipt.state.run_id,revision:receipt.state.revision};
    return clone(row);
  }
  async saveDraft(owner,run,presentation,answer) { if(this.failWrites) throw new Error('quota'); this.drafts.set(owner+':'+run+':'+presentation,clone(answer)); }
  async replaceRejected(owner,id,request) {
    if(this.failWrites) throw new Error('quota');
    const previous=this.rows.find(row=>row.local_id===id && row.owner_user_id===owner);
    const row={...clone(previous),local_id:this.rows.length+1,status:'queued',client_event_id:request.client_event_id,logical_hash:logicalKey(request),request:clone(request),queue_order:previous.queue_order ?? previous.local_id};
    previous.status='dismissed'; this.rows.push(row); return clone(row);
  }
  async draft(owner,run,presentation) { return clone(this.drafts.get(owner+':'+run+':'+presentation) ?? null); }
}

const setup = () => {
  const repository=new MemoryRepository();
  let state={owner_user_id:1,run_id:'r1',revision:1,finished:false,started:true,presentation:{presentation_id:'p1',first_result:null},assistance:[]};
  let signedIn=1;
  let failBefore=null;
  let loseAfter=false;
  let effects=0;
  const calls=[];
  const ledger=new Map();
  const statuses=[];
  const transport={
    profile:async()=>({owner_user_id:signedIn}),
    loadRun:async()=>clone(state),
    call:async(action,run,request,options)=>{
      calls.push({action,run,request:clone(request)});
      if (run && options?.headers?.['X-Practice-Owner'] !== String(signedIn)) throw error(403,'forbidden');
      if(failBefore) throw failBefore;
      const existing=ledger.get(request.client_event_id);
      if(existing) { assert.equal(existing.hash,logicalKey({action,run,request})); return clone(existing.receipt); }
      if(action!=='start' && request.expected_revision!==state.revision) throw Object.assign(error(409,'revision_conflict'),{response:{status:409,data:{code:'revision_conflict',message:'Changed',state:clone(state)}}});
      effects++;
      state.revision++;
      if(action==='help') state.assistance.push(request.help_kind);
      if(action==='answer') state.presentation.first_result={score:state.assistance.length?0:1};
      if(action==='finish') state.finished=true;
      const receipt={accepted_event_id:request.client_event_id,state:clone(state)};
      ledger.set(request.client_event_id,{hash:logicalKey({action,run,request}),receipt:clone(receipt)});
      if(loseAfter) { loseAfter=false; throw new Error('timeout after commit'); }
      return receipt;
    },
  };
  let serial=0;
  const options={ownerUserId:1,runId:'r1',repository,transport,onStatus:s=>statuses.push(s),idFactory:()=> 'command-'+ ++serial};
  return {repository,transport,calls,statuses,options,adapter:createAccountAdapter(options),
    state:()=>state,effects:()=>effects,fail:e=>{failBefore=e;},lose:()=>{loseAfter=true;},signIn:id=>{signedIn=id;},advanceServer:()=>{state.revision++;}};
};

const playbackRun = () => {
  const steps = ['p1','p2'].map(presentation_id => ({
    presentation_id, review_lexeme_key:null,
    task: {id:'task-'+presentation_id,type:'single_choice',choices:[{id:'a'},{id:'b'}],correct_choice_id:'b',
      hint:'Подсказка',feedback:{correct:'Верно',incorrect:'Неверно'}},
  }));
  const {correct_choice_id, hint, ...task} = steps[0].task;
  return { owner_user_id:1, run_id:'r1', revision:1, checking_policy_version:1, kind:'daily', status:'in_progress', started:true, finished:false,
    position:1, total:2, task:{...task,has_hint:true}, assistance:[], vocabulary:[], introductions:[], introduction_seen:false,
    presentation:{presentation_id:'p1',first_result:null,last_result:null,status:'pending',help_used:false},
    corrections:null, mistakes_count:0, mistake_positions:[],
    summary:{processed_steps:0,score_numerator:0,independent_correct_steps:0,technical_skips:0,all_steps_processed:false},
    playback:{steps,introductions:[]} };
};
const playbackInput = {client_event_id:'playback-answer',presentation_id:'p1',answer:{choice_id:'b'}};

test('shows the grade and next task before the first request returns and sends in order without loading the profile', {timeout:2000}, async () => {
  const {playback,...initial} = playbackRun();
  const repository = new MemoryRepository();
  let release;
  const slowResponse = new Promise(resolve => { release = resolve; });
  const calls = [];
  let profileRequests = 0;
  const result = {correct:true,event_type:'answer'};
  const graded = {...initial,revision:2,presentation:{...initial.presentation,first_result:result,last_result:result},
    summary:{...initial.summary,processed_steps:1,score_numerator:1}};
  const nextTask = {...graded,revision:3,position:2,presentation:{...initial.presentation,presentation_id:'p2'}};
  const paused = {...nextTask,revision:4,status:'paused',started:false,resume:true};
  let sequence = 0;
  const adapter = createAccountAdapter({ownerUserId:1,runId:'r1',repository,idFactory:() => 'command-'+ ++sequence,transport:{
    profile:async () => { profileRequests++; return {owner_user_id:1}; },
    loadRun:async (_id, options) => { assert.equal(options.playback,true); return {...initial,playback}; },
    call:async (action,_id,request,options) => {
      assert.equal(options.headers['X-Practice-Owner'],'1');
      calls.push([action,request.expected_revision]);
      if (action === 'answer') await slowResponse;
      return {accepted_event_id:request.client_event_id,state:{answer:graded,advance:nextTask,pause:paused}[action]};
    },
  }});
  await adapter.load();
  const grade = await adapter.answer(playbackInput);
  assert.equal(grade.presentation.first_result.correct,true);
  const next = await adapter.load({advanceFrom:'p1'});
  assert.equal(next.task.id,'task-p2');
  assert.equal(next.task.correct_choice_id,undefined);
  assert.equal(next.task.hint,undefined);
  await adapter.saveDraft({presentation_id:'p2',answer:{choice_id:'a'}});
  assert.deepEqual(calls,[['answer',1]]);
  assert.equal((await repository.pending(1,'r1')).length,3);
  release();
  await adapter.sync();
  assert.deepEqual(calls,[['answer',1],['advance',2],['pause',3]]);
  assert.equal(profileRequests,0);
  assert.deepEqual(await repository.pending(1,'r1'),[]);
});

test('retains the background queue on disconnect and blocks dependent events on a revision conflict', {timeout:2000}, async () => {
  const initial = playbackRun();
  const repository = new MemoryRepository();
  let failure = new Error('connection lost');
  const statuses = [];
  const calls = [];
  const adapter = createAccountAdapter({ownerUserId:1,runId:'r1',repository,idFactory:() => 'next',onStatus:s => statuses.push(s.kind),transport:{
    profile:async () => ({owner_user_id:1}),
    loadRun:async () => clone(initial),
    call:async (_action,_id,request) => { calls.push(request); throw failure; },
  }});
  await adapter.load();
  await adapter.answer(playbackInput);
  await assert.rejects(adapter.sync());
  assert.equal(statuses.at(-1),'warning');
  assert.equal((await adapter.load({advanceFrom:'p1'})).position,2);
  failure = error(409,'revision_conflict');
  await assert.rejects(adapter.sync(), failure => failure.response?.data?.code === 'playback_conflict');
  assert.equal(statuses.at(-1),'conflict');
  assert.deepEqual(calls,[calls[0],calls[0]]);
  assert.deepEqual((await repository.pending(1,'r1')).map(row => row.status),['conflict','conflict']);
  assert.equal((await adapter.acceptServerState()).position,1);
  assert.deepEqual(repository.rows.map(row => row.status),['dismissed','dismissed']);
});

test('plays correction rounds locally and waits for server confirmation at finish', {timeout:2000}, async () => {
  const initial = playbackRun();
  const repository = new MemoryRepository();
  let release;
  const blocked = new Promise(resolve => { release = resolve; });
  const calls = [];
  let sequence = 0;
  const nextId = () => 'command-'+ ++sequence;
  const adapter = createAccountAdapter({ownerUserId:1,runId:'r1',repository,idFactory:nextId,transport:{
    profile:async () => ({owner_user_id:1}),
    loadRun:async () => clone(initial),
    call:async action => { calls.push(action); await blocked; throw new Error('connection lost'); },
  }});
  const answer = (presentation_id,choice_id) => adapter.answer({client_event_id:nextId(),presentation_id,answer:{choice_id}});
  const advance = presentation_id => adapter.load({advanceFrom:presentation_id});
  await adapter.load();
  await answer('p1','a');
  await advance('p1');
  await answer('p2','a');
  assert.equal((await adapter.reviewMistakes()).corrections.total,2);
  await advance('p1');
  await answer('p1','a');
  await advance('p1');
  assert.equal((await answer('p2','b')).corrections.can_finish,false);
  const round = await advance('p2');
  assert.equal(round.corrections.round,2);
  assert.deepEqual(round.corrections.positions,[1]);
  assert.equal(round.corrections.fixed_last_round,1);
  await advance('p1');
  const final = await answer('p1','b');
  assert.equal(final.corrections.can_finish,true);
  assert.equal(final.summary.score_numerator,0);
  const finish = adapter.finish();
  assert.deepEqual(calls,['answer']);
  release();
  await assert.rejects(finish);
  assert.equal((await repository.pending(1,'r1')).at(-1).action,'answer');
});

test('timeout after answer commit retries identical sealed bytes and only then displays the grade',async()=>{
  const s=setup(); await s.adapter.load();
  const input={client_event_id:'answer-1',presentation_id:'p1',answer:{choice_id:'b'}};
  s.lose();
  await assert.rejects(s.adapter.answer(input));
  assert.equal(s.repository.rows[0].status,'sent');
  assert.equal(s.statuses.at(-1).kind,'pending');
  const view=await s.adapter.answer(input);
  assert.equal(view.presentation.first_result.score,1);
  assert.equal(s.effects(),1);
  assert.deepEqual(s.calls[0].request,s.calls[1].request);
  assert.equal(s.repository.rows[0].status,'acked');
});

for(const operation of ['finish','start','advance','introduction']) {
  test('stable lifecycle event ID across retry: '+operation,async()=>{
    const s=setup(); await s.adapter.load();
    const perform=()=>operation==='advance'?s.adapter.load({advanceFrom:'p1'}):operation==='introduction'?s.adapter.load({introductionSeen:'p1'}):s.adapter[operation]();
    s.lose(); await assert.rejects(perform());
    await perform();
    assert.equal(s.effects(),1);
    assert.deepEqual(s.calls[0].request,s.calls[1].request);
  });
}

test('reload reads server first and reconciles a lost finish acknowledgement',async()=>{
  const s=setup(); await s.adapter.load(); s.lose(); await assert.rejects(s.adapter.finish());
  const restored=createAccountAdapter(s.options);
  assert.equal((await restored.load()).finished,true);
  assert.equal(s.effects(),1);
  assert.deepEqual(s.calls[0].request,s.calls[1].request);
});

test('help then answer are serialized and only unsent revision is rebased',async()=>{
  const s=setup();
  const help={client_event_id:'h1',presentation_id:'p1',event_type:'help',help_kind:'audio_transcript'};
  const answer={client_event_id:'a1',presentation_id:'p1',event_type:'answer',answer:{choice_id:'b'}};
  await s.repository.enqueue(1,'r1','help',help,{...help,expected_revision:1});
  await s.repository.enqueue(1,'r1','answer',answer,{...answer,expected_revision:1});
  const view=await s.adapter.load();
  assert.deepEqual(s.calls.map(c=>c.action),['help','answer']);
  assert.deepEqual(s.calls.map(c=>c.request.expected_revision),[1,2]);
  assert.equal(view.presentation.first_result.score,0);
});

test('a rejected stale answer recovers automatically with a new ID while the original sent bytes remain unchanged',async()=>{
  const s=setup(); await s.adapter.load();
  const input={client_event_id:'a1',presentation_id:'p1',answer:{choice_id:'a'}};
  s.fail(new Error('offline'));
  await assert.rejects(s.adapter.answer(input));
  const bytes=logicalKey(s.repository.rows[0].request);
  s.advanceServer(); s.fail(null);
  const view=await s.adapter.answer(input);
  assert.equal(view.presentation.first_result.score,1);
  assert.equal(s.statuses.at(-1).kind,'idle');
  assert.equal(s.repository.rows[0].status,'dismissed');
  assert.equal(logicalKey(s.repository.rows[0].request),bytes);
  assert.notEqual(s.calls.at(-1).request.client_event_id,input.client_event_id);
  assert.equal(s.effects(),1);
  assert.equal((await s.repository.pending(1,'r1')).length,0);
});

test('a legacy conflict first retries the exact request and recovers its existing acknowledgement',async()=>{
  const s=setup(); await s.adapter.load(); s.lose();
  await assert.rejects(s.adapter.answer({client_event_id:'lost-answer',presentation_id:'p1',answer:{choice_id:'b'}}));
  await s.repository.mark(1,1,'conflict');
  const view=await createAccountAdapter(s.options).load();
  assert.equal(view.presentation.first_result.score,1);
  assert.deepEqual(s.calls[0].request,s.calls[1].request);
  assert.equal(s.effects(),1);
});

test('a stale pending start uses the current profile revision without a recovery button or duplicate run',async()=>{
  const s=setup(); let serial=0;
  s.transport.profile=async()=>({owner_user_id:1,revision:4,current_level:'A1'});
  const original=s.transport.call;
  s.transport.call=async(action,run,request)=>{
    if (request.expected_revision!==4) throw Object.assign(error(409,'revision_conflict'),{response:{status:409,data:{code:'revision_conflict',state:{profile:{owner_user_id:1},run:null}}}});
    return original(action,run,request);
  };
  const input={client_event_id:'old-start',kind:'daily',expected_revision:1};
  await s.repository.enqueue(1,null,'start',input,input);
  await s.repository.mark(1,1,'conflict');
  const state=await deliverAccountQueue({...s.options,runId:null,idFactory:()=> 'recovered-'+ ++serial});
  assert.equal(state.run_id,'r1');
  assert.equal(s.effects(),1);
  assert.equal(s.repository.rows[0].status,'dismissed');
  assert.equal(s.repository.rows[0].request.expected_revision,1);
  assert.equal(s.repository.rows[1].request.expected_revision,4);
  assert.equal((await s.repository.pending(1,null)).length,0);
});

test('a stale daily start continues the already selected current run',async()=>{
  const s=setup();
  s.transport.profile=async()=>({owner_user_id:1,revision:4,current_level:'A1'});
  s.transport.call=async()=>{throw Object.assign(error(409,'revision_conflict'),{response:{status:409,data:{code:'revision_conflict',state:{profile:{owner_user_id:1},run:{id:'r1',status:'in_progress',level:'A1'}}}}});};
  const input={client_event_id:'old-start',kind:'daily',expected_revision:1};
  await s.repository.enqueue(1,null,'start',input,input);
  assert.equal((await deliverAccountQueue({...s.options,runId:null})).run_id,'r1');
  assert.equal(s.effects(),0);
  assert.equal((await s.repository.pending(1,null)).length,0);
});

test('recovery never replaces a grade already recorded in another tab',async()=>{
  const s=setup(); await s.adapter.load();
  const input={client_event_id:'old-answer',presentation_id:'p1',answer:{choice_id:'b'}};
  s.fail(new Error('offline')); await assert.rejects(s.adapter.answer(input));
  s.state().presentation.first_result={score:0}; s.advanceServer(); s.fail(null);
  const restored=await s.adapter.load();
  assert.equal(restored.presentation.first_result.score,0);
  assert.equal(s.effects(),0);
  assert.equal(s.repository.rows[0].status,'dismissed');
  assert.deepEqual(s.repository.rows[0].request.answer,input.answer);
});

test('a rejected help event stays ahead of its answer after interrupted local recovery',async()=>{
  const s=setup();
  const help={client_event_id:'h1',presentation_id:'p1',event_type:'help',help_kind:'audio_transcript'};
  const answer={client_event_id:'a1',presentation_id:'p1',event_type:'answer',answer:{choice_id:'b'}};
  await s.repository.enqueue(1,'r1','help',help,{...help,expected_revision:1});
  await s.repository.enqueue(1,'r1','answer',answer,{...answer,expected_revision:1});
  await s.repository.seal(1,1); s.advanceServer();
  const replace=s.repository.replaceRejected.bind(s.repository);
  s.repository.replaceRejected=async(...args)=>{await replace(...args);throw new Error('interrupted after local commit');};
  await assert.rejects(s.adapter.load());
  s.repository.replaceRejected=replace;
  const restored=await createAccountAdapter(s.options).load();
  assert.equal(restored.presentation.first_result.score,0);
  assert.deepEqual(s.calls.slice(1).map(call=>call.action),['help','answer']);
  assert.deepEqual(s.calls.slice(1).map(call=>call.request.expected_revision),[2,3]);
});

test('simultaneous recovery calls share a single delivery',async()=>{
  const s=setup();
  const input={client_event_id:'start-1',kind:'daily',expected_revision:1};
  await s.repository.enqueue(1,null,'start',input,input);
  const first=deliverAccountQueue({...s.options,runId:null});
  const second=deliverAccountQueue({...s.options,runId:null});
  assert.equal(first,second);
  await Promise.all([first,second]);
  assert.equal(s.effects(),1); assert.equal(s.calls.length,1);
});

for(const status of [401,419]) {
  test('session failure '+status+' retains queue and retries original ID after recovery',async()=>{
    const s=setup(); await s.adapter.load();
    const input={client_event_id:'a1',presentation_id:'p1',answer:{choice_id:'b'}};
    s.fail(error(status)); await assert.rejects(s.adapter.answer(input));
    assert.equal(s.statuses.at(-1).kind,'auth');
    s.fail(null); await s.adapter.answer(input);
    assert.deepEqual(s.calls[0].request,s.calls[1].request);
  });
}

test('the server rejects a queued answer after an account change and the original owner can retry it',async()=>{
  const s=setup(); await s.adapter.load();
  const input={client_event_id:'a1',presentation_id:'p1',answer:{choice_id:'b'}};
  s.signIn(2); await assert.rejects(s.adapter.answer(input));
  assert.equal(s.effects(),0);
  assert.equal(s.statuses.at(-1).kind,'auth');
  assert.equal((await s.repository.pending(1,'r1')).length,1);
  assert.equal((await s.repository.pending(2,'r1')).length,0);
  s.signIn(1); await s.adapter.answer(input);
  assert.equal(s.effects(),1);
  assert.deepEqual(s.calls[0].request,s.calls[1].request);
});

test('quota failure never claims device persistence or transmits an event',async()=>{
  const s=setup(); await s.adapter.load(); s.repository.failWrites=true;
  await assert.rejects(s.adapter.answer({client_event_id:'a1',presentation_id:'p1',answer:{value:'merci'}}),/Не удалось сохранить/);
  assert.equal(s.statuses.at(-1).kind,'storage');
  assert.equal(s.calls.length,0);
  assert.equal(s.repository.rows.length,0);
});

test('draft and original answer survive reload and a later caller mutation',async()=>{
  const s=setup(); await s.adapter.load();
  await s.adapter.storeDraft({presentation_id:'p1',answer:{value:'merci'}});
  assert.deepEqual((await createAccountAdapter(s.options).load()).local_draft,{value:'merci'});
  s.fail(new Error('offline'));
  const input={client_event_id:'a1',presentation_id:'p1',answer:{value:'merci'}};
  await assert.rejects(s.adapter.answer(input)); input.answer.value='changed';
  assert.equal(s.repository.rows[0].request.answer.value,'merci');
  await assert.rejects(s.adapter.answer({client_event_id:'new-id',presentation_id:'p1',answer:{value:'changed'}}));
  assert.equal(s.repository.rows.length,1);
});

test('leaving after a network failure preserves the durable draft and pending event',async()=>{
  const s=setup(); await s.adapter.load(); s.fail(new Error('offline'));
  await s.adapter.saveDraft({presentation_id:'p1',answer:{value:'bonjour'}});
  assert.equal((await s.repository.pending(1,'r1'))[0].action,'pause');
  assert.deepEqual(await s.repository.draft(1,'r1','p1'),{value:'bonjour'});
});

test('start recovery uses the exact original request from the owner queue',async()=>{
  const s=setup();
  const input={client_event_id:'start-1',kind:'daily',expected_revision:1};
  s.lose(); await assert.rejects(startAccountRun({...s.options,input}));
  await deliverAccountQueue({...s.options,runId:null});
  assert.equal(s.effects(),1);
  assert.deepEqual(s.calls[0].request,s.calls[1].request);
});

test('the original UI action also recovers when the connection drops again after a conflict was replaced',async()=>{
  const s=setup(); await s.adapter.load();
  const input={client_event_id:'answer-original',presentation_id:'p1',answer:{choice_id:'b'}};
  s.fail(new Error('offline')); await assert.rejects(s.adapter.answer(input));
  s.fail(null); s.advanceServer();
  const original=s.transport.call;
  let interrupted=false;
  s.transport.call=async(action,run,request,options)=>{
    if(request.client_event_id !== input.client_event_id && !interrupted) {interrupted=true;throw new Error('offline again');}
    return original(action,run,request,options);
  };
  await assert.rejects(s.adapter.answer(input));
  const restored=await s.adapter.answer(input);
  assert.equal(restored.presentation.first_result.score,1);
  assert.equal(s.effects(),1);
  assert.equal((await s.repository.pending(1,'r1')).length,0);
});

test('a rejected prerequisite start clears the saved start and preserves the server explanation', async () => {
  const s = setup();
  const message = 'Сначала завершите предыдущее занятие «Повторение» (PRE_A1-U01-L05).';
  s.transport.call = async () => { throw Object.assign(error(422, 'validation_failed'), {response:{status:422,data:{code:'validation_failed',message}}}); };
  const input = {client_event_id:'blocked-start',kind:'extra',session_id:'PRE_A1-U02-L01',expected_revision:1};
  await assert.rejects(startAccountRun({...s.options,input}), {message});
  assert.equal((await s.repository.pending(1,null)).length, 0);
  assert.equal(s.statuses.at(-1).kind, 'error');
  assert.equal(s.statuses.at(-1).message, message);
});

test('starting a lesson checks the profile once before posting the run', async () => {
  const s = setup(); let profiles = 0;
  s.transport.profile = async () => { profiles++; return {owner_user_id:1,revision:1}; };
  const input = {client_event_id:'one-profile-start',kind:'extra',session_id:'PRE_A1-U02-L01',expected_revision:1};
  const view = await startAccountRun({...s.options,input});
  assert.equal(view.run_id,'r1');
  assert.equal(profiles,1);
  assert.equal(s.calls.length,1);
});

test('a just-fetched profile is reused for a start from the completed lesson', async () => {
  const s = setup(); let profiles = 0;
  s.transport.profile = async () => { profiles++; return {owner_user_id:1,revision:1}; };
  const profile = await s.transport.profile();
  const input = {client_event_id:'repeat-profile-start',kind:'extra',session_id:'PRE_A1-U02-L01',expected_revision:profile.revision};
  await startAccountRun({...s.options,input,profile});
  assert.equal(profiles,1);
  assert.equal(s.calls.length,1);
});

test('a fetched profile belonging to another user still blocks sending the start', async () => {
  const s = setup();
  const input = {client_event_id:'wrong-owner-start',kind:'daily',expected_revision:1};
  await assert.rejects(startAccountRun({...s.options,input,profile:{owner_user_id:2,revision:1}}));
  assert.equal(s.calls.length,0);
  assert.equal((await s.repository.pending(1,null)).length,1);
});
