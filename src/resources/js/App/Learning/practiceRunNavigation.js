import { router } from '@inertiajs/vue3';

let returnScroll;
const runPath = /^\/daily\/(run|guest)\/[0-9a-f-]+$/i;

export function visitPracticeRun(path, route, selection = {}) {
  const query = new URLSearchParams();
  if (runPath.test(window.location.pathname)) {
    const current = new URLSearchParams(window.location.search);
    for (const key of ['from_level', 'from_unit', 'from_scroll']) {
      if (current.has(key)) query.set(key, current.get(key));
    }
  } else {
    const level = route?.level?.id ?? selection.level;
    const unit = route?.unit?.id ?? selection.unit;
    if (level) query.set('from_level', level);
    if (unit) query.set('from_unit', unit);
    if (level || unit) query.set('from_scroll', String(Math.round(window.scrollY)));
  }
  const url = query.size ? path + '?' + query.toString() : path;
  return new Promise(resolve => router.visit(url, {onFinish: () => resolve()}));
}

export function returnToPractice() {
  const current = new URLSearchParams(window.location.search);
  const selection = new URLSearchParams();
  const level = current.get('from_level');
  const unit = current.get('from_unit');
  if (level && level.length <= 128) selection.set('level', level);
  if (unit && unit.length <= 128) selection.set('unit', unit);
  const path = selection.size ? '/practice?' + selection.toString() : '/practice';
  const scroll = Number(current.get('from_scroll'));
  const position = {path, scroll: Number.isFinite(scroll) ? Math.max(0, scroll) : 0};
  returnScroll = position;
  router.visit(path, {
    preserveScroll: true,
    onCancel: () => { if (returnScroll === position) returnScroll = undefined; },
  });
}

export function restorePracticeScroll() {
  const position = returnScroll;
  if (!position || window.location.pathname + window.location.search !== position.path) return;
  returnScroll = undefined;
  requestAnimationFrame(() => window.scrollTo(0, position.scroll));
  return true;
}
