<script setup>
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import { rememberTemporaryGuest, registerGuestAuthentication } from '@/App/Learning/guestSyncState.js';
import { Link, router } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import PracticeReport from './PracticeReport.vue';
import PracticeDate from './PracticeDate.vue';
import FrenchText from './FrenchText.vue';
import AudioControl from './AudioControl.vue';
import { createAudioPlayer } from '@/App/Learning/audioPlayer.js';
import { GuestProgressRepository } from '@/App/Learning/guestRepository.js';
import { addGuestRun, recordGuestEvent } from '@/App/Learning/guestProgress.js';
import { filterGuestWords, guestWordData, guestTestPlan, guestTestSource } from '@/App/Learning/guestVocabulary.js';
import { createAccountRepository } from '@/App/Learning/accountRepository.js';
import { startAccountRun, deliverAccountQueue } from '@/App/Learning/accountAdapter.js';
import { accountTransport, vocabularyTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { browserTimezone } from '@/App/Learning/practiceTime.js';

const props = defineProps({authUser:Object,guestRepository:Object});
const emit = defineEmits(['open-guest-run','back']);
const words = ref([]);
const pagination = ref({current_page:1,last_page:1,total:0});
const sessions = ref([]);
const filters = ref({filter:'all',source_session:'',q:''});
const busy = ref(false);
const error = ref('');
const saving = ref({kind:'loading',message:''});
const pendingStart = ref(false);
const feedback = usePracticeFeedback({
  source: 'practice-vocabulary', error,
  status: saving,
});
const plan = ref(null);
const revealed = ref({});
let repository;
let unsubscribe;
let unregisterAuthentication;
let reconnectTimer;
let queueSyncing = false;
let disposed = false;
let openRecoveredStart = false;
const accountRepository = createAccountRepository();
const owner = props.authUser?.id;
const timezone=ref(browserTimezone());
const audio = createAudioPlayer();
provide('practiceAudioPlayer',audio);
const onStatus = value => { if (value.kind !== 'sending' && (saving.value.kind !== value.kind || saving.value.message !== value.message)) saving.value = value; };
const saved = () => onStatus(repository?.temporary ? {kind:'temporary',message:'Временный режим: данные исчезнут при закрытии страницы.'}
  : {kind:'idle',message:''});
const perform = async (action) => {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { await action(); }
  catch (failure) {
    if (owner && pendingStart.value && (!failure.response?.status || failure.response.status === 409 || failure.response.status >= 500)) scheduleRecovery();
    else error.value = failure.response?.data?.message ?? failure.message;
    if (!owner && !failure.code) onStatus({kind:'storage',message:'Не удалось сохранить или прочитать данные браузера. Не закрывайте страницу.'});
  }
  finally { busy.value = false; }
};
const requestFilters = () => Object.fromEntries(Object.entries(filters.value).filter(([,value])=>value));
const load = async (page = pagination.value.current_page) => {
  const current = requestFilters();
  if (owner) {
    const result = await vocabularyTransport.list({...current,page});
    words.value = result.words;
    pagination.value = result.pagination;
    sessions.value = result.source_sessions;
  } else {
    const all = filterGuestWords(await repository.read(),current).map(guestWordData);
    sessions.value = [...new Set(all.flatMap((word)=>(word.sources ?? []).map((source)=>source.session_id)))].sort();
    pagination.value = {current_page:page,last_page:Math.max(1,Math.ceil(all.length/30)),total:all.length};
    words.value = all.slice((page-1)*30,page*30);
  }
  sessions.value = [...new Set([...sessions.value,...words.value.flatMap((word)=>(word.sources ?? []).map((source)=>source.session_id))])].sort();
  plan.value = null;
  if (owner) pendingStart.value = (await accountRepository.pending(owner,null)).length>0;
};
const refresh = () => perform(()=>load(1));
const turnPage = (page) => perform(()=>load(page));
const mark = (word) => perform(async () => {
  const target = !word.needs_review;
  if (owner) await vocabularyTransport.mark(word.id,target);
  else await repository.mutate((state) => {
    if(state.bound_user_id !== null) throw Object.assign(new Error('Продолжите работу со словарём в аккаунте.'),{code:'bound_owner'});
    const current = state.vocabulary[word.lexeme_key];
    if (!current) throw new Error('Выражение отсутствует в этом браузере.');
    current.needs_review = target;
    recordGuestEvent(state,{action:'vocabulary_mark',lexeme_key:word.lexeme_key,needs_review:target,client_event_id:crypto.randomUUID(),client_occurred_at:new Date().toISOString()});
  });
  await load();
  onStatus({kind:'saved',message:target ? 'Выражение отмечено для повторения.' : 'Отметка повторения снята.'});
});
const prepare = () => perform(async () => {
  plan.value = owner ? await vocabularyTransport.plan(requestFilters()) : guestTestPlan(await repository.read(),requestFilters());
  if (!plan.value.total) onStatus({kind:'info',message:'Автоматически проверяемых вопросов нет. Откройте самопроверку у выражения ниже; серия и цели от неё не меняются.'});
});
const start = (all = false) => perform(async () => {
  const ids = all ? plan.value.word_ids : plan.value.word_ids.slice(0,10);
  if (owner) {
    const profile = await accountTransport.profile();
    if (profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
    const input = {client_event_id:crypto.randomUUID(),expected_revision:profile.revision,kind:'vocabulary_test',word_ids:ids,timezone:profile.persisted ? profile.timezone : browserTimezone()};
    try {
      openRecoveredStart = true;
      const state = await startAccountRun({ownerUserId:owner,input,repository:accountRepository,transport:accountTransport,onStatus});
      openRecoveredStart = false;
      router.visit('/daily/run/'+state.run_id);
    } finally { pendingStart.value = (await accountRepository.pending(owner,null)).length>0; if (pendingStart.value) scheduleRecovery(); }
  } else {
    const runId = crypto.randomUUID(); const tabId = crypto.randomUUID(); const seed = crypto.randomUUID(); const eventId = crypto.randomUUID();
    await repository.mutate((state) => {
      const source = guestTestSource(state,ids,seed,()=>crypto.randomUUID());
      addGuestRun(state,{source,runId,presentationIds:source.snapshot.tasks.map(()=>crypto.randomUUID()),eventId,tabId,now:new Date().toISOString(),kind:'vocabulary_test'});
    });
    if (props.guestRepository) emit('open-guest-run',runId);
    else router.visit('/daily/guest/'+runId);
  }
});
const prepareAuthentication = async () => {
  if (busy.value || !repository) throw new Error('Дождитесь сохранения текущего действия.');
  await repository.mutate((state)=>{state.return_path='/daily/vocabulary';});
  if(repository.temporary) rememberTemporaryGuest(await repository.read());
};
const authenticate = () => { prepareAuthentication().then(() => router.visit('/login?practice=1')).catch((failure)=>{error.value=failure.message;}); };
const scheduleRecovery = (delay=5000) => { clearTimeout(reconnectTimer); if (!disposed) reconnectTimer=setTimeout(recover,delay); };
const recover = async () => {
  if (!owner || disposed || queueSyncing || busy.value) return;
  queueSyncing=true;
  try {
    const state=await deliverAccountQueue({ownerUserId:owner,runId:null,repository:accountRepository,transport:accountTransport,onStatus});
    pendingStart.value=(await accountRepository.pending(owner,null)).length>0;
    if (state && openRecoveredStart && !disposed) { openRecoveredStart=false; router.visit('/daily/run/'+state.run_id); }
  } catch (failure) {
    if (!failure.response?.status || failure.response.status === 409 || failure.response.status >= 500) scheduleRecovery();
  } finally {
    try { pendingStart.value=(await accountRepository.pending(owner,null)).length>0; } catch {}
    queueSyncing=false;
  }
};
const sourceLabels = (word) => (word.sources ?? []).map((source)=>source.session_id+' · v'+source.version).join(', ');
onMounted(() => perform(async () => {
  if (owner) {
    const profile=await accountTransport.profile();
    if (profile.owner_user_id !== owner) throw new Error('Войдите в свой аккаунт.');
    if (profile.persisted) timezone.value=profile.timezone;
  }
  if (!owner) {
    repository = props.guestRepository ?? new GuestProgressRepository({timezone:browserTimezone()});
    if(!props.guestRepository) await repository.archiveBound();
    unsubscribe=repository.subscribe('vocabulary-'+crypto.randomUUID(),(notice)=>{if(notice.bound || notice.rotated) router.visit('/daily/vocabulary');});
    await repository.initialize();
    unregisterAuthentication = registerGuestAuthentication(prepareAuthentication);
  }
  await load();
  saved();
  if (owner) scheduleRecovery(0);
}));
onMounted(() => window.addEventListener('online',recover));
onBeforeUnmount(() => { disposed=true; clearTimeout(reconnectTimer); window.removeEventListener('online',recover); unregisterAuthentication?.(); unsubscribe?.(); audio.stop(); if (!owner && !props.guestRepository) repository?.close(); });
</script>
<template>
  <section class="vocabulary-panel">
    <div class="actions"><Link v-if="!guestRepository" href="/daily">К занятиям</Link><afr-button v-else @click="emit('back')">К занятиям</afr-button>
      <a v-if="!authUser" href="/login?practice=1" @click.prevent="authenticate">Войти в аккаунт</a></div>
    <p v-if="!authUser">Гостевой словарь хранится только в этом браузере. После входа он переносится в аккаунт.</p>
    <p v-if="saving.message && !feedback.enabled" role="status" class="notice">{{ saving.message }}</p>
    <p v-if="error && !feedback.enabled" role="alert" class="error">{{ error }}</p>
    <form class="filters" @submit.prevent="refresh">
      <div class="practice-field"><label for="vocabulary-filter">Показать</label><afr-select id="vocabulary-filter" v-model="filters.filter" native><afr-option value="all" label="Все выражения" /><afr-option value="errors" label="Нужно повторить" /></afr-select></div>
      <div class="practice-field"><label for="vocabulary-source">Из занятия</label><afr-select id="vocabulary-source" v-model="filters.source_session" native><afr-option value="" label="Все изученные занятия" /><afr-option v-for="session in sessions" :key="session" :value="session" :label="session" /></afr-select></div>
      <afr-input id="vocabulary-search" v-model="filters.q" label="Поиск" maxlength="100" placeholder="Французское слово или перевод" />
      <afr-button native-type="submit" type="primary" :loading="busy">Применить</afr-button>
      <afr-button :disabled="busy || !words.length || pendingStart" @click="prepare">Тест по словам</afr-button>
    </form>
    <p v-if="busy" role="status">Загружаем словарь…</p>
    <p v-else-if="!words.length">В этом списке пока нет выражений. Завершите занятие или измените фильтры.</p>
    <section v-if="plan" class="notice">
      <p>Для проверяемого теста: {{ plan.total }}. Для самопроверки без зачёта: {{ plan.self_check_words.length }}.</p>
      <div v-if="plan.total" class="actions">
        <afr-button :disabled="busy || pendingStart" @click="start(false)">Короткий тест — до 10 слов</afr-button>
        <afr-button v-if="plan.total > 10" :disabled="busy || pendingStart" @click="start(true)">Все слова — блоками по 10</afr-button>
      </div>
      <p v-if="!plan.total && !feedback.enabled">Автоматически проверяемых вопросов нет. Откройте самопроверку у выражения ниже; серия и цели от неё не меняются.</p>
    </section>
    <nav v-if="pagination.last_page>1" class="actions" aria-label="Страницы словаря">
      <afr-button :disabled="busy || pagination.current_page<=1" @click="turnPage(pagination.current_page-1)">Предыдущая страница</afr-button>
      <span role="status">Страница {{ pagination.current_page }} из {{ pagination.last_page }} · Всего выражений: {{ pagination.total }}</span>
      <afr-button :disabled="busy || pagination.current_page>=pagination.last_page" @click="turnPage(pagination.current_page+1)">Следующая страница</afr-button>
    </nav>
    <ol class="word-list"><li v-for="word in words" :key="word.id">
      <div class="word-main"><french-text :text="word.snapshot.fr" :transcription-ru="word.snapshot.transcription_ru" :translation="word.self_check && !revealed[word.id] ? null : word.snapshot.ru" />
        <p :class="word.needs_review ? 'needs-review' : 'known'">{{ word.needs_review ? 'Нужно повторить · '+word.streak+' из 3' : 'В вашем словаре' }}</p>
        <p>Ошибок за всё время: {{ word.errors }}<span v-if="word.next_review_at"> · Следующее повторение: <practice-date :value="word.next_review_at" :timezone="timezone" :time="false" /></span></p>
        <p v-if="word.self_check">Самопроверка без автоматического зачёта.</p>
      </div>
      <div class="word-actions"><audio-control :audio="{text:word.snapshot.fr,lang:'fr-FR',asset_id:null}" label="Прослушать" />
        <afr-button :disabled="busy" @click="mark(word)">{{ word.needs_review ? 'Снять отметку повторения' : 'Отметить для повторения' }}</afr-button>
        <afr-button v-if="word.self_check && !revealed[word.id]" @click="revealed[word.id] = true">Показать перевод для самопроверки</afr-button>
      </div>
      <details><summary>Изученные источники и примеры</summary>
        <p>{{ sourceLabels(word) || 'Сохранённое выражение' }}</p>
        <div v-for="source in word.sources" :key="source.session_version_id" class="source">
          <p>{{ source.session_id }} · v{{ source.version }}</p>
          <practice-report v-if="owner && source.session_version_id" :version-id="source.session_version_id" />
          <french-text :text="source.snapshot.fr" :transcription-ru="source.snapshot.transcription_ru" :translation="source.snapshot.ru" compact />
          <french-text v-if="source.snapshot.example" :text="source.snapshot.example.fr" :transcription-ru="source.snapshot.example.transcription_ru" :translation="source.snapshot.example.ru" compact />
        </div>
      </details>
    </li></ol>
  </section>
</template>
<style scoped>
p { line-height:1.6; margin:.5rem 0; }
.actions,.word-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:end; margin:.5rem 0; }
.filters { display:grid; grid-template-columns: minmax(140px,170px) minmax(180px,210px) minmax(170px,1fr) auto auto; gap:.5rem; align-items:end; margin:.75rem 0; }
.practice-field { display:grid; gap:.25rem; min-width:0; }
.practice-field label { font-size:.875rem; }
.filters :deep(.afr-input-component) { min-width:0; }
.notice { padding:1rem; background:#eff6ff; border:1px solid #bdd3e7; }
.error { padding:1rem; background:#fef2f2; }
.word-list>li { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem 1rem; padding:1.25rem 0; border-bottom:1px solid #bdd3e7; }
details { grid-column:1/-1; }
summary { cursor:pointer; min-height:44px; padding:.5rem 0; }
.source { padding:.75rem; margin:.5rem 0; background:white; border:1px solid #cbd5e1; }
.source :deep(.french-material) { margin-right:1rem; }
.needs-review { color:#9a3412; }.known { color:#166534; }
a { color:#155e91; text-decoration:underline; }
:deep(button) { max-width:100%; white-space:normal; }
:deep(:focus-visible) { outline:3px solid #2563eb; outline-offset:3px; }
@media(max-width:1000px) { .filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:700px) { .word-list>li { grid-template-columns:minmax(0,1fr); } }
@media(max-width:480px) { .filters { grid-template-columns:minmax(0,1fr); } .filters > button { justify-self:start; } }
</style>
