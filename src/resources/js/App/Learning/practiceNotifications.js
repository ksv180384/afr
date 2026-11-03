import { shallowReactive } from 'vue';

export const practiceNotificationsKey = Symbol('practiceNotifications');

const silentKinds = new Set(['idle', 'loading', 'sending', 'syncing', 'draft']);
const successKinds = new Set(['success', 'saved', 'confirmed', 'synced']);
const warningKinds = new Set(['warning', 'pending', 'auth', 'conflict', 'temporary', 'bound', 'partial']);

export const practiceStatusNotification = (status) => {
  if (!status?.message || silentKinds.has(status.kind)) return null;
  const type = successKinds.has(status.kind) ? 'success'
    : warningKinds.has(status.kind) ? 'warning'
      : ['error', 'storage'].includes(status.kind) ? 'error' : 'info';
  return { type, message: status.message };
};

export const createPracticeNotifications = ({
  now = () => Date.now(), schedule = setTimeout, cancel = clearTimeout, limit = 4,
} = {}) => {
  const items = shallowReactive([]);
  const timers = new Map();
  let serial = 0;
  const stopTimer = (id) => {
    const timer = timers.get(id);
    if (timer?.handle !== undefined) cancel(timer.handle);
    timers.delete(id);
  };
  const dismiss = (id) => {
    stopTimer(id);
    const index = items.findIndex(item => item.id === id);
    if (index !== -1) items.splice(index, 1);
  };
  const startTimer = (id, duration) => {
    stopTimer(id);
    if (duration <= 0) return;
    timers.set(id, { remaining: duration, deadline: now() + duration, handle: schedule(() => dismiss(id), duration) });
  };
  const notify = (source, notification) => {
    if (!notification?.message) return;
    const previous = items.find(item => item.source === source);
    const item = { ...notification, source, id: previous?.id ?? ++serial };
    if (previous) items.splice(items.indexOf(previous), 1, item);
    else items.push(item);
    startTimer(item.id, notification.duration ?? (['success', 'info'].includes(item.type) ? 6500 : 0));
    while (items.length > limit) dismiss(items[0].id);
    return item.id;
  };
  const clearSource = (source) => {
    const item = items.find(item => item.source === source);
    if (item) dismiss(item.id);
  };
  return {
    items, notify, dismiss, clearSource,
    source: () => 'practice-' + ++serial,
    release(source) {
      const item = items.find(item => item.source === source);
      if (item && !['success', 'info'].includes(item.type)) dismiss(item.id);
    },
    pause(id) {
      const timer = timers.get(id);
      if (!timer || timer.handle === undefined) return;
      cancel(timer.handle);
      timer.remaining = Math.max(0, timer.deadline - now());
      timer.handle = undefined;
    },
    resume(id) {
      const timer = timers.get(id);
      if (!timer || timer.handle !== undefined) return;
      if (timer.remaining <= 0) dismiss(id);
      else startTimer(id, timer.remaining);
    },
    clear() { [...items].forEach(item => dismiss(item.id)); },
  };
};
