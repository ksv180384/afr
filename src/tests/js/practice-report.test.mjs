import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';

test('report form requires the authenticated page identity',async()=>{
  const root=fileURLToPath(new URL('../../',import.meta.url));
  const server=await createServer({configFile:false,root,cacheDir:root+'node_modules/.vite-tests/practice-report',optimizeDeps:{noDiscovery:true,include:[]},plugins:[vue()],resolve:{alias:{'@':root+'resources/js'}},server:{middlewareMode:true,hmr:false},appType:'custom',logLevel:'error'});
  try {
    const {default:Report}=await server.ssrLoadModule('/resources/js/App/Components/Learning/PracticeReport.vue');
    const render=async user=>{
      const result=await createInertiaApp({page:{component:'ReportAccess',url:'/daily',version:'test',props:{auth:{user}}},resolve:()=>Report,render:renderToString,
        setup:({App,props,plugin})=>createSSRApp({render:()=>h(App,props)}).use(plugin)});
      return result.body;
    };
    assert.doesNotMatch(await render(null),/<form|<textarea|Сообщить об ошибке/);
    const authorized=await render({id:7});
    assert.match(authorized,/<form/);
    assert.match(authorized,/Сообщить об ошибке/);
    assert.match(authorized,/Отправить сообщение/);
    assert.doesNotMatch(await render(null),/<form|<textarea|Сообщить об ошибке/);
  } finally { await server.close(); }
});
