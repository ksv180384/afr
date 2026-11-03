const id = () => crypto.randomUUID();
const statusError = (message, status, code) => Object.assign(new Error(message), { response: { status, data: { message, code } } });
const localFailure = error => new Error('Не удалось сохранить на устройстве. Не закрывайте страницу: повторите сохранение вручную.', {cause:error});

const deliveries = new WeakMap();
export const deliverAccountQueue = options => {
  let active = deliveries.get(options.repository);
  if (!active) { active = new Map(); deliveries.set(options.repository, active); }
  const key = options.ownerUserId + ':' + options.runId;
  if (active.has(key)) return active.get(key);
  const result = deliverQueue(options).finally(() => active.delete(key));
  active.set(key, result);
  return result;
};

const canRetryRejected = (row, state) => {
  if (state.finished) return false;
  if (row.action === 'resume') return state.status === 'paused';
  if (row.action === 'pause') return state.status !== 'paused';
  if (row.action === 'finish') return state.summary?.all_steps_processed === true;
  if (row.request.presentation_id !== state.presentation?.presentation_id || !state.started) return false;
  if (row.action === 'advance') return !!state.presentation.first_result;
  if (row.action === 'introduction') return !state.introduction_seen;
  return ['answer','help'].includes(row.action) && !state.presentation.first_result;
};

