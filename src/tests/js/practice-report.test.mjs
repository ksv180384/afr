import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import { reportAnswerText } from '../../resources/js/App/Learning/playerUi.js';

test('answer previews show the entered text and selected labels, and ignore empty drafts', () => {
  const words = [{id:'first',text:'Bonjour'},{id:'second',text:'Marie'}];
  const translations = [{id:'ru',text:'Здравствуйте'}];
  assert.equal(reportAnswerText({}, {value:'  '}),'');
  assert.equal(reportAnswerText({}, {value:'  À bientôt !  '}),'  À bientôt !  ');
  assert.equal(reportAnswerText({choices:words}, {choice_id:''}),'');
  assert.equal(reportAnswerText({choices:words}, {choice_id:'second'}),'Marie');
  assert.equal(reportAnswerText({tokens:words}, {token_ids:[]}),'');
  assert.equal(reportAnswerText({tokens:words}, {token_ids:['second','first']}),'Marie Bonjour');
  assert.equal(reportAnswerText({left_items:words,right_items:translations}, {pairs:[]}),'');
  assert.equal(reportAnswerText({left_items:words,right_items:translations}, {pairs:[{left_id:'first',right_id:'ru'}]}),'Bonjour ↔ Здравствуйте');
});

test('report form requires the authenticated page identity',async()=>{
  const root=fileURLToPath(new URL('../../',import.meta.url));
  const server=await createServer({configFile:false,root,cacheDir:root+'node_modules/.vite-tests/practice-report',optimizeDeps:{noDiscovery:true,include:[]},plugins:[vue()],resolve:{alias:{'@':root+'resources/js'}},server:{middlewareMode:true,hmr:false},appType:'custom',logLevel:'error'});
  try {
    const {default:Report}=await server.ssrLoadModule('/resources/js/App/Components/Learning/PracticeReport.vue');
    const render=async (user, report={})=>{
      const result=await createInertiaApp({page:{component:'ReportAccess',url:'/daily',version:'test',props:{auth:{user},...report}},resolve:()=>Report,render:renderToString,
        setup:({App,props,plugin})=>createSSRApp({render:()=>h(App,props)}).use(plugin)});
      return result.body;
    };
    assert.doesNotMatch(await render(null),/<form|<textarea|Сообщить об ошибке/);
    const authorized=await render({id:7});
    assert.match(authorized,/<form/);
    assert.match(authorized,/Сообщить об ошибке/);
    assert.match(authorized,/Отправить сообщение/);
    assert.doesNotMatch(await render(null),/<form|<textarea|Сообщить об ошибке/);
    const draft = {taskId:'Q1',task:{choices:[{id:'a',text:'Bonjour'}]}};
    const noAnswer = await render({id:7},{...draft,answer:{choice_id:''}});
    assert.doesNotMatch(noAnswer,/Приложить введённый ответ/);
    assert.match(noAnswer,/Ответ можно приложить после ввода или выбора/);
    const withAnswer = await render({id:7},{...draft,answer:{choice_id:'a'}});
    assert.match(withAnswer,/Приложить введённый ответ/);
    assert.match(withAnswer,/aria-checked="false"/);
    assert.match(withAnswer,/Без вложения/);
    assert.match(withAnswer,/class="answer-preview-text"[^>]*>Bonjour<\/p>/);
  } finally { await server.close(); }
});
