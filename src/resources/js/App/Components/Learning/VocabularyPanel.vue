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
import AudioPreferences from './AudioPreferences.vue';
import { createAudioPlayer } from '@/App/Learning/audioPlayer.js';
import { reviewDurationLabel, reviewWordCount } from '@/App/Learning/shortReview.js';
import ReviewSuggestionSetting from './ReviewSuggestionSetting.vue';
import { addGuestRun, recordGuestEvent } from '@/App/Learning/guestProgress.js';
import { filterGuestWords, guestWordData, guestTestPlan, guestTestSource } from '@/App/Learning/guestVocabulary.js';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { startAccountRun, deliverAccountQueue } from '@/App/Learning/accountAdapter.js';
import { accountTransport, vocabularyTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { browserTimezone } from '@/App/Learning/practiceTime.js';

const props = defineProps({authUser:Object,guestRepository:Object,routeStore:Object});
const emit = defineEmits(['open-guest-run','back']);
const words = ref([]);
const pagination = ref({current_page:1,last_page:1,total:0});
const sessions = ref([]);
const filters = ref({filter:'all',source_session:'',q:''});
const appliedFilters = ref({filter:'all'});
const shortPlan = ref(null);
const shortError = ref('');
const settingsOpen = ref(false);
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
let queueSyncing = false;
let disposed = false;
let openRecoveredStart = false;
const homeStore = props.routeStore ?? usePracticeHome();
const accountRepository = homeStore.accountRepository;
const owner = props.authUser?.id;
const invalidateHome = () => {
  homeStore.invalidate(owner ?? null);
};
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
    error.value = failure.response?.data?.message ?? failure.message;
    if (!owner && !failure.code) onStatus({kind:'storage',message:'Не удалось сохранить или прочитать данные браузера. Не закрывайте страницу.'});
  }
  finally { busy.value = false; }
};
const requestFilters = () => Object.fromEntries(Object.entries(filters.value).filter(([,value])=>value));
const load = async (page = pagination.value.current_page) => {
  const current = appliedFilters.value;
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
const loadShortPlan = async () => {
  shortError.value = '';
  shortPlan.value = null;
  try { shortPlan.value = await homeStore.loadReviewPlan(owner ?? null, appliedFilters.value); }
  catch (failure) { if (!disposed) shortError.value = failure.response?.data?.message ?? failure.message; }
};
const refresh = () => perform(async () => {
  appliedFilters.value = requestFilters();
  await Promise.all([load(1), loadShortPlan()]);
});
const showErrors = () => {
  if (busy.value) return;
  filters.value.filter = 'errors';
  refresh();
};
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
  invalidateHome();
  await Promise.all([load(), loadShortPlan()]);
  onStatus({kind:'saved',message:target ? 'Выражение отмечено для повторения.' : 'Отметка повторения снята.'});
});
const prepare = () => perform(async () => {
  plan.value = owner ? await vocabularyTransport.plan(appliedFilters.value) : guestTestPlan(await repository.read(), appliedFilters.value);
  if (!plan.value.total) onStatus({kind:'info',message:'Автоматически проверяемых вопросов нет. Откройте самопроверку у выражения ниже; серия и цели от неё не меняются.'});
});
const start = (all = false) => perform(async () => {
  const ids = all ? [...plan.value.word_ids] : plan.value.word_ids.slice(0, 10);
  if (owner) {
    const cachedProfile = homeStore.state.owner === owner && homeStore.state.ready ? homeStore.state.home?.profile : null;
    const profile = cachedProfile ?? await accountTransport.profile();
    if (profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
    const input = {client_event_id:crypto.randomUUID(),expected_revision:profile.revision,kind:'vocabulary_test',word_ids:ids,timezone:profile.persisted ? profile.timezone : browserTimezone()};
    try {
      openRecoveredStart = true;
      const state = await startAccountRun({ownerUserId:owner,input,profile,repository:accountRepository,transport:accountTransport,onStatus,onMutation:invalidateHome});
      openRecoveredStart = false;
      router.visit('/daily/run/'+state.run_id);
    } finally { pendingStart.value = (await accountRepository.pending(owner,null)).length>0; }
  } else {
    const runId = crypto.randomUUID(); const tabId = crypto.randomUUID(); const seed = crypto.randomUUID(); const eventId = crypto.randomUUID();
    await repository.mutate((state) => {
      const source = guestTestSource(state,ids,seed,()=>crypto.randomUUID());
      addGuestRun(state,{source,runId,presentationIds:source.snapshot.tasks.map(()=>crypto.randomUUID()),eventId,tabId,now:new Date().toISOString(),kind:'vocabulary_test'});
    });
    invalidateHome();
    if (props.guestRepository) emit('open-guest-run',runId);
    else router.visit('/daily/guest/'+runId);
  }
});
const openReviewRun = async id => {
  if (!owner && props.guestRepository) emit('open-guest-run', id);
  else await new Promise(resolve => router.visit('/daily/' + (owner ? 'run/' : 'guest/') + id, {onFinish: () => resolve()}));
};
const startShortReview = () => perform(async () => {
  const plan = shortPlan.value;
  if (!plan) return;
  if (plan.active_run_id) return openReviewRun(plan.active_run_id);
  if (!plan.total || pendingStart.value) return;
  try {
    openRecoveredStart = true;
    const path = await homeStore.startReview(owner ?? null, {filters:appliedFilters.value, wordIds:plan.word_ids, onStatus});
    openRecoveredStart = false;
    if (!disposed) await openReviewRun(path.split('/').pop());
  } catch (failure) {
    await loadShortPlan();
    throw failure;
  } finally {
    if (owner) pendingStart.value = (await accountRepository.pending(owner, null)).length > 0;
  }
});
const prepareAuthentication = async () => {
  if (busy.value || !repository) throw new Error('Дождитесь сохранения текущего действия.');
  await repository.mutate((state)=>{state.return_path='/daily/vocabulary';});
  if(repository.temporary) rememberTemporaryGuest(await repository.read());
};
const authenticate = () => { prepareAuthentication().then(() => router.visit('/login?practice=1')).catch((failure)=>{error.value=failure.message;}); };
const recover = async () => {
  if (!owner || disposed || queueSyncing || busy.value) return;
  queueSyncing=true;
  try {
    const state=await deliverAccountQueue({ownerUserId:owner,runId:null,repository:accountRepository,transport:accountTransport,onStatus,onMutation:invalidateHome});
    pendingStart.value=(await accountRepository.pending(owner,null)).length>0;
    if (state && !disposed) {
      if (openRecoveredStart) { openRecoveredStart = false; await openReviewRun(state.run_id); }
      else await loadShortPlan();
    }
  } catch (failure) {
    error.value = failure.response?.data?.message ?? failure.message;
  } finally {
    try { pendingStart.value=(await accountRepository.pending(owner,null)).length>0; } catch {}
    queueSyncing=false;
  }
};
const sourceLabels = (word) => (word.sources ?? []).map((source)=>source.session_id+' · v'+source.version).join(', ');
onMounted(async () => {
  await perform(async () => {
    if (owner) {
      const profile=await accountTransport.profile();
      if (profile.owner_user_id !== owner) throw new Error('Войдите в свой аккаунт.');
      if (profile.persisted) timezone.value=profile.timezone;
    }
    if (!owner) {
      repository = props.guestRepository ?? homeStore.guestRepository;
      if(!props.guestRepository) await repository.archiveBound();
      unsubscribe=repository.subscribe('vocabulary-'+crypto.randomUUID(),(notice)=>{if(notice.bound || notice.rotated) router.visit('/daily/vocabulary');});
      await repository.initialize();
      unregisterAuthentication = registerGuestAuthentication(prepareAuthentication);
    }
    await Promise.all([load(), loadShortPlan()]);
    saved();
  });
  if (owner && pendingStart.value) await recover();
});
onMounted(() => window.addEventListener('online',recover));
onBeforeUnmount(() => { disposed=true; window.removeEventListener('online',recover); unregisterAuthentication?.(); unsubscribe?.(); audio.stop(); });
</script>
<template>
  <section class="vocabulary-panel">
    <div class="actions"><Link v-if="!guestRepository" href="/practice">К занятиям</Link><afr-button v-else @click="emit('back')">К занятиям</afr-button>
      <a v-if="!authUser" href="/login?practice=1" @click.prevent="authenticate">Войти в аккаунт</a></div>
    <p v-if="!authUser">Гостевой словарь хранится только в этом браузере. После входа он переносится в аккаунт.</p>
    <p v-if="saving.message && !feedback.enabled" role="status" class="notice">{{ saving.message }}</p>
    <p v-if="error && !feedback.enabled" role="alert" class="error">{{ error }}</p>
    <afr-button v-if="pendingStart" :disabled="busy" @click="recover">Повторить отправку</afr-button>
    <section class="short-review" aria-labelledby="short-review-title">
      <h2 id="short-review-title">Короткое повторение</h2>
      <template v-if="shortPlan?.active_run_id">
        <p>У вас есть начатое занятие.</p>
        <afr-button :disabled="busy" @click="startShortReview">Продолжить начатое</afr-button>
      </template>
      <template v-else-if="shortPlan?.total">
        <p>{{ reviewWordCount(shortPlan.total) }} · {{ reviewDurationLabel(shortPlan.estimated_seconds) }}</p>
        <p v-if="shortPlan.includes_not_due">В блоке есть выражения, срок повторения которых ещё не наступил.</p>
        <afr-button type="primary" :disabled="busy || pendingStart" @click="startShortReview">Повторить изученное</afr-button>
      </template>
      <template v-else-if="shortError">
        <p role="alert">{{ shortError }}</p><afr-button :disabled="busy" @click="perform(loadShortPlan)">Обновить предложение</afr-button>
      </template>
      <p v-else-if="shortPlan">{{ shortPlan.self_check_count ? 'Для этих выражений в словаре доступны примеры и самопроверка.' : 'В этой выборке пока нет выражений для короткого повторения.' }}</p>
      <p v-else-if="busy" role="status">Проверяем изученные выражения…</p>
    </section>
    <details class="review-preferences" @toggle="settingsOpen ||= $event.target.open">
      <summary>Настройки предложений</summary>
      <review-suggestion-setting v-if="settingsOpen" :auth-user="authUser" :guest-repository="repository" />
    </details>
    <h2>Личный словарь</h2>
    <afr-button :disabled="busy || appliedFilters.filter === 'errors'" @click="showErrors">Ошибки и отметки</afr-button>
    <form class="filters" @submit.prevent="refresh">
      <div class="practice-field"><label for="vocabulary-filter">Показать</label><afr-select id="vocabulary-filter" v-model="filters.filter" native><afr-option value="all" label="Все выражения" /><afr-option value="errors" label="Ошибки и отметки" /><afr-option value="due" label="Пора повторить" /></afr-select></div>
      <div class="practice-field"><label for="vocabulary-source">Из занятия</label><afr-select id="vocabulary-source" v-model="filters.source_session" native><afr-option value="" label="Все изученные занятия" /><afr-option v-for="session in sessions" :key="session" :value="session" :label="session" /></afr-select></div>
      <afr-input id="vocabulary-search" v-model="filters.q" label="Поиск" maxlength="100" placeholder="Французское слово или перевод" />
      <afr-button native-type="submit" type="primary" :loading="busy">Применить</afr-button>
      <afr-button :disabled="busy || !words.length || pendingStart || !!shortPlan?.active_run_id" @click="prepare">Тест по словам</afr-button>
    </form>
    <p v-if="busy" role="status">Загружаем словарь…</p>
    <p v-else-if="!words.length">В этом списке пока нет выражений. Завершите занятие или измените фильтры.</p>
    <section v-if="plan" class="notice">
      <p>Для проверяемого теста: {{ plan.total }}. Для самопроверки без зачёта: {{ plan.self_check_words.length }}.</p>
      <div v-if="plan.total" class="actions">
        <afr-button :disabled="busy || pendingStart || !!shortPlan?.active_run_id" @click="start(false)">Короткий тест — до 10 слов</afr-button>
        <afr-button v-if="plan.total > 10" :disabled="busy || pendingStart || !!shortPlan?.active_run_id" @click="start(true)">Все слова — блоками по 10</afr-button>
      </div>
      <p v-if="!plan.total && !feedback.enabled">Автоматически проверяемых вопросов нет. Откройте самопроверку у выражения ниже; серия и цели от неё не меняются.</p>
    </section>
    <nav v-if="pagination.last_page>1" class="actions" aria-label="Страницы словаря">
      <afr-button :disabled="busy || pagination.current_page<=1" @click="turnPage(pagination.current_page-1)">Предыдущая страница</afr-button>
      <span role="status">Страница {{ pagination.current_page }} из {{ pagination.last_page }} · Всего выражений: {{ pagination.total }}</span>
      <afr-button :disabled="busy || pagination.current_page>=pagination.last_page" @click="turnPage(pagination.current_page+1)">Следующая страница</afr-button>
    </nav>
    <audio-preferences v-if="words.length" />
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
.short-review { padding:var(--practice-pad); margin:1rem 0; border:1px solid var(--practice-border); border-radius:var(--practice-radius); background:var(--practice-surface); }
h2 { font-weight:700; font-size:1.15rem; }
.review-preferences { margin-bottom:1rem; }
.short-review :deep(button) { min-height:44px; }
p { line-height:1.6; margin:.5rem 0; }
.actions,.word-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:end; margin:.5rem 0; }
.filters { display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(0,1.4fr) auto auto; gap:.5rem; align-items:end; margin:.75rem 0; }
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
