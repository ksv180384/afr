import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createRenderer, createSSRApp, h, nextTick, reactive, ssrContextKey } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createHeadManager } from '@inertiajs/core';
import { restorePracticeScroll, returnToPractice, visitPracticeRun } from '../../resources/js/App/Learning/practiceRunNavigation.js';

test('return from a lesson waits for the selected route and restores its scroll once', async (context) => {
  const origin = 'http://localhost:5000';
  const scrolls = [];
  globalThis.window = {
    location: new URL('/daily/run/d0f0?from_level=PRE_A1&from_unit=PRE_A1-U02&from_scroll=720', origin),
    scrollTo: value => scrolls.push(value),
  };
  context.after(() => { delete globalThis.window; });
  const visits = [];
  context.mock.method(router, 'visit', (path, options) => {
    assert.equal(options.preserveScroll, true);
    visits.push(path);
    window.location = new URL(path, origin);
    options.onFinish();
  });
  let resolve;
  const pendingRoute = new Promise(done => { resolve = done; });
  const store = {load(owner, page, options) {
    assert.equal(owner,7);
    assert.equal(page,1);
    assert.deepEqual(options.selection,{level:'PRE_A1',unit:'PRE_A1-U02'});
    return pendingRoute;
  }};
  const returning = returnToPractice(store, 7);
  assert.deepEqual(visits, []);
  resolve();
  await returning;
  assert.deepEqual(visits, ['/practice?level=PRE_A1&unit=PRE_A1-U02']);
  assert.equal(restorePracticeScroll(), true);
  assert.equal(restorePracticeScroll(), false);
  assert.deepEqual(scrolls, [{top:720,left:0,behavior:'instant'}]);
});

test('return to the main practice route leaves scrolling to the current lesson', async (context) => {
  const origin = 'http://localhost:5000';
  globalThis.window = { location: new URL('/practice', origin), scrollY: 720 };
  context.after(() => { delete globalThis.window; });
  const visits = [];
  context.mock.method(router, 'visit', (path, options) => {
    visits.push([path, options.preserveScroll]);
    window.location = new URL(path, origin);
    options.onFinish();
  });
  await visitPracticeRun('/daily/run/d0f0');
  window.location.search = '?from_scroll=720';
  await returnToPractice({load: (_, __, {selection}) => assert.deepEqual(selection, {})}, 7);
  assert.deepEqual(visits, [['/daily/run/d0f0', undefined], ['/practice', false]]);
  assert.equal(restorePracticeScroll(), false);
});

test('the practice path uses the latest target after Inertia resets scroll on navigation', async (context) => {
  const root = fileURLToPath(new URL('../../', import.meta.url));
  const server = await createServer({
    configFile: false, root, cacheDir: root + 'node_modules/.vite-tests/practice-path-scroll',
    optimizeDeps: { noDiscovery: true, include: [] }, plugins: [vue()],
    resolve: { alias: { '@': root + 'resources/js' } },
    server: { middlewareMode: true, hmr: false }, appType: 'custom', logLevel: 'error',
  });
  context.after(() => server.close());
  const { default: pathComponent } = await server.ssrLoadModule('/resources/js/App/Components/Learning/PracticeLevelPath.vue');
  const listeners = new Map();
  const frames = new Map();
  let frameId = 0;
  globalThis.window = {
    location: new URL('http://localhost:5000/practice'),
    addEventListener: (event, callback) => listeners.set(event, callback),
    removeEventListener: event => listeners.delete(event),
  };
  globalThis.document = { readyState: 'interactive' };
  globalThis.requestAnimationFrame = callback => (frames.set(++frameId, callback), frameId);
  globalThis.cancelAnimationFrame = id => frames.delete(id);
  const nextFrame = () => {
    const callbacks = [...frames.values()];
    frames.clear();
    for (const callback of callbacks) callback();
  };
  const scrolls = [];
  const route = reactive({ level: {id: 'PRE_A1'}, topics: [], current_lesson_id: null });
  const container = {querySelectorAll: () => route.topics.flatMap(topic => topic.lessons.map(lesson => ({
    dataset: {lesson: lesson.id}, scrollIntoView: options => scrolls.push([lesson.id, options]),
  })))};
  const renderer = createRenderer({createElement: () => container, createComment: () => ({}),
    insert() {}, remove() {}, patchProp() {}, parentNode: () => null, nextSibling: () => null});
  const app = renderer.createApp({...pathComponent, render: () => h('div', {ref: 'path'})}, {route});
  app.provide(ssrContextKey, {});
  app.mount({});
  try {
    assert.equal(frames.size, 0);
    document.readyState = 'complete';
    listeners.get('load')();
    route.topics = [{id: 'unit', lessons: [{id: 'first', status: 'completed'}, {id: 'current', status: 'available'}]}];
    route.current_lesson_id = 'current';
    await nextTick();
    assert.equal(frames.size, 1);
    // Inertia queues its reset after Vue mounts the cached route.
    requestAnimationFrame(() => requestAnimationFrame(() => scrolls.push(['reset', 0])));
    nextFrame();
    nextFrame();
    assert.deepEqual(scrolls, [['reset', 0]]);
    nextFrame();
    assert.deepEqual(scrolls, [['reset', 0], ['current', {block: 'center', behavior: 'instant'}]]);
  } finally {
    app.unmount();
    for (const key of ['window', 'document', 'requestAnimationFrame', 'cancelAnimationFrame']) delete globalThis[key];
  }
});

