import test from 'node:test';
import assert from 'node:assert/strict';
import { createPracticeNotifications, practiceStatusNotification } from '../../resources/js/App/Learning/practiceNotifications.js';

const setup = (options = {}) => {
  let time = 0;
  let serial = 0;
  const jobs = new Map();
  const notifications = createPracticeNotifications({
    ...options, now: () => time,
    schedule: (callback, delay) => { jobs.set(++serial, { at: time + delay, callback }); return serial; },
    cancel: id => jobs.delete(id),
  });
  const advance = duration => {
    const target = time + duration;
    while (true) {
      const next = [...jobs].sort((a, b) => a[1].at - b[1].at)[0];
      if (!next || next[1].at > target) break;
      time = next[1].at;
      jobs.delete(next[0]);
      next[1].callback();
    }
    time = target;
  };
  return { notifications, advance, jobs };
};

test('form feedback replaces its own toast and closing removes it immediately', () => {
  const { notifications, advance } = setup();
  const first = notifications.notify('settings', { type: 'error', message: 'Не удалось сохранить' });
  const updated = notifications.notify('settings', { type: 'success', message: 'Настройки сохранены' });
  assert.equal(updated, first);
  assert.equal(notifications.items.length, 1);
  assert.equal(notifications.items[0].message, 'Настройки сохранены');
  notifications.dismiss(first);
  assert.equal(notifications.items.length, 0);
  const repeated = notifications.notify('settings', { type: 'success', message: 'Настройки сохранены' });
  assert.notEqual(repeated, first);
  advance(6500);
  assert.equal(notifications.items.length, 0);
});

test('successful notifications hide automatically while errors wait for dismissal', () => {
  const { notifications, advance } = setup();
  notifications.notify('settings', { type: 'success', message: 'Сохранено' });
  const error = notifications.notify('report', { type: 'error', message: 'Повторите отправку' });
  advance(60000);
  assert.deepEqual(notifications.items.map(item => item.id), [error]);
  notifications.dismiss(error);
  assert.equal(notifications.items.length, 0);
});

test('hover and keyboard focus pause the remaining reading time', () => {
  const { notifications, advance } = setup();
  const id = notifications.notify('vocabulary', { type: 'success', message: 'Отметка сохранена' });
  advance(1000);
  notifications.pause(id);
  notifications.pause(id);
  advance(30000);
  assert.equal(notifications.items.length, 1);
  notifications.resume(id);
  notifications.resume(id);
  advance(5499);
  assert.equal(notifications.items.length, 1);
  advance(1);
  assert.equal(notifications.items.length, 0);
});

test('a page change retains the completion receipt and removes obsolete recovery errors', () => {
  const { notifications } = setup();
  notifications.notify('report', { type: 'success', message: 'Сообщение получено. Номер: 12.' });
  notifications.notify('session', { type: 'error', message: 'Повторите запрос' });
  notifications.release('report');
  notifications.release('session');
  assert.deepEqual(notifications.items.map(item => item.source), ['report']);
});

test('the stack is bounded and clearing it cancels every timeout', () => {
  const { notifications, jobs } = setup({ limit: 2 });
  for (let i = 0; i < 4; i++) notifications.notify('form-' + i, { type: 'success', message: 'Сохранено ' + i });
  assert.equal(notifications.items.length, 2);
  assert.equal(jobs.size, 2);
  notifications.clear();
  assert.equal(notifications.items.length, 0);
  assert.equal(jobs.size, 0);
});

test('autosave does not interrupt typing and actionable storage failures remain visible', () => {
  for (const kind of ['draft', 'loading', 'sending', 'syncing', 'idle']) {
    assert.equal(practiceStatusNotification({ kind, message: 'Сохранение…' }), null);
  }
  assert.equal(practiceStatusNotification({ kind: 'storage', message: 'Не закрывайте страницу' }).type, 'error');
  assert.equal(practiceStatusNotification({ kind: 'pending', message: 'Ожидаем соединения' }).type, 'warning');
  assert.equal(practiceStatusNotification({ kind: 'confirmed', message: 'Подтверждено' }).type, 'success');
});
