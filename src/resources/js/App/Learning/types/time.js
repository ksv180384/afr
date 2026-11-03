import { PRACTICE_CONTRACT } from './index.js';

const validateTimezone = (timezone) => {
  if (typeof timezone !== 'string' || !timezone || /^[+-]/.test(timezone)) {
    throw new RangeError('Expected an IANA timezone');
  }
  return new Intl.DateTimeFormat('en', { timeZone: timezone }).resolvedOptions().timeZone;
};

// Call after mounting: no browser API is evaluated during module import / SSR.
export const guestTimezone = (detect = () => new Intl.DateTimeFormat().resolvedOptions().timeZone) => {
  try {
    return { timezone: validateTimezone(detect()), usedFallback: false, message: null };
  } catch {
    return {
      timezone: PRACTICE_CONTRACT.time.guest_fallback_timezone,
      usedFallback: true,
      message: PRACTICE_CONTRACT.time.guest_fallback_message,
    };
  }
};

// instant is a Date supplied by the caller; account dates use a server-supplied instant.
export const localDate = (instant, timezone) => {
  if (!(instant instanceof Date) || Number.isNaN(instant.getTime())) {
    throw new RangeError('Expected a valid instant');
  }
  const parts = new Intl.DateTimeFormat('en', {
    timeZone: validateTimezone(timezone),
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(instant);
  const part = (type) => parts.find((item) => item.type === type).value;
  return part('year') + '-' + part('month') + '-' + part('day');
};