test('practice and home metadata replace each other without retaining a previous canonical or robots tag', async () => {
  const root = fileURLToPath(new URL('../../', import.meta.url));
  const server = await createServer({
    configFile: false, root, cacheDir: root + 'node_modules/.vite-tests/practice-navigation',
    optimizeDeps: { noDiscovery: true, include: [] }, plugins: [vue()],
    server: { middlewareMode: true, hmr: false }, appType: 'custom', logLevel: 'error',
  });
  try {
    const { default: SeoHead } = await server.ssrLoadModule('/resources/js/App/Components/Seo/SeoHead.vue');
    const renderHead = async (url, seo) => {
      const rendered = await createInertiaApp({
        page: { component: 'NavigationProbe', props: { seo, ziggy: { location: url } }, url, version: null },
        resolve: () => ({ props: ['seo'], render() { return h(SeoHead, this.seo); } }),
        render: renderToString,
        setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
      });
      return rendered.head;
    };
    const practiceUrl = 'http://localhost:5000/practice?q=bonjour';
    const homeUrl = 'http://localhost:5000/';
    const practiceHead = await renderHead(practiceUrl, {
      title: 'Практика', description: 'Занятия по уровням', canonicalUrl: practiceUrl,
      robots: 'noindex, follow', jsonLd: { '@type': 'ItemList' },
    });
    const homeHead = await renderHead(homeUrl, {
      title: 'Главная', description: 'Французский язык', jsonLd: { '@type': 'WebSite' },
    });
    let currentHead = [];
    const manager = createHeadManager(true, title => title, elements => { currentHead = elements; });
    const provider = manager.createProvider();
    provider.update(practiceHead);
    assert.match(currentHead.join(''), /noindex, follow/);
    assert.match(currentHead.join(''), /ItemList/);
    provider.update(homeHead);
    const html = currentHead.join('');
    assert.doesNotMatch(html, /noindex|\/practice|ItemList/);
    assert.match(html, /WebSite/);
    assert.equal(currentHead.filter(tag => tag.includes('rel="canonical"')).length, 1);
    assert.match(currentHead.find(tag => tag.includes('rel="canonical"')), /href="http:\/\/localhost:5000\/"/);
    assert.equal(currentHead.filter(tag => tag.includes('name="description"')).length, 1);
    provider.update(practiceHead);
    assert.match(currentHead.join(''), /noindex, follow/);
    assert.doesNotMatch(currentHead.join(''), /WebSite/);
    provider.disconnect();
  } finally {
    await server.close();
  }
});
