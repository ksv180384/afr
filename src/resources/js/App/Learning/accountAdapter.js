import { randomUuid } from './randomUuid.js';
import { practiceDraftId } from './accountRepository.js';
import { accountPlayback, playbackPrediction, projectAccountAction } from './accountPlayback.js';

const id = () => randomUuid();
const statusError = (message, status, code) => Object.assign(new Error(message), { response: { status, data: { message, code } } });
const localFailure = error => new Error('Не удалось сохранить на устройстве.', {cause:error});
const playbackConflict = () => statusError('Ход занятия на сервере изменился. Ваши ответы сохранены на устройстве. Загрузите состояние сервера, чтобы продолжить.',409,'playback_conflict');

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
  if (row.action === 'reviewMistakes') return !state.corrections && state.mistakes_count > 0 && state.summary?.all_steps_processed === true;
  if (row.action === 'finish') return state.corrections
    ? state.corrections.can_finish : state.summary?.all_steps_processed === true;
  // The same presentation can reappear in a later round. A stale action cannot be replayed there.
  if (state.corrections) return false;
  if (row.request.presentation_id !== state.presentation?.presentation_id || !state.started) return false;
  if (row.action === 'advance') return !!state.presentation.first_result;
  if (row.action === 'introduction') return !state.introduction_seen;
  return ['answer','help'].includes(row.action) && !state.presentation.first_result;
};