const deliverQueue = async ({ ownerUserId, runId, repository, transport, state = null, profile: checkedProfile = null, onStatus = () => {}, idFactory = id }) => {
  let rows;
  try { rows = await repository.pending(ownerUserId, runId); }
  catch (error) { onStatus({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
  if (!rows.length) { onStatus({kind:'idle',message:''}); return state; }
  let activeRow;
  try {
    const profile = checkedProfile ?? await transport.profile();
    if (profile.owner_user_id !== ownerUserId) throw statusError('Восстановите вход в тот же аккаунт. Очередь другого владельца не отправлена.', 401, 'owner_changed');
    let previousAccepted = false;
    for (let row of rows) {
      let recovered = false;
      for (let attempt = 0; attempt < 3; attempt++) {
        let sealed;
        try { sealed = await repository.seal(ownerUserId, row.local_id, previousAccepted ? state?.revision : undefined); }
        catch (error) { onStatus({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
        onStatus({kind:'sending',message:'Сохраняем на сервере…'});
        activeRow = sealed;
        let receipt;
        try { receipt = await transport.call(sealed.action, sealed.run_id, sealed.request); }
        catch (error) {
          if (error.response?.status !== 409 || !['revision_conflict','stale_presentation'].includes(error.response.data?.code)) throw error;
          const latest = error.response.data?.state;
          if (runId === null) {
            const profile = await transport.profile();
            if (profile.owner_user_id !== ownerUserId) throw statusError('Войдите в исходный аккаунт.',401,'owner_changed');
            const selected = latest?.profile?.owner_user_id === ownerUserId ? latest.run : null;
            if (row.request.kind === 'daily' && selected && (!row.request.replace_assignment || (selected.status !== 'completed' && selected.level === profile.current_level))) {
              state = await transport.loadRun(selected.id);
              if (state.owner_user_id !== ownerUserId) throw statusError('Войдите в исходный аккаунт.',401,'owner_changed');
              await repository.mark(ownerUserId,row.local_id,'dismissed');
              recovered = true; break;
            }
            if (attempt === 2) throw error;
            row = await repository.replaceRejected(ownerUserId,row.local_id,{...row.request,client_event_id:idFactory(),expected_revision:profile.revision});
          } else {
            state = await transport.loadRun(runId);
            if (state.owner_user_id !== ownerUserId) throw statusError('Войдите в исходный аккаунт.',401,'owner_changed');
            if (attempt < 2 && canRetryRejected(row,state)) {
              row = await repository.replaceRejected(ownerUserId,row.local_id,{...row.request,client_event_id:idFactory(),expected_revision:state.revision});
            } else {
              if (row.action === 'answer' && row.request.presentation_id === state.presentation?.presentation_id && !state.presentation.first_result) {
                await repository.saveDraft(ownerUserId,runId,row.request.presentation_id,row.request.answer);
              }
              await repository.mark(ownerUserId,row.local_id,'dismissed');
              recovered = true; break;
            }
          }
          continue;
        }
        if (receipt.accepted_event_id !== sealed.client_event_id || receipt.state?.owner_user_id !== ownerUserId) {
          throw statusError('Сервер не подтвердил событие. Повторите тот же запрос.',503,'unavailable');
        }
        try { await repository.mark(ownerUserId, sealed.local_id, 'acked', receipt); }
        catch (error) { onStatus({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
        previousAccepted = !state || receipt.state.revision >= state.revision;
        if (previousAccepted) state = receipt.state;
        recovered = true;
        break;
      }
      if (!recovered) throw statusError('Не удалось обновить занятие. Повторите отправку вручную.',503,'unavailable');
    }
    onStatus({kind:'idle',message:''});
    return state;
  } catch (error) {
    const status = error.response?.status;
    const message = error.response?.data?.message ?? (status >= 500 ? 'Сервер временно недоступен. Повторите отправку сохранённого события.' : error.message);
    if ([401,419,403].includes(status)) {
      onStatus({kind:'auth',message:'Войдите в свой аккаунт, чтобы продолжить. Ответ сохранён на этом устройстве.'});
    } else if ([404,422].includes(status)) {
      if (runId === null) {
        if (activeRow) await repository.mark(ownerUserId,activeRow.local_id,'dismissed');
      }
      onStatus({kind:'error',message:error.response?.data?.message || 'Это занятие сейчас недоступно. Выберите другое в практике.'});
    } else if (!error.message.startsWith('Не удалось сохранить на устройстве.')) {
      onStatus({kind:'pending',message:'Не удалось связаться с сервером. Ответ сохранён на устройстве. Повторите отправку вручную.'});
    }
    throw Object.assign(new Error(status ? message : (error.message.startsWith('Не удалось сохранить на устройстве.') ? error.message : 'Нет соединения. Изменения сохранены на устройстве. Повторите отправку вручную.'), {cause:error}), { response:error.response });
  }
};

export const startAccountRun = async ({ ownerUserId, input, repository, transport, onStatus, profile = null }) => {
  let row;
  try {
    const pending = await repository.pending(ownerUserId,null);
    if (pending.length && pending[0].client_event_id !== input.client_event_id) return deliverAccountQueue({ownerUserId,runId:null,repository,transport,onStatus,profile});
    row = await repository.enqueue(ownerUserId,null,'start',input,input);
  } catch (error) {
    if (error.message.startsWith('Сначала восстановите')) throw error;
    onStatus?.({kind:'storage',message:localFailure(error).message}); throw localFailure(error);
  }
  if (row.status === 'acked') {
    const currentProfile = profile ?? await transport.profile();
    if (currentProfile.owner_user_id !== ownerUserId) throw new Error('Войдите в исходный аккаунт.');
    return transport.loadRun(row.acknowledgement.run_id);
  }
  return deliverAccountQueue({ownerUserId,runId:null,repository,transport,onStatus,profile});
};

export const createAccountAdapter = ({ ownerUserId, runId, repository, transport, onStatus = () => {}, idFactory = id }) => {
  let state;
  let status = 'loading';
  const notify = (next) => { status = next.kind; onStatus(next); };
  const storedDraft = async (view) => {
    if (view.presentation && !view.presentation.first_result) {
      try { view.local_draft = await repository.draft(ownerUserId,runId,view.presentation.presentation_id); }
      catch (error) { notify({kind:'storage',message:localFailure(error).message}); }
    }
    return view;
  };
  const execute = async (action, logical) => {
    if (!state) throw new Error('Сначала загрузите состояние прохождения.');
    let row;
    let pending;
    try { pending = await repository.pending(ownerUserId,runId); }
    catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
    if (pending.length && !pending.some(item => item.client_event_id === logical.client_event_id)) {
      state = await deliverAccountQueue({ownerUserId,runId,repository,transport,state,onStatus:notify,idFactory});
      return storedDraft(state);
    }
    try {
      row = await repository.enqueue(ownerUserId,runId,action,logical,{...logical,expected_revision:state.revision});
    } catch (error) {
      notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error);
    }
    if (row.status === 'acked') {
      const profile = await transport.profile();
      if (profile.owner_user_id !== ownerUserId) throw new Error('Войдите в исходный аккаунт.');
      state = await transport.loadRun(runId);
      return storedDraft(state);
    }
    state = await deliverAccountQueue({ownerUserId,runId,repository,transport,state,onStatus:notify,idFactory});
    return storedDraft(state);
  };
  const commands = new Map();
  const command = async (action, fields = {}) => {
    const key = action + ':' + JSON.stringify(fields);
    if (!commands.has(key)) commands.set(key,{client_event_id:idFactory(),...fields});
    const result = await execute(action,commands.get(key));
    commands.delete(key);
    return result;
  };
  return Object.freeze({
    mode:'account',
    async load({advanceFrom,introductionSeen} = {}) {
      if (advanceFrom) return command('advance',{presentation_id:advanceFrom});
      if (introductionSeen) return command('introduction',{presentation_id:introductionSeen});
      state = await transport.loadRun(runId);
      if (state.owner_user_id !== ownerUserId) throw new Error('Состояние принадлежит другому аккаунту.');
      state = await deliverAccountQueue({ownerUserId,runId,repository,transport,state,onStatus:notify,idFactory});
      return {...await storedDraft(state),restore_assistance:true};
    },
    start: () => command('resume'),
    answer: (input) => execute('answer',{...input,event_type:'answer'}),
    help: (input) => execute('help',input),
    finish: () => command('finish'),
    async storeDraft({presentation_id,answer}) {
      try {
        await repository.saveDraft(ownerUserId,runId,presentation_id,answer);
        if (!['sending','pending','auth','conflict'].includes(status)) notify({kind:'draft',message:'Черновик сохранён на этом устройстве. Ответ ещё не отправлен.'});
      } catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
    },
    async saveDraft(input) {
      try { await repository.saveDraft(ownerUserId,runId,input.presentation_id,input.answer); }
      catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
      const pending = await repository.pending(ownerUserId,runId);
      if (pending.length) { notify({kind:'pending',message:'Черновик и очередь сохранены на этом устройстве. Сервер ещё не подтвердил изменения.'}); return; }
      if (state?.finished) return;
      try { await command('pause'); }
      catch (error) {
        const queued = await repository.pending(ownerUserId,runId);
        if (!queued.length) throw error;
      }
    },
    async acceptServerState() {
      const pending = await repository.pending(ownerUserId,runId);
      for (const row of pending) {
        if (row.status !== 'conflict') throw new Error('Есть неподтверждённое событие. Сначала повторите отправку.');
        await repository.mark(ownerUserId,row.local_id,'dismissed');
      }
      notify({kind:'confirmed',message:'Принято состояние сервера. Предыдущая запись оставлена в локальном журнале.'});
      state = await transport.loadRun(runId);
      return storedDraft(state);
    },
  });
};
