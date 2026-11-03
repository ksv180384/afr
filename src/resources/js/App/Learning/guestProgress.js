import { learningAccess, completedGuestSessions } from './learningAccess.js';
import { initialPresentation, evaluateEvent, calculateSessionResult, taskSnapshot } from './answerChecker.js';
import { checkingPolicy, LEVELS } from './types/index.js';
import { ensureSyncState } from './guestSync.js';
import { logicalKey } from './accountRepository.js';
import { applyGuestVocabularyEvent, finishGuestVocabulary, upgradeGuestVocabulary } from './guestVocabulary.js';

export const GUEST_SCHEMA_VERSION = 4;
export const guestError = (message, code = 'revision_conflict') => Object.assign(new Error(message), {code});
const copy = (value) => structuredClone(value);
export const guestDate = (instant, timezone) => {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en', {timeZone:timezone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date(instant)).map(({type,value})=>[type,value]));
  return [parts.year,parts.month,parts.day].join('-');
};
export const migrateGuestState = (source) => {
  const state = copy(source);
  if (state.storage_schema_version === 1) {
    state.sync_receipts ??= {};
    state.bound_user_id ??= null;
    state.storage_schema_version = 2;
  }
  if (state.storage_schema_version === 2) { upgradeGuestVocabulary(state); state.storage_schema_version = 3; }
  if (state.storage_schema_version === 3) { ensureSyncState(state); state.storage_schema_version = 4; }
  if (state.storage_schema_version !== GUEST_SCHEMA_VERSION) throw guestError('Версия гостевого хранилища не поддерживается. Данные сохранены без изменений.', 'unsupported_schema');
  return state;
};
export const recordGuestEvent = (state, event) => {
  const entry = {...copy(event),guest_id:state.guest_id,sequence:++state.sequence,sync_status:'pending'};
  state.outbox.push(entry);
  return entry;
};
export const newGuestState = ({idFactory, now, timezone}) => {
  new Intl.DateTimeFormat('en', {timeZone:timezone});
  const eventId = idFactory();
  const state = {storage_schema_version:GUEST_SCHEMA_VERSION,guest_id:idFactory(),current_level:'PRE_A1',timezone,
    level_selection_event_id:eventId,profile_revision:1,sequence:0,runs:{},vocabulary:{},outbox:[],sync_receipts:{},bound_user_id:null,receipts:{},vocabulary_effects:{},vocabulary_run_effects:{}};
  ensureSyncState(state);
  recordGuestEvent(state,{client_event_id:eventId,action:'level_selection',current_level:state.current_level,timezone,client_occurred_at:now});
  return state;
};
export const changeGuestProfile = (state, input) => {
  if (state.bound_user_id !== null) throw guestError('Продолжите в связанном аккаунте.', 'bound_owner');
  if (!LEVELS.some(({code}) => code === input.current_level)) throw guestError('Выберите доступный уровень.', 'validation_failed');
  try { new Intl.DateTimeFormat('en', {timeZone:input.timezone}); } catch { throw guestError('Укажите существующий часовой пояс IANA.', 'validation_failed'); }
  if (state.profile_revision !== input.expected_revision) throw guestError('Настройки изменены в другой вкладке. Обновите страницу.');
  state.current_level = input.current_level;
  state.timezone = input.timezone;
  state.level_selection_event_id = input.client_event_id;
  state.profile_revision++;
  recordGuestEvent(state,{...input,action:'level_selection'});
};
export const addGuestRun = (state, {source,runId,presentationIds,eventId,tabId,now,promotionEventId,kind = 'daily'}) => {
  if (state.bound_user_id !== null) throw guestError('Этот гостевой набор уже связан с аккаунтом. Войдите для продолжения.', 'bound_owner');
  checkingPolicy(source.checking_policy_version);
  const snapshot = copy(source.snapshot);
  if (!snapshot.tasks?.length || snapshot.tasks.length !== presentationIds.length) throw guestError('В занятии нет доступных заданий.', 'validation_failed');
  const completed = Object.values(state.runs).filter((run) => run.status === 'completed').map((run) => run.snapshot.id);
  if (['daily','extra'].includes(kind) && source.access_outline) {
    const access = learningAccess(source.access_outline, completedGuestSessions(state), state.current_level).sessions[snapshot.id];
    if (!access || access.locked) throw guestError(access?.reason || 'Занятие пока недоступно.', 'validation_failed');
  } else if (!completed.includes(snapshot.id) && ((kind !== 'extra' && snapshot.level !== state.current_level) || snapshot.prerequisite_ids.some((id) => !completed.includes(id)))) throw guestError('Сначала завершите предыдущие занятия выбранного уровня.', 'validation_failed');
  const entry = source.access_outline?.find(level=>level.id===snapshot.level);
  const completionRoute = entry && !entry.has_curriculum && entry.published && entry.units.length && entry.units.every(unit=>unit.sessions.length)
    ? {level:entry.id,profile_event_id:promotionEventId ?? globalThis.crypto?.randomUUID(),session_ids:entry.units.flatMap(unit=>unit.sessions.map(session=>session.id)),next_level:LEVELS[LEVELS.findIndex(level=>level.code===entry.id)+1]?.code ?? null} : null;
  const run = {id:runId,kind,snapshot,completion_route:completionRoute,session_version_id:source.session_version_id,checking_policy_version:source.checking_policy_version,
    checking_policy_snapshot:copy(checkingPolicy(source.checking_policy_version)),audioUrls:copy(source.audioUrls ?? {}),
    timezone:state.timezone,local_date:guestDate(now,state.timezone),started_at:now,finished_at:null,status:'in_progress',revision:1,
    cursor:0,introductions_seen:[],presentations:presentationIds.map(initialPresentation),assistance:{},drafts:{},lease:tabId,result:null,vocabulary_events:[]};
  const event = recordGuestEvent(state,{client_event_id:eventId,action:'start',run_id:runId,kind,session_version_id:run.session_version_id,
    checking_policy_version:run.checking_policy_version,presentation_ids:presentationIds,timezone:run.timezone,local_date:run.local_date,client_occurred_at:now});
  run.record_sequence = event.sequence;
  state.runs[runId] = run;
  return run;
};
export const guestRunView = (run, {restore = false} = {}) => {
  const snapshot = run.snapshot;
  const presentation = run.presentations[run.cursor];
  const task = copy(snapshot.tasks[run.cursor]);
  const reviewKey = snapshot.review_items?.[run.cursor]?.review_lexeme_key;
  if (reviewKey) task.vocabulary_ids = snapshot.vocabulary.filter((word)=>word.lexeme_key===reviewKey).map((word)=>word.id);
  return copy({has_next_test_block:!!run.snapshot.remaining_word_ids?.length,self_check_words:run.snapshot.self_check_words ?? [],mode:'guest',kind:run.kind,run_id:run.id,revision:run.revision,status:run.status,started:run.status !== 'paused',resume:run.status === 'paused',finished:run.status === 'completed',
    session:{id:snapshot.id,title:snapshot.title,description:snapshot.description,level:snapshot.level,duration_minutes:snapshot.duration_minutes,pronunciation_notes:snapshot.pronunciation_notes ?? []},
    session_version_id:run.session_version_id,checking_policy_version:run.checking_policy_version,timezone:run.timezone,local_date:run.local_date,started_at:run.started_at,finished_at:run.finished_at,
    task,presentation,position:run.cursor+1,total:run.presentations.length,
    introductions:(snapshot.introductions ?? []).filter((intro) => intro.before_task_id === snapshot.tasks[run.cursor].id),
    introduction_seen:run.introductions_seen.includes(presentation.presentation_id),vocabulary:snapshot.vocabulary,
    local_draft:!presentation.first_result ? run.drafts[presentation.presentation_id] ?? null : null,
    assistance:run.assistance[presentation.presentation_id] ?? [],restore_assistance:restore,audioUrls:run.audioUrls,
    summary:run.result ?? calculateSessionResult(run.presentations,run.presentations.length,run.checking_policy_version)});
};
export const applyGuestOperation = (state, runId, tabId, action, input) => {
  const run = state.runs[runId];
  if (!run) throw guestError('Занятие отсутствует в этом браузере.', 'not_found');
  if (state.bound_user_id !== null) throw guestError('Продолжите в связанном аккаунте.', 'bound_owner');
  const fingerprint = logicalKey({runId,action,input});
  const previous = state.receipts[input.client_event_id];
  if (previous) {
    if (previous.fingerprint !== fingerprint) throw guestError('ID события уже использован с другим содержимым.', 'idempotency_conflict');
    return copy(previous.run);
  }
  if (run.lease !== tabId || input.expected_revision !== run.revision) throw guestError('Занятие продолжено в другой вкладке. Загрузите принятую версию.');
  if (run.status === 'completed') throw guestError('Завершённое занятие неизменно.');
  const current = run.presentations[run.cursor];
  if (['answer','help','advance','introduction','draft'].includes(action) && input.presentation_id !== current.presentation_id) throw guestError('Текущий шаг изменился. Обновите занятие.');
  if (action === 'draft') {
    run.drafts[current.presentation_id] = copy(input.answer);
    return copy(run);
  }
  if (action === 'answer' || action === 'help') {
    if (run.status !== 'in_progress') throw guestError('Сначала продолжите занятие.');
    const {client_event_id,expected_revision,client_occurred_at,...event} = input;
    if ((action === 'answer') !== (event.event_type === 'answer')) throw guestError('Некорректный тип события.', 'validation_failed');
    const next = evaluateEvent(run.snapshot.review_items?.[run.cursor] ?? taskSnapshot(run.snapshot,run.snapshot.tasks[run.cursor].id),current,event,run.checking_policy_version);
    if (event.answer) next.answer = copy(event.answer);
    run.presentations[run.cursor] = next;
    run.vocabulary_events ??= [];
    run.vocabulary_events.push({event:copy(input),result:copy(next.last_result)});
    applyGuestVocabularyEvent(state,run,input,next.last_result);
    if (event.event_type === 'help') run.assistance[current.presentation_id] = [...new Set([...(run.assistance[current.presentation_id] ?? []),event.help_kind])];
  } else if (action === 'advance' || action === 'introduction') {
    if (run.status !== 'in_progress') throw guestError('Сначала продолжите занятие.');
    if (action === 'advance') {
      if (!current.first_result || run.cursor >= run.presentations.length-1) throw guestError('Сначала обработайте текущий шаг.', 'validation_failed');
      run.cursor++;
    } else run.introductions_seen = [...new Set([...run.introductions_seen,current.presentation_id])];
  } else if (action === 'pause' || action === 'resume') run.status = action === 'pause' ? 'paused' : 'in_progress';
  else if (action === 'finish') {
    const summary = calculateSessionResult(run.presentations,run.presentations.length,run.checking_policy_version);
    if (!summary.all_steps_processed || run.status !== 'in_progress') throw guestError('Сначала обработайте все шаги.', 'validation_failed');
    run.result = {...summary,...finishGuestVocabulary(state,run,input.client_occurred_at),integrations_pending:['objectives']};
    run.finished_at = input.client_occurred_at;
    run.status = 'completed';
  } else throw guestError('Неизвестная операция.', 'validation_failed');
  run.revision++;
  recordGuestEvent(state,{...input,action,run_id:runId,accepted_revision:run.revision});
  if (action === 'finish' && ['daily','extra'].includes(run.kind) && run.completion_route?.level === state.current_level && run.completion_route.next_level
    && run.completion_route.session_ids.every(id=>completedGuestSessions(state).includes(id))) {
    const oldLevel = state.current_level;
    changeGuestProfile(state,{client_event_id:run.completion_route.profile_event_id,expected_revision:state.profile_revision,
      current_level:run.completion_route.next_level,timezone:state.timezone,client_occurred_at:input.client_occurred_at});
    run.result.promotion = {old_level:oldLevel,new_level:state.current_level,reason:{method:'published_route_completion'}};
  }
  state.receipts[input.client_event_id] = {fingerprint,run:copy(run)};
  return copy(run);
};
