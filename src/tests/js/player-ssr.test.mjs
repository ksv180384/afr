import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, createRenderer, h, nextTick, ssrContextKey } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createPracticeNotifications } from '../../resources/js/App/Learning/practiceNotifications.js';

test('player and all task components render on the server without browser globals', async () => {
  const root = fileURLToPath(new URL('../../', import.meta.url));
  // This server must never invalidate the running npm run dev dependency cache.
  const server = await createServer({ configFile: false, root, cacheDir: root + 'node_modules/.vite-tests/player-ssr', optimizeDeps: { noDiscovery: true, include: [] }, plugins: [vue()], resolve: { alias: { '@': root + 'resources/js' } }, server: { middlewareMode: true }, appType: 'custom', logLevel: 'error' });
  const render = async (name, props) => {
    const { default: component } = await server.ssrLoadModule('/resources/js/App/Components/Learning/' + name + '.vue');
    const rendered=await createInertiaApp({page:{component:'ComponentRender',url:'/daily',version:'test',props:{...props,auth:{user:{id:7}}}},resolve:()=>component,render:renderToString,setup:({App,props,plugin})=>createSSRApp({render:()=>h(App,props)}).use(plugin)});
    return rendered.body.replace(/^<div id="app" data-page="[^"]*">/, '').replace(/<\/div>$/, '');
  };
  try {
    let loads = 0;
    const adapter = { mode: 'preview', load: async () => { loads++; }, start() {}, answer() {}, help() {}, finish() {} };
    assert.match(await render('SessionPlayer', { adapter }), /Загружаем занятие/);
    assert.equal(loads, 0);
    // A resumed run retains the assistance history without reopening transient UI.
    const { default: SessionPlayer } = await server.ssrLoadModule('/resources/js/App/Components/Learning/SessionPlayer.vue');
    const savedView = {
      session: { level: 'PRE_A1' }, started: true, finished: false, total: 1, position: 1,
      introductions: [], introduction_seen: true, restore_assistance: true,
      assistance: ['audio_transcript', 'reference'],
      presentation: { presentation_id: 'restored-question' },
      task: { type: 'single_choice', skill: 'listening', choices: [{id:'a',text:'Хорошо'}],
        prompt: { text: 'Прослушайте вопрос.', text_lang: 'ru', audio: {text:'Ça va ?',transcription_ru:'са ва?'} } },
    };
    let helpCalls = 0;
    const restoredAdapter = { mode:'account', load:async()=>structuredClone(savedView), start:async()=>structuredClone(savedView),
      answer(){}, help(){helpCalls++;}, finish(){} };
    const renderer = createRenderer({createElement:()=>({}), createComment:()=>({}), createText:()=>({}),
      insert(){}, remove(){}, setElementText(){}, setText(){}, patchProp(){}, parentNode:()=>null, nextSibling:()=>null});
    const mounted = renderer.createApp({...SessionPlayer, render:()=>h('div')}, {adapter:restoredAdapter});
    mounted.provide(ssrContextKey, {});
    const api = mounted.mount({});
    try {
      await new Promise(resolve=>setImmediate(resolve));
      await nextTick();
      const state = mounted._instance.setupState;
      assert.equal(state.transcriptVisible, false);
      assert.equal(state.referenceOpen, false);
      assert.equal(state.transcriptGranted, true);
      assert.equal(state.listeningCue, 'Прослушайте вопрос.');
      assert.equal(state.listeningText, 'Ça va ?');
      state.toggleTranscript();
      assert.equal(state.transcriptVisible, true);
      state.toggleTranscript();
      assert.equal(state.transcriptVisible, false);
      await api.reload();
      await nextTick();
      assert.equal(state.transcriptVisible, false);
      assert.equal(state.referenceOpen, false);
      assert.equal(helpCalls, 0);
    } finally { mounted.unmount(); }
    assert.match(await render('GuestPractice', {levels:[]}), /Проверяем хранилище браузера/);
    assert.match(await render('VocabularyPanel', {}), /Тест по словам/);
    assert.match(await render('CurrentLevelSetting', {controller:{state:{profile:null,error:''}}}), /Проверяем сохранённый уровень/);
    const levelSetting = await render('CurrentLevelSetting', {
      controller: {state: {profile: {current_level: 'C2'}}},
      levels: [{code: 'B2', title: 'B2'}],
    });
    assert.match(levelSetting, /Сохранённый уровень C2 сейчас недоступен/);
    assert.match(levelSetting, /<option[^>]*disabled[^>]*>Выберите доступный уровень/);
    assert.match(levelSetting, /<option[^>]*value="B2"/);
    assert.doesNotMatch(levelSetting, /<option[^>]*value="C[12]"/);
    assert.match(levelSetting, /<button[^>]*disabled/);
    assert.match(await render('PracticeToasts', {notifications:createPracticeNotifications()}), /Уведомления практики/);
    const requestError = await render('PracticeErrorNotice', {message: 'Не удалось загрузить практику.'});
    assert.match(requestError, /role="alert"/);
    assert.match(requestError, /Не удалось загрузить практику/);
    assert.doesNotMatch(requestError, /<button\b|<a\b/);
    const { default: DailyPractice } = await server.ssrLoadModule('/resources/js/App/Pages/Daily/Index.vue');
    const { accountTransport } = await server.ssrLoadModule('/resources/js/App/Services/Api/daily_practice.js');
    const { usePracticeHome } = await server.ssrLoadModule('/resources/js/App/Learning/practiceHome.js');
    const { practiceNotificationsKey } = await server.ssrLoadModule('/resources/js/App/Learning/practiceNotifications.js');
    const { today, call } = accountTransport;
    const notifications = createPracticeNotifications();
    let startPage;
    let store;
    try {
      globalThis.window = { scrollY: 900, addEventListener() {}, removeEventListener() {} };
      store = usePracticeHome();
      let queued;
      Object.assign(store.accountRepository, {
        pending: async () => queued ? [queued] : [],
        enqueue: async (owner, run, action, logical, request) =>
          (queued = { run_id: run, action, request, client_event_id: logical.client_event_id }),
        seal: async () => queued,
      });
      let requests = 0;
      const message = 'Сервер временно недоступен.';
      const unavailable = () => Object.assign(new Error(message), { response: { status: 503, data: { message } } });
      accountTransport.today = async () => { throw unavailable(); };
      accountTransport.call = async (action, run) => {
        assert.equal(action, 'start');
        assert.equal(run, null);
        requests++;
        throw unavailable();
      };
      const pageProps = { authUser: { id: 7 }, enabled: true, embedded: true };
      startPage = renderer.createApp({ ...DailyPractice, render: () => h('div') }, pageProps);
      startPage.provide(ssrContextKey, {}).provide(practiceNotificationsKey, notifications);
      startPage.mount({});
      await new Promise(resolve => setImmediate(resolve));
      const page = startPage._instance.setupState;
      assert.equal(page.error, message);
      assert.deepEqual(notifications.items.map(({type, message}) => ({type, message})), [{type: 'error', message}]);
      const failedPage = {
        ...DailyPractice,
        setup(props, context) {
          const state = DailyPractice.setup(props, context);
          state.error.value = page.error;
          return state;
        },
      };
      const display = await renderToString(createSSRApp({render: () => h(failedPage, pageProps)})
        .provide(practiceNotificationsKey, notifications));
      assert.match(display, /role="note"/);
      assert.match(display, /Учебный маршрут недоступен/);
      assert.match(display, /Сервер временно недоступен/);
      assert.doesNotMatch(display, /Загружаем учебный маршрут|<button\b/);

      accountTransport.today = async () => ({
        profile: { owner_user_id: 7, revision: 1, timezone: 'UTC' },
        candidate: { id: 'PRE_A1-U01-L01' },
      });
      await page.reconnect();
      await page.startLesson();
      await nextTick();
      assert.equal(requests, 1);
      assert.deepEqual(notifications.items.map(({type, message, actions}) => ({type, message, actions})), [
        {type: 'error', message, actions: undefined},
      ]);
      assert.equal(window.scrollY, 900);
    } finally {
      startPage?.unmount();
      store?.guestRepository.close();
      Object.assign(accountTransport, { today, call });
      delete globalThis.window;
    }
    assert.ok((await render('GuestSync', {})).length > 0);
    const card=await render('DailySessionCard',{});
    assert.match(card,/Проверяем сохранённую практику/);
    assert.doesNotMatch(card,/Текущий уровень/);
    assert.match(card, /href="\/practice"/);
    assert.match(await render('PracticeNavigation',{active:'progress'}),/aria-current="page"/);
    assert.match(await render('FrenchText', { text: 'Ça va ?', transcriptionRu: 'са ва́?' }), /lang="fr"/);
    const answerInput=await render('FrenchAnswerInput',{id:'compact-answer',modelValue:'café',disabled:true});
    const nativeInput=answerInput.match(/<input\b[^>]*>/)?.[0];
    assert.match(nativeInput,/id="compact-answer"/);
    assert.match(nativeInput,/lang="fr"/);
    assert.match(nativeInput,/maxlength="20000"/);
    assert.match(nativeInput,/\bdisabled\b/);
    assert.match(answerInput,/for="compact-answer"/);
    const {default:Select}=await server.ssrLoadModule('/resources/js/App/Components/Form/Select/AfrSelect.vue');
    const {default:Option}=await server.ssrLoadModule('/resources/js/App/Components/Form/Select/AfrOption.vue');
    const searchable=await renderToString(createSSRApp({render:()=>h(Select,{id:'timezone-select',modelValue:'Europe/Moscow',searchable:true,disabled:true},{default:()=>[h(Option,{value:'Europe/Moscow',label:'(UTC+3) Москва'})]})}));
    assert.match(searchable,/role="combobox"/);
    assert.match(searchable,/aria-autocomplete="list"/);
    assert.match(searchable,/id="timezone-select"/);
    assert.match(searchable,/value="\(UTC\+3\) Москва"/);
    assert.match(searchable,/<input[^>]*disabled/);
    const { sessions } = JSON.parse(readFileSync(root + 'resources/practice/templates/task-types.json'));
    for (const task of sessions[0].tasks) {
      const names = { single_choice: 'SingleChoiceTask', matching_pairs: 'MatchingPairsTask', fill_blank: 'FillBlankTask', word_order: 'WordOrderTask', short_answer: 'ShortAnswerTask' };
      const answer = task.type === 'matching_pairs' ? { pairs: [] } : task.type === 'word_order' ? { token_ids: [] }
        : task.correct_choice_id ? { choice_id: '' } : { value: '' };
      const html = await render('Tasks/' + names[task.type], { task, modelValue: answer, uid: 'server-test' });
      assert.ok(html.length > 100);
      assert.doesNotMatch(html, /Произношение не подготовлено/);
      assert.doesNotMatch(html, /Правильный ответ/);
    }
    const contentFile = root + '../learning/programme/packages/PRE_A1/PRE_A1-U01.v1.json';
    const revised = existsSync(contentFile) ? JSON.parse(readFileSync(contentFile)) : {sessions:[]};
    for (const lesson of revised.sessions) for (const task of lesson.tasks) {
      const names = {single_choice:'SingleChoiceTask',matching_pairs:'MatchingPairsTask',word_order:'WordOrderTask'};
      const answer = task.type === 'matching_pairs' ? {pairs:[]} : task.type === 'word_order' ? {token_ids:[]} : {choice_id:''};
      const html = await render('Tasks/' + names[task.type], {task,modelValue:answer,uid:lesson.id+':'+task.id});
      assert.doesNotMatch(html, /<textarea|<input[^>]*type="text"|contenteditable/);
      assert.doesNotMatch(html, /Произношение не подготовлено/);
    }
    const publicHtml = await render('PublicSessionContent', { session: sessions[0] });
    assert.match(publicHtml, /мэрси́/);
    assert.doesNotMatch(publicHtml, /correct_choice_id|accepted_answers|Q01/);
    const { createEditorPreviewAdapter } = await server.ssrLoadModule('/resources/js/Admin/Pages/DailySession/editorPreview.js');
    let serial = 0;
    const options = { session:sessions[0], idFactory:() => 'editor-' + ++serial };
    const failed = createEditorPreviewAdapter({...options,scenario:'load_error'});
    await assert.rejects(failed.load(), /Тестовый сбой/);
    assert.equal((await failed.load()).total, 6);
    for (const scenario of ['empty','exhausted']) {
      const view = await createEditorPreviewAdapter({...options,scenario}).load();
      assert.equal(view.total, 0);
      assert.equal(view.summary.all_steps_processed, false);
      assert.equal(view.vocabulary.length, 0);
      assert.equal(view.exhausted, scenario === 'exhausted');
    }
    let draft;
    const memory = createEditorPreviewAdapter({...options,scenario:'save',onDraft:(value) => { draft = value; }});
    const input = { presentation_id:'editor',answer:{value:'merci'} };
    await memory.saveDraft(input);
    input.answer.value = 'changed';
    assert.equal(draft.answer.value, 'merci');
  } finally { await server.close(); }
});
