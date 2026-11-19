import { usableReviewItems, reviewCategory, selectShortReview, shortReviewOption, estimateReviewSeconds } from './shortReview.js';
import { initialPresentation, evaluateEvent, taskSnapshot, reviewSnapshot } from './answerChecker.js';

const copy = structuredClone;
export const VOCABULARY_SUCCESS_WINDOW_MS = 86400000;
export const REVIEW_INTERVALS = [1,3,7,14,30];
const afterDays = (at,days) => new Date(Date.parse(at)+days*86400000).toISOString();
export const guestWordSource = (word) => {
  const sources = [...(word.sources ?? [])].sort((a,b)=> b.first_added_at.localeCompare(a.first_added_at)
    || a.session_id.localeCompare(b.session_id) || a.version-b.version);
  return sources.find(source=>reviewItems(source.snapshot).length)?.snapshot ?? (reviewItems(word.snapshot).length ? word.snapshot : sources[0]?.snapshot ?? word.snapshot);
};
export const reviewItems = usableReviewItems;
export const guestWordData = (word) => {
  const snapshot = copy(guestWordSource(word));
  delete snapshot.review_items;
  return {...copy(word),snapshot,self_check:reviewItems(guestWordSource(word)).length===0};
};
export const applyGuestVocabularyEvent = (state,run,event,result,{enforceWindow=true}={}) => {
  state.vocabulary_effects ??= {};
  state.vocabulary_run_effects ??= {};
  const effects = new Map();
  for (const entry of result.assessed_lexemes ?? []) {
    if (!effects.has(entry.lexeme_key) || entry.effect === 'error') effects.set(entry.lexeme_key,entry.effect);
  }
  for (const [key,kind] of effects) {
    const word = state.vocabulary[key];
    const effectId = event.client_event_id+'|'+key;
    if (!word || state.vocabulary_effects[effectId]) continue;
    const at = event.client_occurred_at;
    const runKey = run.id+'|'+key;
    let successApplied = false;
    if (kind === 'error') Object.assign(word,{needs_review:true,errors:word.errors+1,streak:0,review_step:0,next_review_at:at,last_reviewed_at:at});
    else if (kind === 'success' && !state.vocabulary_run_effects[runKey] && Number.isFinite(Date.parse(at))
      && (!enforceWindow || !word.last_independent_success_at || Date.parse(at)-Date.parse(word.last_independent_success_at)>=VOCABULARY_SUCCESS_WINDOW_MS)) {
      const step = Math.min(REVIEW_INTERVALS.length,word.review_step+1);
      const streak = Math.min(3,word.streak+1);
      Object.assign(word,{streak,review_step:step,needs_review:streak>=3 ? false : word.needs_review,last_reviewed_at:at,next_review_at:afterDays(at,REVIEW_INTERVALS[step-1])});
      if (!word.last_independent_success_at || Date.parse(at)>Date.parse(word.last_independent_success_at)) word.last_independent_success_at=at;
      state.vocabulary_run_effects[runKey] = true;
      successApplied = true;
    }
    state.vocabulary_effects[effectId] = {event_id:event.client_event_id,lexeme_key:key,kind,success_applied:successApplied,applied_at:at};
  }
};
export const finishGuestVocabulary = (state,run,at,options) => {
  let added=0; let existing=0;
  if (run.session_version_id !== null) for (const snapshot of run.snapshot.vocabulary ?? []) {
    const key = snapshot.lexeme_key;
    if (!state.vocabulary[key]) {
      state.vocabulary[key] = {id:key,lexeme_key:key,snapshot:copy(snapshot),needs_review:false,streak:0,errors:0,review_step:0,
        first_added_at:at,next_review_at:afterDays(at,1),last_reviewed_at:null,last_independent_success_at:null,sources:[]};
      added++;
    } else existing++;
    const word = state.vocabulary[key];
    if (!word.sources.some((source)=>source.session_version_id === run.session_version_id)) word.sources.push({
      session_version_id:run.session_version_id,session_id:run.snapshot.id,version:run.snapshot.version ?? 1,snapshot:copy(snapshot),first_added_at:at,
    });
  }
  for (const item of run.vocabulary_events ?? []) applyGuestVocabularyEvent(state,run,item.event,item.result,options);
  return {words_added:added,words_existing:existing};
};
export const upgradeGuestVocabulary = (state) => {
  state.vocabulary_effects ??= {};
  state.vocabulary_run_effects ??= {};
  const presentations = {};
  for (const run of Object.values(state.runs)) {
    run.vocabulary_events = [];
    presentations[run.id] = run.presentations.map((item)=>initialPresentation(item.presentation_id));
  }
  for (const event of [...state.outbox].sort((a,b)=>a.sequence-b.sequence)) {
    const run = state.runs[event.run_id];
    if (!run) continue;
    if (['answer','help'].includes(event.action)) {
      const index = run.presentations.findIndex((item)=>item.presentation_id===event.presentation_id);
      if (index<0) continue;
      const payload = Object.fromEntries(['event_type','presentation_id','answer','help_kind','reason'].filter((key)=>Object.hasOwn(event,key)).map((key)=>[key,event[key]]));
      const next = evaluateEvent(taskSnapshot(run.snapshot,run.snapshot.tasks[index].id),presentations[run.id][index],payload,run.checking_policy_version);
      presentations[run.id][index] = next;
      run.vocabulary_events.push({event:copy(event),result:copy(next.last_result)});
      applyGuestVocabularyEvent(state,run,event,next.last_result,{enforceWindow:false});
    } else if (event.action === 'finish') {
      const counts = finishGuestVocabulary(state,run,event.client_occurred_at,{enforceWindow:false});
      if (run.result) Object.assign(run.result,counts,{integrations_pending:['objectives']});
    }
  }
  return state;
};
// Preserve old grades, counters, schedules and effect decisions; infer only the missing clock.
export const upgradeGuestSuccessClock = state => {
  const times = new Map((state.outbox ?? []).map(event=>[event.client_event_id,event.client_occurred_at]));
  for (const run of Object.values(state.runs ?? {})) for (const item of run.vocabulary_events ?? []) {
    times.set(item.event.client_event_id,item.event.client_occurred_at);
  }
  const latest = new Map();
  for (const effect of Object.values(state.vocabulary_effects ?? {})) {
    if (!effect.success_applied) continue;
    const at=effect.applied_at ?? times.get(effect.event_id);
    const previous=latest.get(effect.lexeme_key);
    if (Number.isFinite(Date.parse(at)) && (!previous || Date.parse(at)>Date.parse(previous))) latest.set(effect.lexeme_key,at);
  }
  for (const word of Object.values(state.vocabulary ?? {})) {
    if (!Object.hasOwn(word,'last_independent_success_at')) word.last_independent_success_at=latest.get(word.lexeme_key) ?? null;
  }
  return state;
};

