import { browserTimezone } from './practiceTime.js';
import { changeGuestReviewSettings, reviewSuggestionsView } from './reviewSuggestions.js';

const failureMessage = failure => failure.response?.data?.message ?? failure.message;

export const createPracticeSettingsController = ({
  transport, guestRepository, getOwner, onMutation = () => {}, state = {},
  now = () => new Date().toISOString(), idFactory = () => crypto.randomUUID(),
}) => {
  const emptyState = { busy: false, pending: false, profile: null, error: '', operation: null };
  Object.assign(state, emptyState);
  let owner;
  let generation = 0;
  let read;
  let request;

  const checkOwner = () => {
    const next = getOwner();
    if (next !== owner) {
      owner = next;
      generation++;
      read = undefined;
      request = undefined;
      Object.assign(state, emptyState);
    }
    return generation;
  };

  const guard = token => {
    if (checkOwner() !== token) {
      throw Object.assign(new Error('Аккаунт изменился. Повторите действие в нужном аккаунте.'), { code: 'owner_changed' });
    }
  };

  const accept = (profile, token) => {
    guard(token);
    if (owner !== null && profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
    if (!state.profile || profile.revision >= state.profile.revision) state.profile = profile;
    return state.profile;
  };

  const guestProfile = (local, instant) => ({
    timezone: local.timezone,
    revision: local.review_suggestions.revision,
    review_suggestions: reviewSuggestionsView(local.review_suggestions, instant),
  });

  const readProfile = token => {
    if (read) return read;
    const result = (owner === null ? guestRepository.read().then(local => guestProfile(local, now())) : transport.profile())
      .then(profile => accept(profile, token))
      .finally(() => { if (read === result) read = undefined; });
    read = result;
    return result;
  };

  const load = async () => {
    const token = checkOwner();
    if (state.busy || state.pending) return state.profile;
    state.operation = 'load';
    state.error = '';
    try {
      return await readProfile(token);
    } catch (failure) {
      if (token === generation && !state.busy) state.error = failureMessage(failure);
      throw failure;
    }
  };

  const save = async (action, value) => {
    const token = checkOwner();
    if (state.busy) return;
    const retry = !!request;
    const operation = request?.operation ?? (action === 'level' ? 'level' : 'review');
    state.operation = operation;
    state.busy = true;
    state.error = '';
    const actor = owner;
    try {
      const profile = state.profile ?? await readProfile(token);
      guard(token);
      request ??= {
        operation,
        input: {
          client_event_id: idFactory(),
          expected_revision: profile.revision,
          ...(operation === 'level'
            ? { current_level: value, timezone: profile.timezone_configured === false ? browserTimezone() : profile.timezone }
            : { action, ...(action === 'toggle' ? { enabled: value } : {}) }),
        },
      };
      state.pending = true;
      const input = request.input;

      if (actor === null) {
        const saved = await guestRepository.mutate(local => {
          guard(token);
          const instant = now();
          changeGuestReviewSettings(local, input, instant);
          return guestProfile(local, instant);
        }, { profile: true, review_settings: true });
        accept(saved, token);
      } else {
        const options = { headers: { 'X-Practice-Owner': String(actor) } };
        const receipt = await (operation === 'level'
          ? transport.updateProfile(input, options)
          : transport.updateReviewSettings(input, options));
        accept(receipt.profile, token);
        if (retry) {
          // A replay can return a receipt older than a later edit on another device.
          await readProfile(token).catch(() => {});
          guard(token);
        }
      }

      request = undefined;
      state.pending = false;
      onMutation(actor);
      return state.profile;
    } catch (failure) {
      if (token !== generation) throw failure;
      const status = failure.response?.status;
      const conflict = failure.response?.data?.code === 'revision_conflict' || failure.code === 'revision_conflict';
      if ((status && status < 500 && status !== 429) || ['revision_conflict', 'bound_owner', 'validation_failed', 'owner_changed', 'idempotency_conflict'].includes(failure.code)) {
        request = undefined;
        state.pending = false;
      }
      if (conflict) {
        try {
          const latest = failure.response?.data?.state?.profile;
          if (latest) accept(latest, token);
          else await readProfile(token);
          failure = new Error('Настройки уже обновились. Проверьте значение и повторите выбор.');
        } catch (refreshFailure) {
          failure = refreshFailure;
          if (token === generation) {
            state.profile = null;
            state.operation = 'load';
          }
        }
      }
      if (token === generation) state.error = failureMessage(failure);
      throw failure;
    } finally {
      if (token === generation) state.busy = false;
    }
  };

  return { state, load, save, saveLevel: level => save('level', level) };
};
