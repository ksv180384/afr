import { applyGuestOperation, guestRunView, guestError } from './guestProgress.js';

export const createGuestAdapter = ({repository,runId,tabId,onStatus = () => {},idFactory = () => crypto.randomUUID(),now = () => new Date().toISOString()}) => {
  let current;
  let serial = Promise.resolve();
  const envelopes = new Map();
  const commands = new Map();
  const saved = () => onStatus(repository.temporary
    ? {kind:'temporary',message:'Временный режим: прогресс хранится только до закрытия этой страницы.'}
    : {kind:'idle',message:''});
  const report = (error) => {
    if (error.code === 'bound_owner') onStatus({kind:'bound',message:'Гостевой прогресс связан с аккаунтом. Продолжите занятие в аккаунте.'});
    else if (error.code === 'revision_conflict') onStatus({kind:'conflict',message:error.message});
    else if (!error.code || error.code === 'storage' || error.name?.includes('Quota')) onStatus({kind:'storage',message:'Не удалось сохранить в браузере. Не закрывайте страницу; можно повторить запись или продолжить временно.'});
    throw error;
  };
  const schedule = (operation) => {
    const result = serial.then(operation);
    serial = result.catch(() => {});
    return result.catch(report);
  };
  const execute = (action,input) => schedule(async () => {
    if (!current) throw guestError('Сначала загрузите занятие.', 'not_found');
    if (action === 'draft' && (current.presentations[current.cursor].presentation_id !== input.presentation_id || current.presentations[current.cursor].first_result)) return;
    if (!envelopes.has(input.client_event_id)) envelopes.set(input.client_event_id,{...input,expected_revision:current.revision,client_occurred_at:now()});
    const request = envelopes.get(input.client_event_id);
    const run = await repository.mutate((state) => {
      applyGuestOperation(state,runId,tabId,action,request);
      return state.runs[runId];
    },{run_id:runId});
    current = run;
    if (action === 'draft') onStatus({kind:'draft',message:'Черновик сохранён в этом браузере.'});
    else saved();
    return guestRunView(current);
  });
  const command = async (action,fields = {}) => {
    const key = action + ':' + JSON.stringify(fields);
    if (!commands.has(key)) commands.set(key,{client_event_id:idFactory(),...fields});
    const result = await execute(action,commands.get(key));
    commands.delete(key);
    return result;
  };
  return Object.freeze({
    mode:'guest',
    async load({advanceFrom,introductionSeen} = {}) {
      if (advanceFrom) return command('advance',{presentation_id:advanceFrom});
      if (introductionSeen) return command('introduction',{presentation_id:introductionSeen});
      return schedule(async () => {
        current = await repository.mutate((state) => {
          const run = state?.runs[runId];
          if (!run) throw guestError('Занятие не найдено в этом браузере.', 'not_found');
          if (state.bound_user_id !== null) throw guestError('Продолжите в связанном аккаунте.', 'bound_owner');
          run.lease = tabId;
          return run;
        },{run_id:runId,claimed:true});
        saved();
        return guestRunView(current,{restore:true});
      });
    },
    start: () => command('resume'),
    answer: (input) => execute('answer',{...input,event_type:'answer'}),
    help: (input) => execute('help',input),
    finish: () => command('finish'),
    storeDraft: (input) => execute('draft',{...input,client_event_id:idFactory()}),
    async storeDraft(input) { await execute('draft',{...input,client_event_id:idFactory()}); },
    async saveDraft(input) { await execute('draft',{...input,client_event_id:idFactory()}); if (current.status !== 'completed') await command('pause'); },
    async checkOwnership() {
      const state = await repository.read();
      if (state?.bound_user_id !== null) { onStatus({kind:'bound',message:'Гостевой прогресс связан с аккаунтом. Продолжите занятие в аккаунте.'}); return; }
      if (state?.runs[runId]?.lease !== tabId) onStatus({kind:'conflict',message:'Занятие продолжено в другой вкладке. Загрузите принятую версию.'});
    },
    async acceptServerState() { return this.load(); },
  });
};