export const filterGuestWords = (state,{filter='all',source_session='',q=''}={},instant=new Date().toISOString()) => Object.values(state.vocabulary)
  .filter((word)=>filter!=='difficult' || word.is_difficult === true)
  .filter((word)=>filter!=='errors' || word.needs_review)
  .filter((word)=>filter!=='due' || Date.parse(word.next_review_at) <= Date.parse(instant))
  .filter((word)=>!source_session || word.sources.some((source)=>source.session_id===source_session))
  .filter((word)=>!q || (guestWordSource(word).fr+' '+(guestWordSource(word).ru ?? []).join(' ')).toLocaleLowerCase().includes(q.toLocaleLowerCase()))
  .sort((a,b)=>Number(b.needs_review)-Number(a.needs_review) || a.next_review_at.localeCompare(b.next_review_at) || a.lexeme_key.localeCompare(b.lexeme_key));
export const selectGuestBlock = (words,limit=10,errorsOnly=false) => {
  const sorted = [...words].sort((a,b)=>Number(b.needs_review)-Number(a.needs_review) || a.next_review_at.localeCompare(b.next_review_at) || a.lexeme_key.localeCompare(b.lexeme_key));
  const errors = sorted.filter((word)=>word.needs_review);
  const other = sorted.filter((word)=>!word.needs_review);
  const half = Math.floor(limit/2);
  if (!errorsOnly && sorted.length>2 && sorted.length>=limit) {
    const selected = errors.slice(0,Math.max(half,limit-other.length));
    return [...selected,...other.slice(0,limit-selected.length)];
  }
  return sorted.slice(0,limit);
};
export const guestTestPlan = (state,filters={}) => {
  const words = filterGuestWords(state,filters);
  const self_check_words = words.filter((word)=>!reviewItems(guestWordSource(word)).length).map(guestWordData);
  let remaining = words.filter((word)=>reviewItems(guestWordSource(word)).length);
  const word_ids = [];
  while (remaining.length) {
    const block = selectGuestBlock(remaining,10,filters.filter==='errors');
    word_ids.push(...block.map((word)=>word.lexeme_key));
    remaining = remaining.filter((word)=>!block.includes(word));
  }
  return {word_ids,self_check_words,total:word_ids.length};
};
const localDate = (instant,timezone) => {const p=Object.fromEntries(new Intl.DateTimeFormat('en',{timeZone:timezone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date(instant)).map(part=>[part.type,part.value]));return `${p.year}-${p.month}-${p.day}`;};
export const guestShortReviewPlan = (state,instant,mode='manual',filters={}) => {
  const reviews=Object.values(state.runs).filter(run=>run.kind==='review').sort((a,b)=>b.record_sequence-a.record_sequence);
  const previousKeys=[];const previousKinds={};let done=false;let finished=false;
  for(const [index,run] of reviews.entries()){
    const items=run.snapshot.review_items ?? [];
    for(const item of items){const key=item.review_lexeme_key;if(index===0)previousKeys.push(key);
      previousKinds[key] ??= reviewCategory(item.task,run.snapshot.vocabulary.find(word=>word.lexeme_key===key) ?? {});}
    if(run.status==='completed' && items.length>0 && items.length<=3 && run.finished_at && localDate(run.finished_at,state.timezone)===localDate(instant,state.timezone)
      && run.result?.all_steps_processed) {
      finished = true;
      done ||= run.result.independent_correct_steps === items.length;
    }
  }
  const active=Object.values(state.runs).find(run=>run.status==='in_progress');const rows=[];let selfCheck=0;
  const words = mode === 'manual' ? filterGuestWords(state, filters, instant) : Object.values(state.vocabulary);
  for(const word of words){
    if(!reviewItems(guestWordSource(word)).length){selfCheck++;continue;}
    const due=Date.parse(word.next_review_at)<=Date.parse(instant);if(mode==='suggested' && !word.needs_review && !due)continue;
    const source=[...(word.sources ?? [])].sort((a,b)=>Date.parse(b.first_added_at)-Date.parse(a.first_added_at))[0];
    rows.push({key:word.lexeme_key,id:word.lexeme_key,tier:word.needs_review ? 0 : due ? 1 : 2,due:Date.parse(word.next_review_at)/1000,
      topic:(source?.session_id ?? word.lexeme_key).replace(/-L[0-9]+$/u,''),previous_kind:previousKinds[word.lexeme_key] ?? null});
  }
  const settings=state.review_suggestions;
  const blocked=!!active || (mode==='suggested' && (!settings.enabled || (settings.snoozed_until && Date.parse(settings.snoozed_until)>Date.parse(instant)) || finished));
  const selection=blocked ? [] : selectShortReview(rows,mode==='manual' ? previousKeys : []);
  const estimate = selection.reduce((seconds, row, index) =>
    seconds + estimateReviewSeconds(guestWordSource(state.vocabulary[row.id]), index, row.previous_kind), 0);
  return {mode,total:selection.length,estimated_seconds:estimate,word_ids:selection.map(row=>row.id),selection,
    self_check_count:selfCheck,active_run_id:active?.id ?? null,completed_today:done,finished_today:finished,
    includes_not_due:selection.some(row=>row.tier===2)};
};
export const guestShortReviewSource = (state,instant,seed,idFactory,mode='manual',filters={}) => {
  const plan=guestShortReviewPlan(state,instant,mode,filters);
  if (plan.active_run_id) throw Object.assign(new Error('Сначала завершите или отложите активное занятие.'), {code:'validation_failed'});
  if (!plan.total) throw Object.assign(new Error(plan.self_check_count
    ? 'Доступны примеры и самопроверка в словаре; проверяемых вопросов сейчас нет.'
    : 'Сейчас нет подходящего короткого повторения.'), {code:'validation_failed'});
  return guestTestSource(state,plan.word_ids,seed,idFactory,'review',plan);
};

export const guestTestSource = (state,keys,seed,idFactory,kind='vocabulary_test',shortPlan=null) => {
  const words = keys.map((key)=>state.vocabulary[key]);
  if (!words.length || words.some((word)=>!word)) throw new Error('Выбранные выражения недоступны.');
  const usable = words.filter((word)=>reviewItems(guestWordSource(word)).length);
  if (!usable.length) throw new Error('Для этих слов доступна самопроверка без зачёта.');
  const vocabulary = []; const items = [];
  const previous = new Map();
  if (!shortPlan) {
    const runs = Object.values(state.runs).filter(run => ['review', 'vocabulary_test'].includes(run.kind))
      .sort((a, b) => b.record_sequence - a.record_sequence);
    for (const run of runs) for (const item of run.snapshot.review_items ?? []) {
      if (!previous.has(item.review_lexeme_key)) previous.set(item.review_lexeme_key, item.task);
    }
  }
  for (const [index,word] of usable.slice(0,kind==='review'?3:10).entries()) {
    const source = copy(guestWordSource(word));
    const previousItem = previous.get(word.lexeme_key);
    const previousKind = previousItem ? reviewCategory(previousItem, source) : shortPlan?.selection.find(row=>row.key===word.lexeme_key)?.previous_kind;
    items.push(reviewSnapshot(shortReviewOption(source,seed,word.lexeme_key,index,previousKind,previousItem?.id),word.lexeme_key));
    vocabulary.push({...source,id:'word-'+word.lexeme_key,lexeme_key:word.lexeme_key});
  }
  return {session_version_id:null,checking_policy_version:1,snapshot:{id:idFactory(),level:state.current_level,title:kind==='review'?'Повторение выражений':'Тест личного словаря',
    description:shortPlan?.includes_not_due ? 'Повторение изученного: срок части слов ещё не наступил.' : 'Набор выражений закреплён при старте.',review_mode:shortPlan?.mode ?? null,duration_minutes:shortPlan ? Math.ceil(shortPlan.estimated_seconds / 60) : 3,prerequisite_ids:[],introductions:[],seed,
    vocabulary,tasks:items.map((item)=>item.task),review_items:items,remaining_word_ids:usable.slice(10).map((word)=>word.lexeme_key),
    self_check_words:words.filter((word)=>!reviewItems(guestWordSource(word)).length).map(guestWordData)}};
};
