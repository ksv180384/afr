import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { selectShortReview, reviewCategory } from '../../resources/js/App/Learning/shortReview.js';
import { guestShortReviewPlan, guestShortReviewSource } from '../../resources/js/App/Learning/guestVocabulary.js';
import { newGuestState, addGuestRun, applyGuestOperation, migrateGuestState } from '../../resources/js/App/Learning/guestProgress.js';
const now='2026-11-06T12:00:00Z';const original=JSON.parse(readFileSync(new URL('../../resources/practice/templates/pre-a1-u01-examples.json',import.meta.url))).sessions[0].vocabulary[0];
const stateFor=(n=0)=>{const state=newGuestState({idFactory:randomUUID,now,timezone:'Europe/Moscow'});state.current_level='B1';
  for(let i=0;i<n;i++){const snapshot=structuredClone(original);const key='fr:short:'+i;snapshot.lexeme_key=key;const context=structuredClone(snapshot.review_items[0]);context.id='misleading-meaning-'+i;context.prompt={text:'Bonjour, je suis Marie.',text_lang:'fr',transcription_ru:'бо(н)жур, жё сюи мари'};snapshot.review_items.push(context);
    state.vocabulary[key]={id:key,lexeme_key:key,snapshot,sources:[],needs_review:false,streak:0,errors:0,review_step:0,next_review_at:'2026-11-06T11:00:00Z'};}return state;};
const start=(state,mode='manual',instant=now)=>{const source=guestShortReviewSource(state,instant,randomUUID(),randomUUID,mode);const runId=randomUUID();addGuestRun(state,{source,runId,presentationIds:source.snapshot.tasks.map(()=>randomUUID()),eventId:randomUUID(),tabId:'tab',now:instant,kind:'review'});return state.runs[runId];};
const finish=(state,run,instant=now)=>{const act=(action,extra={})=>applyGuestOperation(state,run.id,'tab',action,{client_event_id:randomUUID(),expected_revision:run.revision,client_occurred_at:instant,...extra});
  for(let i=0;i<run.snapshot.tasks.length;i++){act('answer',{presentation_id:run.presentations[i].presentation_id,event_type:'answer',answer:{choice_id:run.snapshot.tasks[i].correct_choice_id}});if(i<run.snapshot.tasks.length-1)act('advance',{presentation_id:run.presentations[i].presentation_id});}act('finish');};
