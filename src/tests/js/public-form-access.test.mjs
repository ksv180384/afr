import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';

test('search and local text and audio activities remain available to guests',async()=>{
  const root=fileURLToPath(new URL('../../',import.meta.url));
  const server=await createServer({configFile:false,root,cacheDir:root+'node_modules/.vite-tests/public-form-access',optimizeDeps:{noDiscovery:true,include:[]},plugins:[vue()],resolve:{alias:{'@':root+'resources/js'}},server:{middlewareMode:true,hmr:false},appType:'custom',logLevel:'error'});
  const render=async(path,props,user)=>{
    const {default:component}=await server.ssrLoadModule('/resources/js/'+path+'.vue');
    const result=await createInertiaApp({page:{component:'FormAccessTest',url:'/daily',version:'test',props:{...props,auth:{user}}},resolve:()=>component,render:renderToString,setup:({App,props,plugin})=>{const app=createSSRApp({render:()=>h(App,props)}).use(plugin);app.config.globalProperties.route=()=>'/games';return app;}});
    return result.body.replace(/^<div id="app" data-page="[^"]*">/,'').replace(/<\/div>$/,'');
  };
  try {
    for (const [path,props,pattern] of [
      ['App/Components/Learning/FrenchAnswerInput',{id:'answer',modelValue:''},/<input/],
      ['App/Components/Header/Search/Search',{},/<input/],
      ['App/Components/Player/AfrPlayerSearch',{},/<input/],
      ['App/Components/Learning/VocabularyPanel',{authUser:{id:7}},/<input/],
      ['App/Components/Player/AfrPlayer',{},/type="file"/],
    ]) {
      assert.match(await render(path,props,null),pattern);
      assert.match(await render(path,props,{id:7}),pattern);
      assert.match(await render(path,props,null),pattern);
    }
    for (const [path,label] of [
      ['App/Components/AfrLearningWrite','Учимся писать'],
      ['App/Pages/Lessons/Components/AfrTestFrToRu','Проверка знаний Fr'],
      ['App/Pages/Lessons/Components/AfrTestRuToFr','Проверка знаний Ru'],
      ['App/Components/Song/AfrKaraokePlayer','Караоке'],
    ]) {
      const guest=await render(path,{},null);
      assert.match(guest,new RegExp(label));
      assert.doesNotMatch(guest,/чтобы продолжить/);
      assert.match(await render(path,{}, {id:7}),new RegExp(label));
    }
    const guestGame=await render('App/Pages/Games/FallingTranslations',{},null);
    assert.match(guestGame,/Войти и начать игру/);
    assert.doesNotMatch(guestGame,/>Начать игру</);
    const accountGame=await render('App/Pages/Games/FallingTranslations',{}, {id:7});
    assert.match(accountGame,/>Начать игру</);
    assert.doesNotMatch(accountGame,/Войти и начать игру/);
    const choice=await render('App/Components/Learning/Tasks/SingleChoiceTask',{uid:'guest-choice',modelValue:{choice_id:''},task:{choices:[{id:'a',text:'Bonjour'}]}},null);
    assert.match(choice,/type="radio"/);
  } finally {await server.close();}
});
