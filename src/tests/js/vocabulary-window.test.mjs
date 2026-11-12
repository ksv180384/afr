import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { newGuestState, migrateGuestState, addGuestRun, applyGuestOperation } from '../../resources/js/App/Learning/guestProgress.js';
import { applyGuestVocabularyEvent, guestTestSource } from '../../resources/js/App/Learning/guestVocabulary.js';

const fixture=JSON.parse(readFileSync(new URL('../Fixtures/Learning/vocabulary-window-parity.json',import.meta.url)));
const key='fr:bonjour:greeting';
const stateFor=()=>{const state=newGuestState({idFactory:randomUUID,now:fixture.initial_at,timezone:'Europe/Paris'});state.vocabulary[key]={lexeme_key:key,needs_review:true,streak:0,errors:0,review_step:0,next_review_at:fixture.initial_at,last_independent_success_at:null};return state;};
const apply=(state,id,run,at,kind)=>applyGuestVocabularyEvent(state,{id:run},{client_event_id:id,client_occurred_at:at},{assessed_lexemes:[{lexeme_key:key,effect:kind}]});

test('PHP and JS share 19 exact elapsed-time decisions across types DST errors and repeated delivery',()=>{
  let state=stateFor();
  for(const [index,event] of fixture.events.entries()){
    apply(state,'event-'+index,event.run,event.at,event.kind);apply(state,'event-'+index,event.run,event.at,event.kind);
    const word=state.vocabulary[key];for(const field of ['streak','review_step','needs_review','errors','last_independent_success_at'])assert.equal(word[field],event[field],field+' case '+index);
    assert.equal(Date.parse(word.next_review_at),Date.parse(event.next_review_at));assert.equal(state.vocabulary_effects['event-'+index+'|'+key].success_applied,event.success_applied);
    state=migrateGuestState(JSON.parse(JSON.stringify(state)));
  }
  assert.equal(Object.keys(state.vocabulary_effects).length,19);
});

test('v5 upgrade infers a success clock only from credited effects and preserves same-day legacy mastery',()=>{
  const old=stateFor();old.storage_schema_version=5;const word=old.vocabulary[key];delete word.last_independent_success_at;
  Object.assign(word,{review_step:3,streak:3,needs_review:false,last_reviewed_at:'2026-11-06T13:00:00Z',next_review_at:'2026-11-13T12:10:00Z'});
  old.vocabulary_effects={['old-a|'+key]:{event_id:'old-a',lexeme_key:key,success_applied:true},['old-b|'+key]:{event_id:'old-b',lexeme_key:key,success_applied:true},['help|'+key]:{event_id:'help',lexeme_key:key,success_applied:false}};
  old.runs={legacy:{vocabulary_events:[{event:{client_event_id:'old-a',client_occurred_at:'2026-11-06T12:00:00Z'}},{event:{client_event_id:'old-b',client_occurred_at:'2026-11-06T12:10:00Z'}},{event:{client_event_id:'help',client_occurred_at:'2026-11-06T13:00:00Z'}}]}};
  const result=migrateGuestState(old);assert.equal(result.storage_schema_version,6);assert.equal(result.vocabulary[key].last_independent_success_at,'2026-11-06T12:10:00Z');
  for(const field of ['review_step','streak','needs_review','last_reviewed_at','next_review_at'])assert.equal(result.vocabulary[key][field],word[field]);
  assert.deepEqual(result.runs,old.runs);assert.deepEqual(result.outbox,old.outbox);assert.deepEqual(result.vocabulary_effects,old.vocabulary_effects);assert.deepEqual(migrateGuestState(result),result);
  apply(result,'new','new-run','2026-11-06T13:10:00Z','success');assert.equal(result.vocabulary[key].review_step,3);
});

test('unknown historical success stays unknown instead of using an error view as a success',()=>{
  const old=stateFor();old.storage_schema_version=5;delete old.vocabulary[key].last_independent_success_at;
  old.vocabulary[key].last_reviewed_at='2026-11-06T12:00:00Z';old.vocabulary[key].review_step=4;old.vocabulary[key].streak=3;
  const state=migrateGuestState(old);assert.equal(state.vocabulary[key].last_independent_success_at,null);
  apply(state,'new','new-run','2026-11-06T12:01:00Z','success');assert.equal(state.vocabulary[key].review_step,5);
});

test('backwards clocks and helped or corrected answers cannot move the independent clock',()=>{
  const state=stateFor();apply(state,'one','run-a','2026-11-06T12:00:00Z','success');
  apply(state,'backwards','run-b','2026-11-05T12:00:00Z','success');apply(state,'help','run-c','2026-11-08T12:00:00Z','none');
  assert.equal(state.vocabulary[key].last_independent_success_at,'2026-11-06T12:00:00Z');assert.equal(state.vocabulary[key].review_step,1);
});


test('two tab leases on separate review runs give feedback without awarding a second interval',()=>{
  const state=stateFor();const session=JSON.parse(readFileSync(new URL('../../resources/practice/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
  state.vocabulary[key].snapshot=session.vocabulary.find(word=>word.lexeme_key===key);state.vocabulary[key].sources=[];
  const runs=[];
  for(const tab of ['tab-a','tab-b']){
    const source=guestTestSource(state,[key],randomUUID(),randomUUID);const runId=randomUUID();
    addGuestRun(state,{source,runId,presentationIds:source.snapshot.tasks.map(()=>randomUUID()),eventId:randomUUID(),tabId:tab,now:fixture.initial_at,kind:'vocabulary_test'});
    runs.push({runId,tab,source});
  }
  for(const [index,{runId,tab,source}] of runs.entries()){
    const run=state.runs[runId];const instant=new Date(Date.parse(fixture.initial_at)+index*600000).toISOString();
    const input={client_event_id:randomUUID(),expected_revision:1,presentation_id:run.presentations[0].presentation_id,event_type:'answer',answer:{choice_id:source.snapshot.tasks[0].correct_choice_id},client_occurred_at:instant};
    const result=applyGuestOperation(state,runId,tab,'answer',input);assert.equal(result.presentations[0].first_result.score,1);assert.equal(state.vocabulary[key].review_step,1);
    const replay=applyGuestOperation(state,runId,tab,'answer',input);assert.deepEqual(replay,result);
  }
  assert.equal(state.vocabulary[key].streak,1);
});
