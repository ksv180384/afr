import { router } from '@inertiajs/vue3';

let returnScroll;
let panelHistoryHandler;
const runPath = /^\/daily\/(run|guest)\/[0-9a-f-]+$/i;

export function bindPracticePanelHistory(handler) {
  panelHistoryHandler = handler;
  return () => { if (panelHistoryHandler === handler) panelHistoryHandler = undefined; };
}

export function handlePracticeHistory(event) {
  panelHistoryHandler?.(event);
}

export function practiceTopicFromHash(hash) {
  if (!hash.startsWith('#topic-')) return null;
  try { return decodeURIComponent(hash.slice('#topic-'.length)) || null; }
  catch { return null; }
}

export function visitPracticeRun(path, selection = {}) {
  const query = new URLSearchParams();
  if (runPath.test(window.location.pathname)) {
    const current = new URLSearchParams(window.location.search);
    for (const key of ['from_level', 'from_unit', 'from_scroll']) {
      if (current.has(key)) query.set(key, current.get(key));
    }
  } else {
    const {level} = selection;
    const unit = (window.location.pathname === '/practice' && practiceTopicFromHash(window.location.hash)) || selection.unit;
    if (level) query.set('from_level', level);
    if (unit) query.set('from_unit', unit);
    if (level || unit) query.set('from_scroll', String(Math.round(window.scrollY)));
  }
  const url = query.size ? path + '?' + query.toString() : path;
  return new Promise(resolve => router.visit(url, {onFinish: () => resolve()}));
}

export function practiceReturnDestination(search = window.location.search) {
  const current = new URLSearchParams(search);
  const selection = {};
  for (const key of ['level', 'unit']) {
    const value = current.get('from_' + key) ?? current.get(key);
    if (value && value.length <= 128) selection[key] = value;
  }
  const query = new URLSearchParams(selection);
  const position = current.get('from_scroll');
  const scroll = query.size && position ? Number(position) : NaN;
  return {
    path: query.size ? '/practice?' + query.toString() : '/practice',
    selection,
    scroll: Number.isFinite(scroll) ? Math.max(0, scroll) : null,
  };
}

export async function returnToPractice(store, owner) {
  const source = window.location.href;
  const destination = practiceReturnDestination();
  try { await store.load(owner, 1, {selection: destination.selection}); }
  catch { /* The destination can show its own loading error. */ }
  if (window.location.href !== source) return;
  returnScroll = destination.scroll === null ? undefined : destination;
  return new Promise(resolve => router.visit(destination.path, {
    preserveScroll: returnScroll === destination,
    onCancel: () => { if (returnScroll === destination) returnScroll = undefined; },
    onFinish: resolve,
  }));
}

export function restorePracticeScroll() {
  const position = returnScroll;
  if (!position || window.location.pathname + window.location.search !== position.path) return false;
  returnScroll = undefined;
  window.scrollTo({top: position.scroll, left: 0, behavior: 'instant'});
  return true;
}
