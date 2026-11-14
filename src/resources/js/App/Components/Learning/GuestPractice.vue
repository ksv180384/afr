<script setup>
import ReviewSuggestionCard from './ReviewSuggestionCard.vue';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { createPracticeHomeStore } from '@/App/Learning/practiceHomeStore.js';
import PracticeRouteTopic from './PracticeRouteTopic.vue';
import { computed, markRaw, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import SessionPlayer from './SessionPlayer.vue';
import VocabularyPanel from './VocabularyPanel.vue';
import PracticeStatus from './PracticeStatus.vue';
import PracticeDate from './PracticeDate.vue';
import { browserTimezone } from '@/App/Learning/practiceTime.js';
import { rememberTemporaryGuest, registerGuestAuthentication } from '@/App/Learning/guestSyncState.js';
import { guestTestSource } from '@/App/Learning/guestVocabulary.js';
import { GuestProgressRepository } from '@/App/Learning/guestRepository.js';
import { createGuestAdapter } from '@/App/Learning/guestAdapter.js';
import { addGuestRun, changeGuestProfile } from '@/App/Learning/guestProgress.js';
import { guestContentTransport } from '@/App/Services/Api/daily_practice.js';
import { returnToPractice, visitPracticeRun } from '@/App/Learning/practiceRunNavigation.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';

const props = defineProps({runId:String,levels:Array,progressOnly:Boolean,trail:Boolean,selectedSession:Object,routeSelection:Object});
const state = ref(null);
const vocabularyMode = ref(false);
const sharedStore = usePracticeHome();
const store = shallowRef(sharedStore);
const home = computed(() => store.value.state.owner === null ? store.value.state.home : null);
const selectedAccess = computed(() => home.value?.route.lessons.find(lesson => lesson.id === props.selectedSession?.id));
const adapter = shallowRef(null);
const player = ref(null);
const saving = ref({kind:'loading',message:'Проверяем хранилище браузера…'});
const error = ref('');
const feedback = usePracticeFeedback({ status: saving, error, source: 'practice-session' });
const busy = ref(false);
const retryLesson = ref();
let temporaryScroll = 0;
const ready = ref(false);
const level = ref('PRE_A1');
const timezone = ref(browserTimezone());
let repository;
let tabId;
let activeRun;
let unsubscribe;
let unregisterAuthentication;
const onStatus = (next) => { saving.value = next; };
const locked = computed(() => ['conflict','storage','bound'].includes(saving.value.kind));
const runs = computed(() => Object.values(state.value?.runs ?? {}).sort((a,b) => b.record_sequence-a.record_sequence));
const currentRun = computed(() => home.value?.active_run);
const candidate = computed(() => home.value?.candidate);
const showNext = computed(() => currentRun.value || !(props.routeSelection?.level || props.routeSelection?.unit) ||
  home.value?.route.lessons.some(lesson => lesson.id === candidate.value?.id));
const lessonLevel = computed(() => currentRun.value?.level ?? candidate.value?.level ?? state.value?.current_level);
const reviewPlan = computed(() => home.value?.review_suggestion);
const dirty = computed(() => state.value && (state.value.current_level !== level.value || state.value.timezone !== timezone.value));
const storedNotice = () => onStatus(repository.temporary
  ? {kind:'temporary',message:'Временный режим: прогресс хранится только до закрытия этой страницы.'}
  : {kind:'idle',message:''});
const fail = (failure) => {
  error.value = failure.message || 'Не удалось выполнить действие.';
  if (failure.code === 'revision_conflict') onStatus({kind:'conflict',message:failure.message});
  else if (!failure.code || failure.code === 'storage') onStatus({kind:'storage',message:'Не удалось сохранить в браузере. Доступен временный режим до закрытия страницы.'});
};
const perform = async (action) => {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { await action(); } catch (failure) { fail(failure); } finally { busy.value = false; }
};
const applyLocal = (local, resetDraft = true) => {
  state.value = local;
  if (resetDraft) {
    level.value = local.current_level;
    timezone.value = local.timezone;
  }
};
const readLocal = async (resetDraft = true) => {
  const local = await repository.read();
  if (!local || local.bound_user_id != null) return;
  applyLocal(local, resetDraft);
  store.value.updateGuest(local);
};
const attach = (id) => {
  activeRun = id;
  adapter.value = markRaw(createGuestAdapter({
    repository, runId:id, tabId, onStatus, onMutation: () => store.value.invalidate(null),
  }));
};
const initialize = async (force = false, homeOnly = false) => {
  const routeStore = store.value;
  if (!homeOnly && (props.runId || props.progressOnly)) {
    routeStore.setOwner(null);
    await repository.archiveBound();
    const local = await repository.initialize();
    applyLocal(local);
    if (props.runId && (!repository.temporary || local.runs[props.runId])) attach(props.runId);
  }
  if (!adapter.value && !props.progressOnly) {
    const options = {selection: props.routeSelection ?? {}};
    const result = await (force ? routeStore.refresh(null, 1, options) : routeStore.load(null, 1, options));
    if (!result) return;
    applyLocal(routeStore.state.guest);
    error.value = result.home.content_error;
  }
  ready.value = true;
  storedNotice();
};
const temporary = () => perform(async () => {
  const cached = repository.cached;
  repository = new GuestProgressRepository({timezone: timezone.value});
  if (cached?.bound_user_id == null && cached?.sync?.owner_user_id == null && cached) repository.cached = cached;
  repository.useTemporary();
  store.value = createPracticeHomeStore({guestTransport: guestContentTransport, guestRepository: repository});
  await initialize(true);
});
const enterRun = (path, route = home.value?.route) => {
  if (!repository.temporary) return visitPracticeRun(path, route, props.routeSelection);
  if (!adapter.value) temporaryScroll = window.scrollY;
  attach(path.split('/').pop());
};
const start = (sessionId = null) => perform(async () => {
  retryLesson.value = undefined;
  const route = home.value?.route;
  try {
    const path = await store.value.start(null, sessionId);
    storedNotice();
    await enterRun(path, route);
  } catch (failure) {
    if (failure.code !== 'content_load') throw failure;
    retryLesson.value = sessionId;
    error.value = 'Не удалось загрузить занятие. Повторите после восстановления соединения.';
  }
});
const refreshSuggestion = () => perform(() => readLocal(false));
const startShortReview = (mode = 'manual') => perform(async () => {
  const route = home.value?.route;
  const path = await store.value.startReview(null, {mode});
  await enterRun(path, route);
});
const nextTest = (input) => perform(async () => {
  const runId = crypto.randomUUID(); const seed = crypto.randomUUID();
  await repository.mutate((current) => {
    const previous = current.runs[input.continue_run_id];
    if (!previous || previous.status !== 'completed') throw new Error('Сначала завершите предыдущий блок.');
    const source = guestTestSource(current,previous.snapshot.remaining_word_ids ?? [],seed,()=>crypto.randomUUID());
    addGuestRun(current,{source,runId,presentationIds:source.snapshot.tasks.map(()=>crypto.randomUUID()),eventId:crypto.randomUUID(),tabId,now:new Date().toISOString(),kind:'vocabulary_test'});
  });
  store.value.invalidate(null);
  await enterRun('/daily/guest/' + runId);
});
const openVocabulary = () => { if (repository.temporary) { adapter.value = null; vocabularyMode.value = true; } else router.visit('/daily/vocabulary'); };
const open = id => perform(() => enterRun('/daily/guest/' + id));
const exit = async () => {
  if (repository.temporary) {
    adapter.value = null;
    activeRun = null;
    if (home.value) await readLocal();
    else await initialize(true, true);
    await nextTick();
    requestAnimationFrame(() => window.scrollTo(0, temporaryScroll));
  } else returnToPractice();
};
const changeProfile = () => perform(async () => {
  await repository.mutate((current) => changeGuestProfile(current,{client_event_id:crypto.randomUUID(),expected_revision:state.value.profile_revision,
    current_level:level.value,timezone:timezone.value,client_occurred_at:new Date().toISOString()}),{profile:true});
  await readLocal();
  onStatus(repository.temporary
    ? {kind:'temporary',message:'Настройки изменены во временном режиме. Они сохранятся только до закрытия страницы.'}
    : {kind:'confirmed',message:'Уровень и часовой пояс сохранены в этом браузере.'});
});
const reload = () => perform(async () => {
  if (adapter.value) await player.value.reload();
  else { await initialize(true); }
});
const prepareAuthentication = async () => {
  if (busy.value || !ready.value) throw new Error('Дождитесь сохранения текущего действия.');
  await player.value?.prepareAuthentication();
  await repository.mutate((current)=>{current.return_run_id=activeRun ?? props.runId ?? null;current.return_path='/daily';});
  if(repository.temporary) rememberTemporaryGuest(await repository.read());
};
const authenticate = (path) => {
  prepareAuthentication().then(() => router.visit(path+'?practice=1')).catch(fail);
};
const focus = () => { if (adapter.value && document.hasFocus()) player.value?.reload(); };
onMounted(() => {
  tabId = crypto.randomUUID();
  timezone.value = browserTimezone();
  repository = sharedStore.guestRepository;
  unsubscribe = repository.subscribe(tabId,(notice) => {
    if(notice.rotated) { router.visit('/daily'); return; }
    if(notice.bound) { onStatus({kind:'bound',message:'Гостевой прогресс связан с аккаунтом. Продолжите в аккаунте.'}); return; }
    if (notice.run_id === activeRun) focus();
    else if (!adapter.value && ready.value) readLocal().catch(fail);
  });
  window.addEventListener('focus',focus);
  unregisterAuthentication = registerGuestAuthentication(prepareAuthentication);
  perform(initialize);
});
watch(() => props.routeSelection, () => {
  if (repository && !props.runId && !props.progressOnly) initialize().catch(fail);
});
onBeforeUnmount(() => { window.removeEventListener('focus',focus); unregisterAuthentication?.(); unsubscribe?.(); if (repository?.temporary) repository.close(); });
</script>
<template>
  <section class="guest-practice">
    <section v-if="saving.message && (!feedback.enabled || saving.kind === 'loading' || ['storage','bound'].includes(saving.kind))" class="practice-panel notice" :class="saving.kind">
      <p v-if="!feedback.enabled || saving.kind === 'loading'" role="status">{{ saving.message }}</p><a v-if="saving.kind === 'bound'" href="/daily">Продолжить в аккаунте</a>
      <div v-if="saving.kind === 'storage'" class="actions">
        <afr-button v-if="saving.kind === 'storage'" :disabled="busy" @click="temporary">Продолжить временно</afr-button>
      </div>
    </section>
    <p v-if="error && !feedback.enabled" role="alert" class="error">{{ error }}</p>
    <a v-if="runId && !adapter && error" class="guest-return" href="/practice">К моему пути</a>
    <afr-button v-if="!adapter && home?.content_error" :disabled="busy" @click="perform(() => initialize(true))">Повторить загрузку</afr-button>
    <afr-button v-if="retryLesson !== undefined" :disabled="busy" @click="start(retryLesson)">Повторить загрузку занятия</afr-button>
    <vocabulary-panel v-if="vocabularyMode" :guest-repository="repository" :route-store="store" @back="vocabularyMode = false; readLocal()" @open-guest-run="vocabularyMode = false; attach($event)" /><session-player v-else-if="adapter" :key="activeRun" ref="player" :adapter="adapter" :locked="locked" :timezone="timezone" :route-store="store" :route-owner="null" :navigation-busy="busy"
      @exit="exit" @next-lesson="start()" @review="startShortReview" @repeat="start($event.session_id)" @test-next="nextTest" @vocabulary="openVocabulary" />
    <template v-else-if="ready && state">
      <p v-if="!progressOnly && !home" role="status">Загружаем учебный маршрут…</p>
      <practice-route-topic v-if="!progressOnly && home" :trail="trail" :route="home.route" :store="store" :selection="routeSelection"
        :busy="busy || locked || dirty" @start="start" @continue="open" guest>
      <section v-if="selectedSession" class="practice-panel lesson-main">
        <p class="muted">Выбранное занятие</p><h2>{{ selectedSession.title }}</h2>
        <p v-if="['locked','unavailable'].includes(selectedAccess?.status)" role="status">{{ selectedAccess.reason }}</p>
        <afr-button v-if="selectedAccess?.run_id" type="primary" :disabled="busy || locked" @click="open(selectedAccess.run_id)">
          Продолжить занятие
        </afr-button>
        <afr-button v-else-if="['available', 'completed'].includes(selectedAccess?.status)" type="primary"
          :disabled="busy || locked" @click="start(selectedSession.id)">{{ selectedAccess.completed ? 'Пройти повторно' : 'Начать занятие' }}</afr-button>
      </section>
      <section v-else-if="showNext" class="practice-panel lesson-main">
        <p class="muted">{{ lessonLevel==='PRE_A1'?'С нуля':lessonLevel }}<span v-if="currentRun || candidate"> · Около {{ currentRun?.duration_minutes || candidate?.duration_minutes }} мин</span></p>
        <h2>{{ currentRun?.title || candidate?.title || (error || home?.route.level.status === 'unavailable' ? 'Материалы сейчас недоступны' : 'Вы всё прошли на этом уровне') }}</h2>
        <p v-if="(currentRun ?? candidate)?.goal">{{ (currentRun ?? candidate).goal }}</p>
        <afr-button v-if="currentRun" type="primary" :disabled="busy || locked" @click="open(currentRun.id)">Продолжить занятие</afr-button>
        <afr-button v-else-if="candidate" type="primary" :disabled="busy || dirty || locked" @click="start()">Начать занятие</afr-button>
        <p v-else>Новые занятия появятся после публикации.</p>
      </section>
      </practice-route-topic>
      <review-suggestion-card v-if="!progressOnly && !trail" :suggestion="reviewPlan" :store="store" :busy="busy || locked"
        @start="startShortReview('suggested')" @dismissed="refreshSuggestion" />
      <p v-if="!progressOnly && !trail && repository?.temporary"><afr-button @click="openVocabulary">Повторять · Личный словарь</afr-button></p>
      <details v-if="!progressOnly && !trail" class="practice-panel">
        <summary>Уровень занятий</summary>
        <div class="settings">
          <div class="practice-field"><label for="daily-guest-level">Уровень</label><afr-select id="daily-guest-level" v-model="level" native :disabled="busy"><afr-option v-for="item in levels" :key="item.code" :value="item.code" :label="item.title" /></afr-select></div>
          <afr-button type="primary" :loading="busy" @click="changeProfile">Сохранить уровень</afr-button>
        </div>
        <p class="muted">При смене уровня ваш словарь, ошибки и расписание повторений сохраняются в этом браузере. Новые слова добавляются после завершения занятий.</p>
        <p class="muted">Начатое занятие останется доступным после смены уровня.</p>
      </details>
      <section v-if="progressOnly" class="practice-panel">
        <h2>Прогресс в этом браузере</h2>
        <p>Текущий уровень: {{ state.current_level==='PRE_A1'?'С нуля':state.current_level }}. Завершено прохождений: {{ runs.filter((run)=>run.status==='completed').length }}. Выражений в словаре: {{ Object.keys(state.vocabulary).length }}.</p>
        <p>Учебные цели и автоматический переход уровня подтверждаются после входа и серверной проверки ответов.</p>
        <a href="/daily">Изменить уровень или продолжить занятие</a>
      </section>
      <details v-if="progressOnly" class="practice-panel">
        <summary>История занятий<span v-if="runs.length"> · {{ runs.length }}</span></summary>
        <p v-if="!runs.length">Начатых занятий пока нет.</p>
        <ol><li v-for="run in runs" :key="run.id">
          <a v-if="!repository?.temporary" :href="'/daily/guest/'+run.id" @click.prevent="open(run.id)">{{ run.snapshot.title }}</a>
          <afr-button v-else @click="open(run.id)">{{ run.snapshot.title }}</afr-button>
          <practice-status :status="run.status" :label="run.status === 'completed' ? 'Завершено' : null" />
          <p class="muted">Начато <practice-date :value="run.started_at || run.local_date" :timezone="timezone" /><span v-if="run.finished_at"> · Завершено <practice-date :value="run.finished_at" :timezone="timezone" /></span></p>
          <p v-if="run.result">Самостоятельно: {{ run.result.independent_correct_steps }} из {{ run.result.total_steps }}</p>
        </li></ol>
      </details>
    </template>
    <p v-if="ready && !vocabularyMode && !trail" class="muted guest-note">Прогресс в этом браузере. <a href="/login?practice=1" @click.prevent="authenticate('/login')">Войти в аккаунт</a></p>
  </section>
</template>
<style scoped>
p { line-height:1.65; margin:.5rem 0; }
h2 { font-size:1.2rem; font-weight:bold; margin-bottom:.7rem; }
.notice { background:#eff6ff; }
.guest-note { margin-top:1rem; }
.guest-note a,.guest-return { display:inline-flex; align-items:center; min-height:44px; }
.notice.storage,.notice.conflict,.notice.temporary { background:#fffbeb; border-color:#b45309; }
.error { padding:1rem; background:#fef2f2; }
.settings { display:grid; grid-template-columns:150px max-content; align-items:end; gap:.5rem; margin-top:.75rem; }
.practice-field { display:grid; gap:.25rem; min-width:0; }
.practice-field label { font-size:.875rem; }
.settings :deep(.afr-input-component) { min-width:0; }
.settings > button { justify-self:start; }
.actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:end; }
li { padding:.75rem 0; border-bottom:1px solid #e2e8f0; }
a { color:#155e91; text-decoration:underline; }
.muted { font-size:.9rem; color:#475569; }
summary { cursor:pointer; font-weight:600; }
:deep(button) { max-width:100%; white-space:normal; }
@media(max-width:700px) { .settings { grid-template-columns:minmax(0,1fr); } }
</style>
