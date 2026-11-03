import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { presentationOrder } from '../../resources/js/App/Learning/presentationOrder.js';
import { createPreviewAdapter } from '../../resources/js/App/Learning/playerAdapter.js';
import { checkAnswer } from '../../resources/js/App/Learning/answerChecker.js';
const pack = JSON.parse(readFileSync(new URL('../../resources/learning/programme/packages/PRE_A1/PRE_A1-U01.v2.json', import.meta.url)));
const correct = t => t.correct_choice_id ? { choice_id: t.correct_choice_id } : t.type === 'word_order'
  ? { token_ids: t.accepted_orders[0] } : { pairs: t.correct_pairs.map(({left_id,right_id}) => ({left_id,right_id})) };

test('display permutations preserve IDs and input, survive reload, and vary between attempts', () => {
  const items = Object.freeze(['a','b','c','d'].map(id => Object.freeze({id,text:id})));
  assert.deepEqual(presentationOrder(items, 'run:step'), presentationOrder(items, 'run:step'));
  const orders = new Set();
  for (let i=0;i<40;i++) {
    const shuffled = presentationOrder(items, 'run-'+i);
    assert.deepEqual(shuffled.map(v=>v.id).sort(), ['a','b','c','d']);
    orders.add(shuffled.map(v=>v.id).join());
  }
  assert.ok(orders.size > 10);
  assert.deepEqual(items.map(v=>v.id), ['a','b','c','d']);
  assert.deepEqual(presentationOrder([], 'empty'), []);
});

test('new material precedes its first assessment; final lesson recalls only known vocabulary', () => {
  const learned = new Set();
  for (const s of pack.sessions) {
    const introduced = new Set(learned);
    for (const t of s.tasks) {
      for (const intro of s.introductions.filter(i=>i.before_task_id===t.id)) {
        assert.ok(intro.vocabulary_ids.length<=3);
        intro.vocabulary_ids.forEach(id=>introduced.add(id));
      }
      for (const id of t.vocabulary_ids) assert.ok(introduced.has(id), s.id+'/'+t.id+' used before introduction: '+id);
      assert.notEqual(t.type, 'short_answer');
      assert.notEqual(t.input_mode, 'text');
      if (t.tokens) assert.notDeepEqual(t.tokens.map(i=>i.id), t.accepted_orders[0]);
    }
    if (s.order===5) for (const w of s.vocabulary) assert.ok(learned.has(w.id), w.id);
    s.vocabulary.forEach(w=>learned.add(w.id));
  }
});

for (const session of pack.sessions) test('complete revised preview without text input: '+session.id, async () => {
  let sequence = 0;
  const adapter = createPreviewAdapter({loadSession:async()=>session,idFactory:()=>String(++sequence)});
  let view = await adapter.start();
  for (const task of session.tasks) {
    if(view.introductions.length) view=await adapter.load({introductionSeen:view.presentation.presentation_id});
    const answer=correct(task);
    assert.ok(!Object.hasOwn(answer,'value'));
    view=await adapter.answer({client_event_id:String(++sequence),presentation_id:view.presentation.presentation_id,answer});
    assert.equal(view.presentation.first_result.correct,true);
    if(view.position<view.total) view=await adapter.load({advanceFrom:view.presentation.presentation_id});
  }
  view=await adapter.finish();
  assert.equal(view.summary.independent_correct_steps,session.tasks.length);
  for(const w of session.vocabulary) for(const review of w.review_items) {
    assert.equal(review.type,'single_choice');
    assert.equal(checkAnswer(review,{choice_id:review.correct_choice_id},1).correct,true);
    const wrong=review.choices.find(c=>c.id!==review.correct_choice_id);
    assert.equal(checkAnswer(review,{choice_id:wrong.id},1).correct,false);
  }
});
