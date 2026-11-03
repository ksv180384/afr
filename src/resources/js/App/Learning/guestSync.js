const clone = structuredClone;
const uuid = () => crypto.randomUUID();
const ownerError = () => Object.assign(new Error('Войдите в исходный аккаунт. Очередь другого владельца не отправлена.'),{code:'owner_changed'});
export const hasGuestWork = (state) => !!state && (Object.keys(state.runs ?? {}).length > 0 || (state.outbox ?? []).filter((event)=>event.action==='level_selection').length>1);
export const ensureSyncState = (state) => {
  state.sync ??= {owner_user_id:null,episode_id:null,batches:[],mapping:{},rejected:[]};
  state.sync_receipts ??= {};
  return state.sync;
};
const runBundle = (state,runId,events) => {
  const run=state.runs[runId];
  if(!run) throw new Error('Снимок гостевого прохождения отсутствует. События оставлены в браузере.');
  return {guest_run_id:run.id,kind:run.kind,session_version_id:run.session_version_id,snapshot:clone(run.snapshot),
    checking_policy_version:run.checking_policy_version,presentation_ids:run.presentations.map((item)=>item.presentation_id),
    timezone:run.timezone,local_date:run.local_date,started_at:run.started_at,events};
};
const eventPayload = (event) => Object.fromEntries(Object.entries(event).filter(([key])=>!['guest_id','sync_status'].includes(key)));
export const freezeGuestBatch = (state,owner,idFactory=uuid) => {
  const sync=ensureSyncState(state);
  if((state.bound_user_id!==null && state.bound_user_id!==owner) || (sync.owner_user_id!==null && sync.owner_user_id!==owner)) throw ownerError();
  const sealed=sync.batches.find((batch)=>batch.status==='pending');
  if(sealed) return clone(sealed);
  const pending=(state.outbox ?? []).filter((event)=>!event.sync_status || event.sync_status==='pending').sort((a,b)=>a.sequence-b.sequence);
  if(!pending.length) return null;
  const runIds=[...new Set(pending.filter((event)=>event.run_id).map((event)=>event.run_id))].slice(0,5);
  const levels=pending.filter((event)=>event.action==='level_selection').slice(0,100).map(eventPayload);
  const runs=runIds.map((id)=>runBundle(state,id,pending.filter((event)=>event.run_id===id).map(eventPayload)));
  const marks=runIds.length ? [] : pending.filter((event)=>event.action==='vocabulary_mark').slice(0,500).map(eventPayload);
  if(!levels.length && !runs.length && !marks.length) throw new Error('Неизвестные события оставлены в браузере для восстановления.');
  const payload={level_selection_event_id:state.level_selection_event_id,guest_id:state.guest_id,episode_id:sync.episode_id,batch_id:idFactory(),level_events:levels,runs,marks};
  const batch={owner_user_id:owner,status:'pending',payload,event_ids:[...levels,...runs.flatMap((run)=>run.events),...marks].map((event)=>event.client_event_id)};
  sync.owner_user_id=owner;
  sync.batches.push(clone(batch));
  return clone(batch);
};
export const acknowledgeGuestBatch = (state,batch,receipt) => {
  const sync=ensureSyncState(state);
  if(receipt.owner_user_id!==batch.owner_user_id || receipt.guest_id!==state.guest_id || receipt.batch_id!==batch.payload.batch_id
    || !Array.isArray(receipt.accepted) || !Array.isArray(receipt.rejected) || receipt.accepted.some((id)=>!batch.event_ids.includes(id))) throw new Error('Сервер не подтвердил исходный пакет. Очередь сохранена.');
  const stored=sync.batches.find((item)=>item.payload.batch_id===receipt.batch_id);
  if(!stored || JSON.stringify(stored.payload)!==JSON.stringify(batch.payload)) throw new Error('Локальный пакет изменился. Данные не очищены.');
  state.bound_user_id=receipt.owner_user_id;
  sync.owner_user_id=receipt.owner_user_id;
  sync.episode_id=receipt.episode_id;
  sync.mapping={...sync.mapping,...clone(receipt.mapping)};
  const rejectedIds=new Set(receipt.rejected.flatMap((entry)=>entry.event_ids ?? []));
  state.outbox=state.outbox.filter((event)=>!receipt.accepted.includes(event.client_event_id)).map((event)=>rejectedIds.has(event.client_event_id) ? {...event,sync_status:'rejected'} : event);
  stored.status='acked';
  const unresolved=new Set(state.outbox.map((event)=>event.client_event_id));
  sync.rejected=[...new Map([...sync.rejected,...clone(receipt.rejected)].filter((entry)=>entry.event_ids?.some((id)=>unresolved.has(id))).map((entry)=>[JSON.stringify(entry),entry])).values()];
  state.sync_receipts[receipt.batch_id]=clone(receipt);
};
export const retryRejectedGuestEvents = (state) => {
  ensureSyncState(state).rejected=[];
  state.outbox=state.outbox.map((event)=>event.sync_status==='rejected' ? {...event,sync_status:'pending'} : event);
};
export const syncGuestRepository = async ({ownerUserId,repository,transport,accountRepository,onStatus=()=>{},getOwner=()=>ownerUserId,idFactory=uuid}) => {
  let state=await repository.read();
  if(!hasGuestWork(state)) return {state,mapping:state?.sync?.mapping ?? {}};
  if((state.bound_user_id!==null && state.bound_user_id!==ownerUserId) || (state.sync?.owner_user_id!=null && state.sync.owner_user_id!==ownerUserId)) throw ownerError();
  for(let round=0;round<1000;round++) {
    if(getOwner()!==ownerUserId) throw ownerError();
    const profile=await transport.profile();
    if(profile.owner_user_id!==ownerUserId) throw ownerError();
    const batch=await repository.mutate((current)=>freezeGuestBatch(current,ownerUserId,idFactory));
    if(!batch) break;
    onStatus({kind:'syncing',message:'Переносим гостевой прогресс в аккаунт…'});
    const receipt=await transport.sync(batch.payload);
    await repository.mutate((current)=> {
      if(current.guest_id!==batch.payload.guest_id) throw ownerError();
      acknowledgeGuestBatch(current,batch,receipt);
    },{bound:true});
    state=await repository.read();
    if(getOwner()!==ownerUserId) throw ownerError();
    if(round===999) throw new Error('Перенос продолжается. Повторите отправку оставшейся очереди.');
  }
  state=await repository.read();
  // Unsubmitted input remains a draft, never an automatically graded answer.
  for(const [guestId,mapping] of Object.entries(state.sync?.mapping ?? {})) {
    const run=state.runs[guestId];
    const presentation=run?.presentations[run.cursor];
    if(presentation && !presentation.first_result && run.drafts[presentation.presentation_id]) {
      await accountRepository.saveDraft(ownerUserId,mapping.server_run_id,mapping.presentation_map[presentation.presentation_id],clone(run.drafts[presentation.presentation_id]));
    }
  }
  const rejected=state.outbox.filter((event)=>event.sync_status==='rejected');
  onStatus(rejected.length ? {kind:'partial',message:'Часть гостевых данных не перенесена. Они остались в этом браузере.',rejected:state.sync.rejected}
    : {kind:'synced',message:'Гостевой прогресс перенесён в аккаунт.'});
  return {state,mapping:state.sync?.mapping ?? {},rejected};
};
