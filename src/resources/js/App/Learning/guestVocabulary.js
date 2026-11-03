import { initialPresentation, evaluateEvent, taskSnapshot, reviewSnapshot } from './answerChecker.js';

const copy = structuredClone;
export const REVIEW_INTERVALS = [1,3,7,14,30];
const afterDays = (at,days) => new Date(Date.parse(at)+days*86400000).toISOString();
export const guestWordSource = (word) => {
  const sources = [...(word.sources ?? [])].sort((a,b)=> b.first_added_at.localeCompare(a.first_added_at)
    || a.session_id.localeCompare(b.session_id) || a.version-b.version);
  return (sources.find((source)=>reviewItems(source.snapshot).length) ?? sources[0])?.snapshot ?? word.snapshot;
};
export const reviewItems = (source) => (source.review_items ?? []).filter((item)=>['single_choice','short_answer'].includes(item.type));
export const guestWordData = (word) => {
  const snapshot = copy(guestWordSource(word));
  delete snapshot.review_items;
  return {...copy(word),snapshot,self_check:reviewItems(guestWordSource(word)).length===0};
};
export const applyGuestVocabularyEvent = (state,run,event,result) => {
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
    else if (kind === 'success' && !state.vocabulary_run_effects[runKey]) {
      const step = Math.min(REVIEW_INTERVALS.length,word.review_step+1);
      const streak = Math.min(3,word.streak+1);
      Object.assign(word,{streak,review_step:step,needs_review:streak>=3 ? false : word.needs_review,last_reviewed_at:at,next_review_at:afterDays(at,REVIEW_INTERVALS[step-1])});
      state.vocabulary_run_effects[runKey] = true;
      successApplied = true;
    }
    state.vocabulary_effects[effectId] = {event_id:event.client_event_id,lexeme_key:key,kind,success_applied:successApplied};
  }
};
export const finishGuestVocabulary = (state,run,at) => {
  let added=0; let existing=0;
  if (run.session_version_id !== null) for (const snapshot of run.snapshot.vocabulary ?? []) {
    const key = snapshot.lexeme_key;
    if (!state.vocabulary[key]) {
      state.vocabulary[key] = {id:key,lexeme_key:key,snapshot:copy(snapshot),needs_review:false,streak:0,errors:0,review_step:0,
        first_added_at:at,next_review_at:afterDays(at,1),last_reviewed_at:null,sources:[]};
      added++;
    } else existing++;
    const word = state.vocabulary[key];
    if (!word.sources.some((source)=>source.session_version_id === run.session_version_id)) word.sources.push({
      session_version_id:run.session_version_id,session_id:run.snapshot.id,version:run.snapshot.version ?? 1,snapshot:copy(snapshot),first_added_at:at,
    });
  }
  for (const item of run.vocabulary_events ?? []) applyGuestVocabularyEvent(state,run,item.event,item.result);
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
      applyGuestVocabularyEvent(state,run,event,next.last_result);
    } else if (event.action === 'finish') {
      const counts = finishGuestVocabulary(state,run,event.client_occurred_at);
      if (run.result) Object.assign(run.result,counts,{integrations_pending:['objectives']});
    }
  }
  return state;
};
export const filterGuestWords = (state,{filter='all',source_session='',q=''}={}) => Object.values(state.vocabulary)
  .filter((word)=>filter!=='errors' || word.needs_review)
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
export const guestTestSource = (state,keys,seed,idFactory,kind='vocabulary_test') => {
  const words = keys.map((key)=>state.vocabulary[key]);
  if (!words.length || words.some((word)=>!word)) throw new Error('Выбранные выражения недоступны.');
  const usable = words.filter((word)=>reviewItems(guestWordSource(word)).length);
  if (!usable.length) throw new Error('Для этих слов доступна самопроверка без зачёта.');
  const vocabulary = []; const items = [];
  for (const word of usable.slice(0,10)) {
    const source = copy(guestWordSource(word));
    const options = reviewItems(source);
    const value = [...new TextEncoder().encode(seed+word.lexeme_key)].reduce((sum,byte)=>sum+byte,0);
    items.push(reviewSnapshot(options[value%options.length],word.lexeme_key));
    vocabulary.push({...source,id:'word-'+word.lexeme_key,lexeme_key:word.lexeme_key});
  }
  return {session_version_id:null,checking_policy_version:1,snapshot:{id:idFactory(),level:state.current_level,title:kind==='review'?'Повторение выражений':'Тест личного словаря',
    description:'Набор выражений закреплён при старте.',duration_minutes:3,prerequisite_ids:[],introductions:[],seed,
    vocabulary,tasks:items.map((item)=>item.task),review_items:items,remaining_word_ids:usable.slice(10).map((word)=>word.lexeme_key),
    self_check_words:words.filter((word)=>!reviewItems(guestWordSource(word)).length).map(guestWordData)}};
};
