import { learningAccess, completedGuestSessions } from './learningAccess.js';
import { shallowReactive } from 'vue';
import { addGuestRun, guestDate, changeGuestProfile } from './guestProgress.js';
import { startAccountRun, deliverAccountQueue } from './accountAdapter.js';
import { browserTimezone } from './practiceTime.js';

export const createPracticeHomeStore = ({transport, guestTransport, guestRepository, accountRepository,
  idFactory = () => crypto.randomUUID(), now = () => new Date().toISOString()}) => {
  const state = shallowReactive({owner:undefined,home:null,history:null,ready:false,loading:false,busy:false,profilePending:false,guest:null,error:'',saving:null});
  let generation = 0;
  let loading = null;
  let action = null;
  let profileRequest = null;
  const setOwner = (owner) => {
    if (state.owner === owner) return;
    generation++;
    state.owner = owner; state.home = null; state.history = null; state.ready = false; state.error = ''; state.saving = null;
    profileRequest = null; state.profilePending=false; state.guest=null; loading = null;
  };
  const refresh = (owner, page = 1, {includeHistory = true} = {}) => {
    setOwner(owner);
    if (loading) return loading;
    const revision = generation;
    state.loading = true; state.error = '';
    const pending = (async () => {
      try {
        if (owner !== null) {
          const [home,history] = await Promise.all([transport.today(),includeHistory ? transport.history(page) : Promise.resolve(state.history)]);
          if (revision !== generation) return null;
          if (home.profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
          state.home = home; state.history = history;
        } else {
          await guestRepository.archiveBound();
          await guestRepository.initialize();
          const local = await guestRepository.read();
          if (revision !== generation) return null;
          const runs = Object.values(local.runs).sort((a,b)=>b.record_sequence-a.record_sequence);
          const completed = runs.filter((run)=>run.status==='completed').map((run)=>run.snapshot.id);
          let catalog = []; let contentError = ''; let access = null;
          try { const response = await guestTransport.catalog(); catalog = response.sessions; if(response.access_outline) access = learningAccess(response.access_outline, completedGuestSessions(local), local.current_level); }
          catch { contentError = 'Нет соединения с библиотекой. Сохранённые занятия можно продолжить.'; }
          if (revision !== generation) return null;
          const candidate = catalog.find(session => access ? !access.sessions[session.id]?.locked && !access.sessions[session.id]?.review_only && !completed.includes(session.id) : session.level===local.current_level && !completed.includes(session.id) && session.prerequisite_ids.every(id=>completed.includes(id)));
          const active = runs.find((run)=>run.status!=='completed');
          const date = guestDate(now(),local.timezone);
          state.home = {profile:{current_level:local.current_level,timezone:local.timezone,revision:local.profile_revision},
            run:active ? {id:active.id,title:active.snapshot.title,level:active.snapshot.level,status:active.status,duration_minutes:active.snapshot.duration_minutes,theme:catalog.find((item)=>item.id===active.snapshot.id)?.theme} : null,
            candidate:candidate ?? null,exhausted:!candidate,content_error:contentError,
            activity_today:runs.some((run)=>run.finished_at && guestDate(run.finished_at,run.timezone)===date),
            local_date:date,word_count:Object.keys(local.vocabulary).length};
          state.history = {data:runs,total:runs.length}; state.guest = local;
        }
        state.ready = true;
        return {home:state.home,history:state.history};
      } catch (error) {
        if (revision === generation) state.error = error.response?.data?.message ?? error.message ?? 'Не удалось загрузить практику.';
        throw error;
      } finally {
        if (revision === generation) { state.loading = false; loading = null; }
      }
    })();
    loading = pending;
    return pending;
  };
  const serialize = (operation) => {
    if (action) return action;
    const revision = generation;
    state.busy = true; state.error = '';
    action = Promise.resolve().then(operation).catch((error)=>{
      if (revision===generation) state.error=error.response?.data?.message ?? error.message;
      throw error;
    }).finally(()=>{action=null;state.busy=false;});
    return action;
  };
  const start = (owner) => serialize(async () => {
    setOwner(owner);
    const token = generation;
    if (!state.ready) await refresh(owner);
    if (owner !== null) {
      const pending = await accountRepository.pending(owner,null);
      const onStatus = (value)=>{if(token===generation) state.saving=value;};
      const view = pending.length ? await deliverAccountQueue({ownerUserId:owner,runId:null,repository:accountRepository,transport,onStatus})
        : await startAccountRun({ownerUserId:owner,input:{client_event_id:idFactory(),expected_revision:state.home.profile.revision,kind:'daily',timezone:state.home.profile.persisted === false ? browserTimezone() : state.home.profile.timezone},
          repository:accountRepository,transport,onStatus});
      if (token!==generation) throw new Error('Аккаунт изменился. Откройте практику заново.');
      return '/daily/run/'+view.run_id;
    }
    if (state.home.run) return '/daily/guest/'+state.home.run.id;
    if (!state.home.candidate) throw new Error('Новых доступных занятий пока нет.');
    const source = await guestTransport.session(state.home.candidate.id);
    if (token!==generation) throw new Error('Состояние изменилось. Откройте практику заново.');
    const runId=idFactory();
    const input={source,runId,presentationIds:source.snapshot.tasks.map(()=>idFactory()),eventId:idFactory(),tabId:idFactory(),now:now(),kind:'daily'};
    const chosen = await guestRepository.mutate((local)=>{
      const existing=Object.values(local.runs).sort((a,b)=>b.record_sequence-a.record_sequence).find((run)=>run.status!=='completed');
      if(existing) return existing.id;
      if(!source.access_outline && local.current_level!==source.snapshot.level) throw new Error('Уровень изменён. Обновите карточку.');
      addGuestRun(local,input);
      return runId;
    },{run_id:runId});
    await refresh(null);
    return '/daily/guest/'+chosen;
  });
  const changeLevel = (owner, level) => serialize(async () => {
    setOwner(owner);
    const token=generation;
    if(!state.ready) await refresh(owner);
    if(token!==generation) throw new Error('Аккаунт изменился.');
    if(owner !== null) {
      profileRequest ??= {client_event_id:idFactory(),expected_revision:state.home.profile.revision,current_level:level,timezone:state.home.profile.persisted === false ? browserTimezone() : state.home.profile.timezone};
      state.profilePending=true;
      const profile=await transport.profile();
      if(profile.owner_user_id!==owner || token!==generation) throw new Error('Войдите в исходный аккаунт.');
      try { await transport.updateProfile(profileRequest); }
      catch (error) {
        if (error.response?.status !== 409 || error.response.data?.code !== 'revision_conflict') throw error;
        const latest=await transport.profile();
        if(latest.owner_user_id!==owner || token!==generation) throw new Error('Войдите в исходный аккаунт.');
        profileRequest={...profileRequest,client_event_id:idFactory(),expected_revision:latest.revision,timezone:latest.timezone};
        await transport.updateProfile(profileRequest);
      }
      if(token!==generation) throw new Error('Аккаунт изменился.');
      profileRequest=null; state.profilePending=false;
    } else {
      await guestRepository.mutate((local)=>changeGuestProfile(local,{client_event_id:idFactory(),expected_revision:local.profile_revision,
        current_level:level,timezone:local.timezone,client_occurred_at:now()}),{profile:true});
    }
    if(token!==generation) throw new Error('Аккаунт изменился.');
    await refresh(owner);
  });
  return {state,refresh,start,changeLevel,accountRepository,guestRepository,
    resetProfile:()=>{profileRequest=null;state.profilePending=false;},setOwner};
};