for(const count of [0,1,2,3,9])test(`short guest count ${count} uses only personal questions`,()=>{
  const state=stateFor(count);const plan=guestShortReviewPlan(state,now,'suggested');assert.equal(plan.total,Math.min(count,3));
  if(count){const run=start(state,'suggested');assert.equal(run.snapshot.tasks.length,plan.total);}else assert.throws(()=>start(state,'suggested'),/нет подходящего/);
});
test('PHP and guest selection share priorities topic diversity rotation and content classification',()=>{
  const fixture=JSON.parse(readFileSync(new URL('../Fixtures/Learning/short-review-parity.json',import.meta.url)));for(const row of fixture.cases)assert.deepEqual(selectShortReview(row.rows,row.previous).map(x=>x.key),row.expected);
  const source=stateFor(1).vocabulary['fr:short:0'].snapshot;assert.equal(reviewCategory(source.review_items[0],source),'meaning');assert.equal(reviewCategory(source.review_items[1],source),'context');
});
test('missing questions are filtered before counting and future material is manual only',()=>{
  const state=stateFor(3);state.vocabulary['fr:short:0'].snapshot.review_items=[];state.vocabulary['fr:short:1'].next_review_at='2027-01-01T00:00:00Z';
  assert.equal(guestShortReviewPlan(state,now,'suggested').total,1);const manual=guestShortReviewPlan(state,now);assert.equal(manual.total,2);assert.equal(manual.self_check_count,1);assert.equal(manual.includes_not_due,true);
});
test('success suppresses the offer today while next manual block changes words and frozen questions survive reload',()=>{
  let state=stateFor(6);let run=start(state,'suggested');const keys=run.snapshot.vocabulary.map(word=>word.lexeme_key);const snapshot=structuredClone(run.snapshot);
  state.vocabulary[keys[0]].snapshot.review_items=[];state=migrateGuestState(JSON.parse(JSON.stringify(state)));run=state.runs[run.id];assert.deepEqual(run.snapshot,snapshot);finish(state,run);
  assert.equal(guestShortReviewPlan(state,now,'suggested').total,0);assert.equal(guestShortReviewPlan(state,now,'suggested').completed_today,true);
  const next=start(state);assert.equal(next.snapshot.vocabulary.some(word=>keys.includes(word.lexeme_key)),false);
});
test('active work is never paused implicitly and unavailable settings only suppress suggested work',()=>{
  const state=stateFor(3);state.review_suggestions.enabled=false;assert.equal(guestShortReviewPlan(state,now,'suggested').total,0);assert.equal(guestShortReviewPlan(state,now).total,3);
  const active=start(state);const before=structuredClone(active);assert.throws(()=>start(state),/активное/);assert.deepEqual(state.runs[active.id],before);
});
test('the local completion date controls suppression after a block crossing midnight',()=>{
  const state=stateFor(1);const run=start(state,'suggested','2026-11-06T20:59:00Z');finish(state,run,'2026-11-06T21:01:00Z');
  const extra=stateFor(2).vocabulary['fr:short:1'];state.vocabulary[extra.lexeme_key]=extra;
  assert.equal(guestShortReviewPlan(state,'2026-11-06T21:02:00Z','suggested').completed_today,true);assert.equal(guestShortReviewPlan(state,'2026-11-07T21:02:00Z','suggested').completed_today,false);
});


test('a one-word manual block alternates context and meaning without ID conventions',()=>{
  const state=stateFor(1);const word=state.vocabulary['fr:short:0'];const first=start(state);assert.equal(reviewCategory(first.snapshot.tasks[0],word.snapshot),'context');finish(state,first);
  const next=start(state);assert.equal(reviewCategory(next.snapshot.tasks[0],word.snapshot),'meaning');assert.notEqual(next.snapshot.tasks[0].id,first.snapshot.tasks[0].id);
});
test('a helped completion suppresses the offer but is not independently successful',()=>{
  const state=stateFor(1);const run=start(state);applyGuestOperation(state,run.id,'tab','help',{client_event_id:randomUUID(),expected_revision:run.revision,client_occurred_at:now,presentation_id:run.presentations[0].presentation_id,event_type:'help',help_kind:'reference'});
  finish(state,run);assert.equal(guestShortReviewPlan(state,now,'suggested').completed_today,false);assert.equal(guestShortReviewPlan(state,now,'suggested').finished_today,true);assert.equal(guestShortReviewPlan(state,now,'suggested').total,0);assert.equal(guestShortReviewPlan(state,now).total,1);
});

test('a manual plan applies the dictionary filters and budgets for longer audio', () => {
  const state = stateFor(2);
  const word = state.vocabulary['fr:short:0'];
  word.needs_review = true;
  word.snapshot.review_items[1].prompt.audio = {text:'Bonjour '.repeat(90), lang:'fr-FR'};
  const selected = guestShortReviewPlan(state, now, 'manual', {filter:'errors'});
  assert.deepEqual(selected.word_ids, [word.lexeme_key]);
  assert.ok(selected.estimated_seconds >= 150);
  const source = guestShortReviewSource(state, now, randomUUID(), randomUUID, 'manual', {filter:'errors'});
  assert.equal(source.snapshot.duration_minutes, Math.ceil(selected.estimated_seconds / 60));
  state.vocabulary['fr:short:1'].next_review_at = '2027-01-01T00:00:00Z';
  assert.deepEqual(guestShortReviewPlan(state, now, 'manual', {filter:'due'}).word_ids, [word.lexeme_key]);
});
