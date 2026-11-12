import { logicalKey } from './accountRepository.js';

export const defaultReviewSuggestions = () => ({enabled:true,snoozed_until:null,revision:1});
const error = (message, code = 'validation_failed') => Object.assign(new Error(message), {code});

// Search a calendar-day boundary, not an elapsed 24-hour deadline. This also handles skipped midnight.
export const nextLocalMidnight = (instant, timezone) => {
  const formatter = new Intl.DateTimeFormat('en-CA', {timeZone:timezone,year:'numeric',month:'2-digit',day:'2-digit'});
  const key = value => { const p=Object.fromEntries(formatter.formatToParts(new Date(value)).map(x=>[x.type,x.value])); return `${p.year}-${p.month}-${p.day}`; };
  let left = new Date(instant).getTime();
  if (!Number.isFinite(left)) throw error('Неверное время.');
  const today = key(left);
  let right = left + 72*60*60*1000; // Search bound only; the returned duration depends on the local calendar.
  while (right-left>1) { const middle=Math.floor((left+right)/2); if(key(middle)===today) left=middle; else right=middle; }
  return new Date(right).toISOString().replace('.000Z','+00:00');
};

export const reviewSuggestionsView = (settings, instant) => ({...settings,available:true,
  can_suggest:settings.enabled && (!settings.snoozed_until || new Date(settings.snoozed_until).getTime()<=new Date(instant).getTime())});

export const changeGuestReviewSettings = (state, input, instant) => {
  if (state.bound_user_id != null) throw error('Продолжите в связанном аккаунте.', 'bound_owner');
  if (!['toggle','snooze_today'].includes(input.action) || (input.action==='toggle' && typeof input.enabled!=='boolean')) throw error('Проверьте настройку повторения.');
  const fingerprint = logicalKey(input);
  state.review_settings_receipts ??= {};
  const previous = state.review_settings_receipts[input.client_event_id];
  if (previous) {
    if (previous.fingerprint!==fingerprint) throw error('ID уже использован с другим содержимым.', 'idempotency_conflict');
    return structuredClone(previous.settings);
  }
  const settings = state.review_suggestions;
  if (settings.revision!==input.expected_revision) throw error('Настройки изменены в другой вкладке. Повторите выбор.', 'revision_conflict');
  if (input.action==='toggle') settings.enabled=input.enabled;
  else {
    const until=nextLocalMidnight(instant,state.timezone);
    if (!settings.snoozed_until || new Date(until)>new Date(settings.snoozed_until)) settings.snoozed_until=until;
  }
  settings.revision++;
  state.review_settings_receipts[input.client_event_id]={fingerprint,settings:structuredClone(settings)};
  return structuredClone(settings);
};