const deliverQueue = async ({ ownerUserId, runId, repository, transport, state = null, profile: checkedProfile = null, onMutation = () => {}, onStatus = () => {}, idFactory = id }) => {
  let rows;
  try { rows = await repository.pending(ownerUserId, runId); }
  catch (error) { onStatus({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
  if (!rows.length) { onStatus({kind:'idle',message:''}); return state; }
  const runOptions = runId === null ? undefined : {headers:{'X-Practice-Owner':String(ownerUserId)}};
  let activeRow;
  try {
    if (checkedProfile && checkedProfile.owner_user_id !== ownerUserId) throw statusError('Войдите в исходный аккаунт.',401,'owner_changed');
    if (runId === null) {
      const ownerBound = rows.every(row => row.request.expected_owner_user_id === ownerUserId);
      const profile = (ownerBound ? checkedProfile : null) ?? await transport.profile();
      if (profile.owner_user_id !== ownerUserId) throw statusError('Восстановите вход в тот же аккаунт. Очередь другого владельца не отправлена.', 401, 'owner_changed');
    }
    let previousAccepted = false;
    for (let row of rows) {
      if (state?.status === 'cancelled' && runId === state.run_id) {
        await repository.mark(ownerUserId, row.local_id, 'dismissed');
        continue;
      }
      if (row.prediction && row.status === 'conflict') throw playbackConflict();
      let recovered = false;
      for (let attempt = 0; attempt < 3; attempt++) {
        let sealed;
        try { sealed = await repository.seal(ownerUserId, row.local_id, !row.prediction && previousAccepted ? state?.revision : undefined); }
        catch (error) { onStatus({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
        onStatus({kind:'sending',message:'Сохраняем на сервере…'});
        activeRow = sealed;
        let receipt;
        try { receipt = await transport.call(sealed.action, sealed.run_id, sealed.request, sealed.prediction ? {...runOptions,timeout:15000} : runOptions); }
        catch (error) {
          if (sealed.prediction && [404,409,422].includes(error.response?.status)) throw playbackConflict();
          if (error.response?.status !== 409 || !['revision_conflict','stale_presentation'].includes(error.response.data?.code)) throw error;
          const latest = error.response.data?.state;
          if (runId === null) {
            const profile = Number.isInteger(latest?.profile?.revision) ? latest.profile : await transport.profile();
            if (profile.owner_user_id !== ownerUserId) throw statusError('Войдите в исходный аккаунт.',401,'owner_changed');
            const sameOwner = latest?.profile?.owner_user_id === ownerUserId;
            const selected = sameOwner ? (row.request.route_start ? latest.active_run : latest.run) : null;
            const restoreDaily = !row.request.route_start && row.request.kind === 'daily' && selected &&
              (!row.request.replace_assignment || (selected.status !== 'completed' && selected.level === profile.current_level));
            const restoreRoute = row.request.route_start &&
              (!row.request.session_id || selected?.session_id === row.request.session_id);
            if (selected && (restoreRoute || restoreDaily)) {
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
              if (!state.corrections && row.action === 'answer' && row.request.presentation_id === state.presentation?.presentation_id && !state.presentation.first_result) {
                await repository.saveDraft(ownerUserId,runId,row.request.presentation_id,row.request.answer);
              }
              await repository.mark(ownerUserId,row.local_id,'dismissed');
              recovered = true; break;
            }
          }
          continue;
        }
        if (receipt.state?.status === 'cancelled' && runId === receipt.state.run_id && receipt.state.owner_user_id === ownerUserId) {
          onMutation();
          await repository.mark(ownerUserId, sealed.local_id, 'dismissed');
          state = receipt.state;
          recovered = true;
          break;
        }
        if (receipt.accepted_event_id !== sealed.client_event_id || receipt.state?.owner_user_id !== ownerUserId) {
          throw statusError('Сервер не подтвердил сохранение.',503,'unavailable');
        }
        onMutation();
        try { await repository.mark(ownerUserId, sealed.local_id, 'acked', receipt); }
        catch (error) { onStatus({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
        if (sealed.prediction && JSON.stringify(sealed.prediction) !== JSON.stringify(playbackPrediction(receipt.state))) {
          throw playbackConflict();
        }
        previousAccepted = !state || receipt.state.revision >= state.revision;
        if (previousAccepted) state = receipt.state;
        recovered = true;
        break;
      }
      if (!recovered) throw statusError('Не удалось обновить занятие.',503,'unavailable');
    }
    onStatus({kind:'idle',message:''});
    return state;
  } catch (error) {
    const status = error.response?.status;
    const message = error.response?.data?.message ?? (status >= 500 ? 'Сервер временно недоступен.' : error.message);
    if (error.response?.data?.code === 'playback_conflict') {
      onStatus({kind:'conflict',message});
      try {
        const pending = await repository.pending(ownerUserId,runId);
        for (const row of pending.filter(row => row.prediction)) await repository.mark(ownerUserId,row.local_id,'conflict');
      } catch (failure) { onStatus({kind:'storage',message:localFailure(failure).message}); throw localFailure(failure); }
    } else if ([401,419,403].includes(status)) {
      onStatus({kind:'auth',message:'Войдите в свой аккаунт, чтобы продолжить. Ответ сохранён на этом устройстве.'});
    } else if ([404,422].includes(status)) {
      if (runId === null) {
        if (activeRow) await repository.mark(ownerUserId,activeRow.local_id,'dismissed');
      }
      onStatus({kind:'error',message:error.response?.data?.message || 'Это занятие сейчас недоступно. Выберите другое в практике.'});
    } else if (!error.message.startsWith('Не удалось сохранить на устройстве.')) {
      onStatus({kind:'pending',message:'Не удалось связаться с сервером. Действие сохранено на устройстве.'});
    }
    throw Object.assign(new Error(status ? message : (error.message.startsWith('Не удалось сохранить на устройстве.') ? error.message : 'Нет соединения. Действие сохранено на устройстве.'), {cause:error}), { response:error.response });
  }
};

export const startAccountRun = async ({ ownerUserId, input, repository, transport, onStatus, profile = null, onMutation }) => {
  if (profile) input = {...input, expected_owner_user_id: ownerUserId};
  let row;
  try {
    const pending = await repository.pending(ownerUserId,null);
    if (pending.length && pending[0].client_event_id !== input.client_event_id) return deliverAccountQueue({ownerUserId,runId:null,repository,transport,onStatus,profile,onMutation});
    row = await repository.enqueue(ownerUserId,null,'start',input,input);
  } catch (error) {
    if (error.message.startsWith('Сначала восстановите')) throw error;
    onStatus?.({kind:'storage',message:localFailure(error).message}); throw localFailure(error);
  }
  if (row.status === 'acked') {
    const currentProfile = row.request?.expected_owner_user_id === ownerUserId && profile ? profile : await transport.profile();
    if (currentProfile.owner_user_id !== ownerUserId) throw new Error('Войдите в исходный аккаунт.');
    const view = await transport.loadRun(row.acknowledgement.run_id);
    if (view.owner_user_id !== ownerUserId) throw new Error('Войдите в исходный аккаунт.');
    return view;
  }
  return deliverAccountQueue({ownerUserId,runId:null,repository,transport,onStatus,profile,onMutation});
};

export const createAccountAdapter = ({ ownerUserId, runId, repository, transport, onStatus = () => {}, onMutation = () => {}, idFactory = id }) => {
  let state;
  let confirmed;
  let playback;
  let background = null;
  let queued = false;
  let status = 'loading';
  let onCancelled;
  const notify = (next) => { status = next.kind; onStatus(next); };
  const deliver = (onStatus = notify) => deliverAccountQueue({ownerUserId,runId,repository,transport,state:confirmed,onStatus,onMutation,idFactory});
  const flushPlayback = () => {
    queued = true;
    if (!background) {
      background = (async () => {
        await Promise.resolve();
        try {
          do {
            queued = false;
            confirmed = await deliver(next => notify(next.kind === 'pending' ? {
              kind:'warning', message:'Ответы сохранены на устройстве. Пока не удалось отправить их на сервер.',
            } : next));
            if (confirmed?.status === 'cancelled') {
              state = confirmed;
              playback = null;
              onCancelled?.(state);
            }
          } while (queued);
        } catch (error) { queued = true; throw error; }
        finally { background = null; }
      })();
    }
    return background;
  };
  const storedDraft = async (view) => {
    if (view.presentation && !view.presentation.first_result) {
      try { view.local_draft = await repository.draft(ownerUserId,runId,practiceDraftId(view.presentation.presentation_id,view.corrections)); }
      catch (error) { notify({kind:'storage',message:localFailure(error).message}); }
    }
    return view;
  };
  const execute = async (action, logical) => {
    if (!state) throw new Error('Сначала загрузите состояние прохождения.');
    if (status === 'conflict') throw playbackConflict();
    const projected = projectAccountAction(state, playback, action, logical);
    if (projected) {
      try {
        await repository.enqueue(ownerUserId,runId,action,logical,
          {...logical,expected_revision:state.revision},playbackPrediction(projected));
      } catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
      state = projected;
      queued = true;
      if (!['warning','auth','conflict'].includes(status)) flushPlayback().catch(() => {});
      return action === 'advance' || action === 'reviewMistakes' ? storedDraft(state) : state;
    }
    if (background || queued) {
      await flushPlayback();
      state = confirmed;
    }
    let row;
    let pending;
    try { pending = await repository.pending(ownerUserId,runId); }
    catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
    if (pending.length && !pending.some(item => item.client_event_id === logical.client_event_id)) {
      state = confirmed = await deliver();
      return storedDraft(state);
    }
    try {
      row = await repository.enqueue(ownerUserId,runId,action,logical,{...logical,expected_revision:state.revision});
    } catch (error) {
      notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error);
    }
    if (row.status === 'acked') {
      const current = await transport.loadRun(runId);
      if (current.owner_user_id !== ownerUserId) throw new Error('Войдите в исходный аккаунт.');
      state = confirmed = current;
      return storedDraft(state);
    }
    state = confirmed = await deliver();
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
  const load = async () => {
    if (background) await background.catch(() => {});
    const view = await transport.loadRun(runId,{playback:true}).catch(error => {
      if (error.response?.data?.code !== 'response_too_large') throw error;
      return transport.loadRun(runId);
    });
    if (view.owner_user_id !== ownerUserId) throw new Error('Состояние принадлежит другому аккаунту.');
    playback = accountPlayback(view);
    const { playback:pack, ...serverState } = view;
    confirmed = serverState;
    try { confirmed = await deliver(); }
    catch (error) { if (status !== 'conflict') throw error; }
    queued = false;
    state = confirmed;
    return {...await storedDraft(state),restore_assistance:true};
  };
  return Object.freeze({
    mode:'account',
    onCancelled(listener) { onCancelled = listener; return () => { onCancelled = undefined; }; },
    async load({advanceFrom,introductionSeen} = {}) {
      if (advanceFrom) return command('advance',{presentation_id:advanceFrom});
      if (introductionSeen) return command('introduction',{presentation_id:introductionSeen});
      return load();
    },
    start: () => command('resume'),
    answer: (input) => execute('answer',{...input,event_type:'answer'}),
    help: (input) => execute('help',input),
    reviewMistakes: () => command('reviewMistakes'),
    finish: () => command('finish'),
    sync: () => flushPlayback(),
    async storeDraft({presentation_id,answer}) {
      try {
        await repository.saveDraft(ownerUserId,runId,practiceDraftId(presentation_id,state?.corrections),answer);
        if (!['sending','pending','warning','auth','conflict'].includes(status)) notify({kind:'draft',message:'Черновик сохранён на этом устройстве. Ответ ещё не отправлен.'});
      } catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
    },
    async saveDraft(input) {
      try { await repository.saveDraft(ownerUserId,runId,practiceDraftId(input.presentation_id,state?.corrections),input.answer); }
      catch (error) { notify({kind:'storage',message:localFailure(error).message}); throw localFailure(error); }
      if (playback && state?.started && !state.finished && status !== 'conflict') {
        await command('pause');
        return;
      }
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
      if (background) await background.catch(() => {});
      const pending = await repository.pending(ownerUserId,runId);
      if (pending.some(row => row.status !== 'conflict')) throw new Error('Сервер ещё не подтвердил предыдущее действие.');
      for (const row of pending) {
        await repository.mark(ownerUserId,row.local_id,'dismissed');
      }
      commands.clear();
      const latest = await load();
      notify({kind:'confirmed',message:'Принято состояние сервера. Предыдущая запись оставлена в локальном журнале.'});
      return latest;
    },
  });
};
