import { guestTimezone } from './types/time.js';
import { PRACTICE_CONTRACT } from './types/index.js';
export const DEFAULT_TIMEZONE = PRACTICE_CONTRACT.time.guest_fallback_timezone;

export const browserTimezone = () => guestTimezone().timezone;

const plural = (value, forms) => forms[value % 100 >= 11 && value % 100 <= 14 ? 2 : value % 10 === 1 ? 0 : value % 10 >= 2 && value % 10 <= 4 ? 1 : 2];
const pad = value => String(value).padStart(2, '0');

export const practiceDate = (value, { timezone = DEFAULT_TIMEZONE, time = true, relative = true, now = Date.now() } = {}) => {
  if (!value) return { text: '', title: '' };
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [year, month, day] = value.split('-');
    return { text: `${day}.${month}.${year}`, title: `${day}.${month}.${year}` };
  }
  const date = new Date(value);
  if (!Number.isFinite(date.getTime())) return { text: '', title: '' };
  let parts;
  try { parts = new Intl.DateTimeFormat('en-GB', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: 'numeric', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(date); }
  catch { return practiceDate(value, { timezone: DEFAULT_TIMEZONE, time, relative, now }); }
  const p = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
  const fullDate = `${p.day}.${p.month}.${p.year}`;
  const title = `${fullDate} ${Number(p.hour)}:${pad(p.minute)}:${pad(p.second)}`;
  const elapsed = now - date.getTime();
  if (relative && elapsed >= 0 && elapsed < 86400000) {
    const minutes = Math.floor(elapsed / 60000);
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    const text = [hours ? `${hours} ${plural(hours, ['час', 'часа', 'часов'])}` : '', remainder ? `${remainder} ${plural(remainder, ['минуту', 'минуты', 'минут'])}` : ''].filter(Boolean).join(' ');
    return { text: text ? text + ' назад' : 'менее минуты назад', title };
  }
  return { text: time ? title : fullDate, title };
};

export const timezoneLabel = (timezone, instant = new Date()) => {
  const cities = { 'Europe/Moscow':'Москва', 'Europe/Kaliningrad':'Калининград', 'Europe/Samara':'Самара', 'Asia/Yekaterinburg':'Екатеринбург', 'Asia/Novosibirsk':'Новосибирск', 'Asia/Krasnoyarsk':'Красноярск', 'Asia/Irkutsk':'Иркутск', 'Asia/Yakutsk':'Якутск', 'Asia/Vladivostok':'Владивосток', 'Asia/Magadan':'Магадан', 'Asia/Kamchatka':'Камчатка', 'Europe/Paris':'Париж', 'UTC':'UTC' };
  const title = cities[timezone] ? (timezone==='UTC' ? 'UTC' : `${cities[timezone]} — ${timezone}`) : timezone.replaceAll('_',' ');
  try {
    const offset = new Intl.DateTimeFormat('en', {timeZone:timezone,timeZoneName:'shortOffset'}).formatToParts(instant).find(part=>part.type==='timeZoneName').value.replace(/^GMT/,'UTC');
    return `(${offset==='UTC' ? 'UTC+0' : offset}) ${title}`;
  } catch { return title; }
};
