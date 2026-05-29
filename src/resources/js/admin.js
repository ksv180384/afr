import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ElementPlus from 'element-plus';

import 'element-plus/dist/index.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const resetBodyMargin = () => {
  if (document.body.style.margin !== '0px') {
    document.body.style.margin = '0';
  }

  if (document.body.style.marginBottom !== '0px') {
    document.body.style.marginBottom = '0';
  }
};

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent(
      `./Admin/Pages/${name}.vue`,
      import.meta.glob('./Admin/Pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    resetBodyMargin();

    const bodyStyleObserver = new MutationObserver(resetBodyMargin);
    bodyStyleObserver.observe(document.body, {
      attributes: true,
      attributeFilter: ['style'],
    });

    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .use(ElementPlus)
      .mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});
