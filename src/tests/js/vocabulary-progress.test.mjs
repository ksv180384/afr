import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { newGuestState, addGuestRun, applyGuestOperation, migrateGuestState, guestRunView } from '../../resources/js/App/Learning/guestProgress.js';
import { applyGuestVocabularyEvent, guestWordSource, guestTestPlan, guestTestSource, selectGuestBlock } from '../../resources/js/App/Learning/guestVocabulary.js';

const session = JSON.parse(readFileSync(new URL('../../resources/learning/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0];
const parity = JSON.parse(readFileSync(new URL('../Fixtures/Learning/vocabulary-parity.json',import.meta.url)));
const at = '2026-10-25T12:00:00.000Z';
const stateFor = () => newGuestState({idFactory:randomUUID,now:at,timezone:'Europe/Moscow'});
const start = (state,source={session_version_id:1,checking_policy_version:1,snapshot:session},kind='daily') => {
  const runId=randomUUID();
  addGuestRun(state,{source,runId,presentationIds:source.snapshot.tasks.map(()=>randomUUID()),eventId:randomUUID(),tabId:'tab',now:at,kind});
  return state.runs[runId];
};
const act = (state,run,action,extra={}) => {
  const input={client_event_id:randomUUID(),expected_revision:run.revision,client_occurred_at:at,...extra};
  const result=applyGuestOperation(state,run.id,'tab',action,input);
  return result;
};
const end = (state,run) => {
  while(true) {
    const current=run.presentations[run.cursor];
    if (!current.first_result) act(state,run,'help',{presentation_id:current.presentation_id,event_type:'unknown'});
    if(run.cursor===run.presentations.length-1) break;
    act(state,run,'advance',{presentation_id:current.presentation_id});
  }
  return act(state,run,'finish');
};

test('PHP and guest vocabulary transitions share the same parity fixture including run success after a later error', () => {
  const state=stateFor();
  const word={lexeme_key:'fr:bonjour:greeting',needs_review:true,streak:0,errors:0,review_step:0,next_review_at:at};
  state.vocabulary[word.lexeme_key]=word;
  for (const [index,entry] of parity.events.entries()) {
    const result={assessed_lexemes:[{lexeme_key:word.lexeme_key,effect:entry.kind}]};
    const event={client_event_id:'event-'+index,client_occurred_at:at};
    applyGuestVocabularyEvent(state,{id:'run-'+entry.run},event,result);
    applyGuestVocabularyEvent(state,{id:'run-'+entry.run},event,result);
    for(const key of ['streak','errors','review_step','needs_review']) assert.equal(word[key],entry[key],key+' at '+index);
  }
  assert.equal(word.next_review_at,'2026-11-24T12:00:00.000Z');
  assert.equal(Object.keys(state.vocabulary_effects).length,parity.events.length);
});

test('new guest words appear only at finish, preserve transcription and sources, and are not duplicated by another finish', () => {
  const state=stateFor(); const run=start(state);
  act(state,run,'help',{presentation_id:run.presentations[0].presentation_id,event_type:'unknown'});
  assert.equal(Object.keys(state.vocabulary).length,0);
  const result=end(state,run);
  assert.equal(result.result.words_added,6);
  assert.equal(Object.keys(state.vocabulary).length,6);
  const word=state.vocabulary[session.vocabulary[0].lexeme_key];
  assert.ok(word.needs_review);
  assert.ok(word.errors>0);
  assert.equal(word.snapshot.transcription_ru,session.vocabulary[0].transcription_ru);
  assert.equal(word.sources.length,1);
  const again=start(state);
  end(state,again);
  assert.equal(word.sources.length,1);
  assert.equal(Object.keys(state.vocabulary).length,6);
});

test('guest wrong then helped correction then three separate word tests clears the mark without clearing history', () => {
  const state=stateFor(); end(state,start(state));
  const key=session.vocabulary[0].lexeme_key;
  let source=guestTestSource(state,[key],randomUUID(),randomUUID);
  let run=start(state,source,'vocabulary_test');
  assert.deepEqual(guestRunView(run).task.vocabulary_ids,[guestRunView(run).vocabulary[0].id]);
  const id=run.presentations[0].presentation_id;
  act(state,run,'help',{presentation_id:id,event_type:'help',help_kind:'reference'});
  act(state,run,'answer',{presentation_id:id,event_type:'answer',answer:{choice_id:'b'}});
  act(state,run,'answer',{presentation_id:id,event_type:'answer',answer:{choice_id:'a'}});
  assert.equal(state.vocabulary[key].streak,0);
  end(state,run);
  const errors=state.vocabulary[key].errors;
  for(let i=1;i<=3;i++) {
    source=guestTestSource(state,[key],randomUUID(),randomUUID);
    run=start(state,source,'vocabulary_test');
    act(state,run,'answer',{presentation_id:run.presentations[0].presentation_id,event_type:'answer',answer:{choice_id:'a'}});
    end(state,run);
    assert.equal(state.vocabulary[key].streak,i);
    assert.equal(state.vocabulary[key].needs_review,i<3);
  }
  assert.equal(state.vocabulary[key].errors,errors);
});

test('v2 upgrade reconstructs ordered event effects and completed words exactly once without changing grades or IDs', () => {
  const current=stateFor(); const run=start(current); end(current,run);
  const old=structuredClone(current);
  old.storage_schema_version=2; old.vocabulary={}; delete old.vocabulary_effects; delete old.vocabulary_run_effects;
  for (const value of Object.values(old.runs)) { delete value.vocabulary_events; delete value.result.words_added; delete value.result.words_existing; }
  const migrated=migrateGuestState(old);
  assert.equal(migrated.storage_schema_version,4);
  assert.deepEqual(migrated.vocabulary,current.vocabulary);
  assert.equal(migrated.runs[run.id].result.score_numerator,current.runs[run.id].result.score_numerator);
  assert.deepEqual(migrated.outbox,current.outbox);
  assert.deepEqual(migrateGuestState(migrated),migrated);
  assert.deepEqual(old.vocabulary,{});
});

test('mixed test caps errors when sufficient healthy choices exist and small error-only pools remain usable', () => {
  const words=Array.from({length:16},(_,i)=>({id:i,lexeme_key:'key'+i,needs_review:i<10,next_review_at:at}));
  const selected=selectGuestBlock(words);
  assert.equal(selected.filter((word)=>word.needs_review).length,5);
  assert.equal(selected.length,10);
  assert.equal(selectGuestBlock(words,10,true).filter((word)=>word.needs_review).length,10);
  assert.equal(selectGuestBlock(words.slice(0,2)).length,2);
});

test('studied source selection ignores unpublished replacements and snapshots a multi-block set before marks change', () => {
  const state=stateFor(); end(state,start(state));
  const key=session.vocabulary[0].lexeme_key;
  const word=state.vocabulary[key];
  const later={...structuredClone(word.sources[0]),session_version_id:2,version:2,first_added_at:'2026-10-26T12:00:00.000Z'};
  later.snapshot.fr='bonjour source étudiée';
  word.sources.push(later);
  assert.equal(guestWordSource(word).fr,'bonjour source étudiée');
  assert.equal(word.snapshot.fr,'bonjour');
  for(let i=0;i<12;i++) state.vocabulary['fr:copy:'+i]={...structuredClone(word),id:'fr:copy:'+i,lexeme_key:'fr:copy:'+i};
  state.vocabulary['fr:self']={...structuredClone(word),id:'fr:self',lexeme_key:'fr:self',sources:[],snapshot:{fr:'sans modèle',ru:['без шаблона'],transcription_ru:'са(н) модэль'}};
  const plan=guestTestPlan(state);
  assert.equal(plan.self_check_words.length,1);
  const source=guestTestSource(state,plan.word_ids,randomUUID(),randomUUID);
  const run=start(state,source,'vocabulary_test');
  assert.equal(run.presentations.length,10);
  const before=structuredClone(run.snapshot);
  for(const value of Object.values(state.vocabulary)) value.needs_review=false;
  assert.deepEqual(run.snapshot,before);
  assert.ok(guestRunView(run).has_next_test_block);
  assert.equal(new Set(plan.word_ids).size,plan.word_ids.length);
});
