import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import { createHeadManager } from '@inertiajs/core';

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
