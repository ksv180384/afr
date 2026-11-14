import { shallowReactive } from 'vue';
import { addGuestRun, changeGuestProfile, guestDate } from './guestProgress.js';
import { startAccountRun, deliverAccountQueue } from './accountAdapter.js';
import { browserTimezone } from './practiceTime.js';
import { guestShortReviewPlan, guestShortReviewSource } from './guestVocabulary.js';
import { activeGuestRun, guestPracticeHome, searchGuestRoute } from './guestPracticeHome.js';

export const createPracticeHomeStore = ({transport, guestTransport, guestRepository, accountRepository,
  idFactory = () => crypto.randomUUID(), now = () => new Date().toISOString()}) => {
  const state = shallowReactive({
    owner: undefined, home: null, history: null, ready: false, loading: false, busy: false,
    profilePending: false, guest: null, error: '', saving: null,
  });
  let generation = 0;
  let homeRequest;
  let historyRequest;
  let action;
  let profileRequest;
  let currentSelection = {};
  let selectionKey;
  let catalog;
  let contentError = '';

  const invalidate = owner => {
    if (owner !== state.owner) return;
    homeRequest = undefined;
    historyRequest = undefined;
    state.ready = false;
    state.loading = false;
    state.history = null;
  };
  const setOwner = owner => {
    if (state.owner === owner) return;
    generation++;
    invalidate(state.owner);
    Object.assign(state, {owner, home: null, guest: null, error: '', saving: null, busy: false, profilePending: false});
    action = undefined;
    profileRequest = undefined;
    currentSelection = {};
    selectionKey = undefined;
    catalog = undefined;
    contentError = '';
  };

  const updateGuest = local => {
    if (state.owner !== null || local?.bound_user_id != null || !local) return;
    invalidate(null);
    state.guest = local;
    state.home = guestPracticeHome(local, catalog, currentSelection, now(), contentError);
    state.ready = catalog !== undefined;
  };

  const loadHome = (owner, selection, force) => {
    setOwner(owner);
    const key = JSON.stringify([selection.level ?? null, selection.unit ?? null]);
    if (homeRequest?.key === key) return homeRequest.promise;
    const date = state.home?.local_date;
    const sameDay = !date || date === guestDate(now(), state.home.profile.timezone);
    if (!force && state.ready && selectionKey === key && sameDay) return Promise.resolve({home: state.home, history: state.history});
    if (selectionKey !== key) {
      state.home = null;
      state.ready = false;
    }
    currentSelection = selection;
    selectionKey = key;
    const request = {key};
    homeRequest = request;
    state.loading = true;
    state.error = '';
    request.promise = (async () => {
      try {
        if (owner !== null) {
          const home = await transport.today(selection);
          if (homeRequest !== request) return null;
          if (home.profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
          state.home = home;
        } else {
          await guestRepository.archiveBound();
          if (homeRequest !== request) return null;
          const local = await guestRepository.initialize() ?? await guestRepository.read();
          if (homeRequest !== request) return null;
          if (local.bound_user_id != null) throw new Error('Продолжите в связанном аккаунте.');
          if (force || !catalog) {
            try {
              const response = await guestTransport.catalog();
              if (homeRequest !== request) return null;
              catalog = response;
              contentError = '';
            } catch {
              if (homeRequest !== request) return null;
              catalog = null;
              contentError = 'Нет соединения с библиотекой. Сохранённые занятия можно продолжить.';
            }
          }
          state.guest = local;
          state.home = guestPracticeHome(local, catalog, selection, now(), contentError);
        }
        state.ready = true;
        return {home: state.home, history: state.history};
      } catch (error) {
        if (homeRequest !== request) return null;
        state.error = error.response?.data?.message ?? error.message ?? 'Не удалось загрузить практику.';
        throw error;
      } finally {
        if (homeRequest === request) {
          state.loading = false;
          homeRequest = undefined;
        }
      }
    })();
    return request.promise;
  };

  const loadHistory = (owner, page = 1) => {
    setOwner(owner);
    if (owner === null) return Promise.resolve(null);
    if (historyRequest?.page === page) return historyRequest.promise;
    if (state.history?.current_page === page && !historyRequest) return Promise.resolve(state.history);
    const request = {page};
    historyRequest = request;
    request.promise = (async () => {
      try {
        const history = await transport.history(page);
        if (historyRequest !== request) return null;
        state.history = history;
        return history;
      } catch (error) {
        if (historyRequest === request) throw error;
        return null;
      } finally {
        if (historyRequest === request) historyRequest = undefined;
      }
    })();
    return request.promise;
  };

  const requestView = (owner, page, {includeHistory = false, selection = {}} = {}, force = false) => {
    const home = loadHome(owner, selection, force);
    if (!includeHistory || owner === null) return home;
    return Promise.all([home, loadHistory(owner, page)]).then(([result, history]) =>
      result && state.owner === owner ? {home: result.home, history} : null);
  };
  const load = (owner, page = 1, options) => requestView(owner, page, options);
  const refresh = (owner, page = 1, options) => requestView(owner, page, options, true);

  const serialize = (owner, operation) => {
    setOwner(owner);
    if (action) return action;
    const token = generation;
    state.busy = true;
    state.error = '';
    action = Promise.resolve().then(() => operation(token)).catch(error => {
      if (token === generation) state.error = error.response?.data?.message ?? error.message;
      throw error;
    }).finally(() => {
      if (token === generation) {
        action = undefined;
        state.busy = false;
      }
    });
    return action;
  };
  const assertOwner = token => {
    if (token !== generation) throw new Error('Аккаунт изменился. Откройте практику заново.');
  };
  const start = (owner, sessionId = null, notify = () => {}) => serialize(owner, async token => {
    assertOwner(token);
    await load(owner, 1, {selection: currentSelection});
    assertOwner(token);
    const home = state.home;
    const active = sessionId === null ? home.active_run?.id
      : home.route?.lessons.find(lesson => lesson.id === sessionId)?.run_id;
    if (owner !== null) {
      const pending = await accountRepository.pending(owner, null);
      assertOwner(token);
      if (!pending.length && active) return '/daily/run/' + active;
      if (!pending.length && !sessionId && !home.candidate) throw new Error('Новых доступных занятий пока нет.');
      const profile = home.profile;
      const onStatus = value => {
        if (token !== generation) return;
        state.saving = value;
        notify(value);
      };
      const onMutation = () => { if (token === generation) invalidate(owner); };
      const view = pending.length
        ? await deliverAccountQueue({ownerUserId: owner, runId: null, repository: accountRepository, transport, profile, onStatus, onMutation})
        : await startAccountRun({
            ownerUserId: owner, profile, repository: accountRepository, transport, onStatus, onMutation,
            input: {client_event_id: idFactory(), expected_revision: profile.revision, route_start: true,
              timezone: profile.persisted === false ? browserTimezone() : profile.timezone,
              ...(sessionId ? {kind: 'extra', session_id: sessionId} : {kind: 'daily'})},
          });
      assertOwner(token);
      invalidate(owner);
      return '/daily/run/' + view.run_id;
    }
    if (active) return '/daily/guest/' + active;
    const lessonId = sessionId ?? home.candidate?.id;
    if (!lessonId) throw new Error(home.content_error || 'Новых доступных занятий пока нет.');
    let source;
    try { source = await guestTransport.session(lessonId); }
    catch (failure) {
      throw Object.assign(new Error(failure.message, {cause: failure}), {code: 'content_load'});
    }
    assertOwner(token);
    const runId = idFactory();
    const input = {source, runId, presentationIds: source.snapshot.tasks.map(() => idFactory()),
      eventId: idFactory(), tabId: idFactory(), now: now(), kind: sessionId ? 'extra' : 'daily'};
    const chosen = await guestRepository.mutate(local => {
      assertOwner(token);
      const existing = activeGuestRun(local.runs, local.current_level, sessionId);
      if (existing) return existing.id;
      addGuestRun(local, input);
      return runId;
    }, {run_id: runId});
    assertOwner(token);
    const local = await guestRepository.read();
    assertOwner(token);
    updateGuest(local);
    return '/daily/guest/' + chosen;
  });
  const loadReviewPlan = async (owner, filters = {}) => {
    setOwner(owner);
    const token = generation;
    if (owner !== null) {
      const plan = await transport.reviewPlan(filters);
      assertOwner(token);
      if (plan.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
      return plan;
    }
    await guestRepository.archiveBound();
    assertOwner(token);
    const local = await guestRepository.initialize() ?? await guestRepository.read();
    assertOwner(token);
    if (local.bound_user_id != null) throw new Error('Продолжите в связанном аккаунте.');
    return guestShortReviewPlan(local, now(), 'manual', filters);
  };

  const startReview = (owner, {mode = 'manual', filters = {}, wordIds, onStatus = () => {}} = {}) => serialize(owner, async token => {
    assertOwner(token);
    if (owner !== null) {
      const profile = (state.ready ? state.home?.profile : null) ?? await transport.profile();
      assertOwner(token);
      if (profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
      const input = {
        client_event_id: idFactory(), expected_revision: profile.revision, kind: 'review', review_mode: mode,
        timezone: profile.persisted ? profile.timezone : browserTimezone(),
      };
      if (mode === 'manual' && wordIds?.length) input.word_ids = [...wordIds];
      const view = await startAccountRun({
        ownerUserId: owner, profile, input, repository: accountRepository, transport,
        onStatus: status => { if (token === generation) onStatus(status); },
        onMutation: () => { if (token === generation) invalidate(owner); },
      });
      assertOwner(token);
      invalidate(owner);
      return '/daily/run/' + view.run_id;
    }

    const instant = now();
    const runId = idFactory();
    await guestRepository.mutate(local => {
      assertOwner(token);
      const source = guestShortReviewSource(local, instant, idFactory(), idFactory, mode, filters);
      addGuestRun(local, {source, runId, presentationIds: source.snapshot.tasks.map(() => idFactory()),
        eventId: idFactory(), tabId: idFactory(), now: instant, kind: 'review'});
    }, {run_id: runId});
    assertOwner(token);
    invalidate(null);
    return '/daily/guest/' + runId;
  });

  const changeLevel = (owner, level) => serialize(owner, async token => {
    assertOwner(token);
    if(!state.ready) await load(owner);
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
    invalidate(owner);
    await refresh(owner, 1, {selection: currentSelection});
  });
  return {
    state, load, refresh, loadHistory, invalidate, updateGuest, start, loadReviewPlan, startReview, changeLevel, accountRepository, guestRepository, setOwner,
    searchGuest: (query, completedOnly) => searchGuestRoute(state.guest, catalog, query, completedOnly),
    resetProfile: () => { profileRequest = undefined; state.profilePending = false; },
  };
};
