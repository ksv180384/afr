<script setup>
import { learningAccess, completedGuestSessions } from '@/App/Learning/learningAccess.js';
import { computed, markRaw, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { router } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import SessionPlayer from './SessionPlayer.vue';
import VocabularyPanel from './VocabularyPanel.vue';
import PracticeDate from './PracticeDate.vue';
import { browserTimezone } from '@/App/Learning/practiceTime.js';
import { rememberTemporaryGuest, registerGuestAuthentication } from '@/App/Learning/guestSyncState.js';
import { guestTestSource } from '@/App/Learning/guestVocabulary.js';
import { GuestProgressRepository } from '@/App/Learning/guestRepository.js';
import { createGuestAdapter } from '@/App/Learning/guestAdapter.js';
import { addGuestRun, changeGuestProfile, applyGuestOperation } from '@/App/Learning/guestProgress.js';
import { guestContentTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';

const props = defineProps({runId:String,levels:Array,progressOnly:Boolean,selectedSession:Object});
const state = ref(null);
const vocabularyMode = ref(false);
const catalog = ref([]);
const accessOutline = ref(null);
const access = computed(() => accessOutline.value ? learningAccess(accessOutline.value, completedGuestSessions(state.value), state.value?.current_level) : null);
const selectedAccess = computed(() => props.selectedSession ? access.value?.sessions[props.selectedSession.id] : null);
const adapter = shallowRef(null);
const player = ref(null);
const saving = ref({kind:'loading',message:'Проверяем хранилище браузера…'});
const error = ref('');
const feedback = usePracticeFeedback({ status: saving, error, source: 'practice-session' });
const busy = ref(false);
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
const completed = computed(() => runs.value.filter((run) => run.status === 'completed').map((run) => run.snapshot.id));
const currentRun = computed(() => runs.value.find(run => run.status !== 'completed'));
const candidate = computed(() => catalog.value.find(session => access.value ? !access.value.sessions[session.id]?.locked && !access.value.sessions[session.id]?.review_only && !completed.value.includes(session.id) : session.level === state.value?.current_level && !completed.value.includes(session.id) && session.prerequisite_ids.every(id => completed.value.includes(id))));
const lessonLevel = computed(() => currentRun.value?.snapshot.level ?? candidate.value?.level ?? state.value?.current_level);
const dirty = computed(() => state.value && (state.value.current_level !== level.value || state.value.timezone !== timezone.value));
const statusTitle = (status) => ({in_progress:'Начато',paused:'Отложено',completed:'Завершено'})[status] ?? status;
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
const readLocal = async () => {
  state.value = await repository.read();
  level.value = state.value.current_level;
  timezone.value = state.value.timezone;
};
const loadCatalog = async () => {
  try { const response = await guestContentTransport.catalog(); catalog.value = response.sessions; accessOutline.value = response.access_outline ?? null; }
  catch { error.value = 'Новый материал сейчас недоступен. Для загрузки нового занятия нужно соединение; сохранённые занятия можно продолжить.'; }
};
const attach = (id) => {
  activeRun = id;
  adapter.value = markRaw(createGuestAdapter({repository,runId:id,tabId,onStatus}));
};
const initialize = async () => {
  await repository.archiveBound();
  await repository.initialize();
  await readLocal();
  ready.value = true;
  storedNotice();
  if (props.runId) attach(props.runId);
  else if(!props.progressOnly) await loadCatalog();
};
const temporary = () => perform(async () => {
  repository.useTemporary();
  ready.value = true;
  await readLocal();
  storedNotice();
  if (!adapter.value && props.runId && state.value.runs[props.runId]) attach(props.runId);
  if (!adapter.value) await loadCatalog();
});
const start = (sessionId = candidate.value?.id, kind = 'daily') => perform(async () => {
  if (!sessionId) return;
  let source;
  try { source = await guestContentTransport.session(sessionId); }
  catch { error.value = 'Не удалось загрузить новое занятие. Проверьте соединение. Сохранённые прохождения доступны отдельно.'; return; }
  const runId = crypto.randomUUID();
  const input = {source,runId,presentationIds:source.snapshot.tasks.map(() => crypto.randomUUID()),eventId:crypto.randomUUID(),tabId,now:new Date().toISOString(),kind};
  const oldRun = kind === 'daily' && runs.value.find((run)=>run.status==='in_progress' && run.snapshot.level!==source.snapshot.level);
  await repository.mutate((current) => {
    if(oldRun && current.runs[oldRun.id]?.status==='in_progress') {
      const previous=current.runs[oldRun.id];
      previous.lease=tabId;
      applyGuestOperation(current,previous.id,tabId,'pause',{client_event_id:crypto.randomUUID(),expected_revision:previous.revision,client_occurred_at:new Date().toISOString()});
    }
    addGuestRun(current,input);
  },{run_id:oldRun?.id ?? runId,claimed:!!oldRun});
  storedNotice();
  if (repository.temporary) attach(runId);
  else router.visit('/daily/guest/'+runId);
});
const nextTest = (input) => perform(async () => {
  const runId = crypto.randomUUID(); const seed = crypto.randomUUID();
  await repository.mutate((current) => {
    const previous = current.runs[input.continue_run_id];
    if (!previous || previous.status !== 'completed') throw new Error('Сначала завершите предыдущий блок.');
    const source = guestTestSource(current,previous.snapshot.remaining_word_ids ?? [],seed,()=>crypto.randomUUID());
    addGuestRun(current,{source,runId,presentationIds:source.snapshot.tasks.map(()=>crypto.randomUUID()),eventId:crypto.randomUUID(),tabId,now:new Date().toISOString(),kind:'vocabulary_test'});
  });
  if (repository.temporary) attach(runId); else router.visit('/daily/guest/'+runId);
});
const openVocabulary = () => { if (repository.temporary) { adapter.value = null; vocabularyMode.value = true; } else router.visit('/daily/vocabulary'); };
const open = (id) => {
  if (repository.temporary) attach(id);
  else router.visit('/daily/guest/'+id);
};
const exit = async () => {
  if (repository.temporary) { adapter.value = null; activeRun = null; await readLocal(); }
  else router.visit('/daily');
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
  else { await initialize(); }
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
  repository = new GuestProgressRepository({timezone:timezone.value});
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
onBeforeUnmount(() => { window.removeEventListener('focus',focus); unregisterAuthentication?.(); unsubscribe?.(); repository?.close(); });
</script>
<template>
  <section class="guest-practice">
    <p class="muted guest-note">Прогресс сохраняется в этом браузере. <a href="/login?practice=1" @click.prevent="authenticate('/login')">Войдите</a>, чтобы заниматься на разных устройствах.</p>
    <section v-if="saving.message && (!feedback.enabled || saving.kind === 'loading' || ['storage','bound'].includes(saving.kind))" class="notice" :class="saving.kind">
      <p v-if="!feedback.enabled || saving.kind === 'loading'" role="status">{{ saving.message }}</p><a v-if="saving.kind === 'bound'" href="/daily">Продолжить в аккаунте</a>
      <div v-if="saving.kind === 'storage'" class="actions">
        <afr-button v-if="saving.kind === 'storage'" :disabled="busy" @click="temporary">Продолжить временно</afr-button>
      </div>
    </section>
    <p v-if="error && !feedback.enabled" role="alert" class="error">{{ error }}</p>
    <vocabulary-panel v-if="vocabularyMode" :guest-repository="repository" @back="vocabularyMode = false; readLocal()" @open-guest-run="vocabularyMode = false; attach($event)" /><session-player v-else-if="adapter" :key="activeRun" ref="player" :adapter="adapter" :locked="locked" :timezone="timezone" @exit="exit" @repeat="start($event.session_id,'extra')" @test-next="nextTest" @vocabulary="openVocabulary" />
    <template v-else-if="ready && state"><section v-if="selectedSession" class="panel lesson-main"><p class="muted">Выбранное занятие</p><h2>{{ selectedSession.title }}</h2><p v-if="selectedAccess?.locked" role="status">{{ selectedAccess.reason }}</p><afr-button v-else-if="selectedAccess" type="primary" :disabled="busy || locked" @click="start(selectedSession.id,'extra')">{{ selectedAccess.completed ? 'Пройти повторно' : 'Начать занятие' }}</afr-button></section>
      <section v-else-if="!progressOnly" class="panel lesson-main">
        <p class="muted">{{ lessonLevel==='PRE_A1'?'С нуля':lessonLevel }}<span v-if="currentRun || candidate"> · Около {{ currentRun?.snapshot.duration_minutes || candidate?.duration_minutes }} мин</span></p>
        <h2>{{ currentRun?.snapshot.title || candidate?.title || (error ? 'Материалы сейчас недоступны' : 'Вы всё прошли на этом уровне') }}</h2>
        <p class="muted">Несколько минут французского в удобном темпе. Прогресс сохраняется автоматически.</p>
        <afr-button v-if="currentRun" type="primary" :disabled="busy || locked" @click="open(currentRun.id)">Продолжить занятие</afr-button>
        <afr-button v-else-if="candidate" type="primary" :disabled="busy || dirty || locked" @click="start()">Начать занятие</afr-button>
        <p v-else>Новые занятия появятся после публикации.</p>
        <p v-if="currentRun && currentRun.snapshot.level !== state.current_level && candidate"><afr-button :disabled="busy || dirty || locked" @click="start()">Начать занятие нового уровня</afr-button></p>
      </section>
      <details v-if="!progressOnly" class="panel">
        <summary>Уровень занятий</summary>
        <div class="settings">
          <div class="practice-field"><label for="daily-guest-level">Уровень</label><afr-select id="daily-guest-level" v-model="level" native :disabled="busy"><afr-option v-for="item in levels" :key="item.code" :value="item.code" :label="item.title" /></afr-select></div>
          <afr-button type="primary" :loading="busy" @click="changeProfile">Сохранить уровень</afr-button>
        </div>
        <p class="muted">Начатое занятие останется доступным после смены уровня.</p>
      </details>
      <section v-if="progressOnly" class="panel">
        <h2>Прогресс в этом браузере</h2>
        <p>Текущий уровень: {{ state.current_level==='PRE_A1'?'С нуля':state.current_level }}. Завершено прохождений: {{ runs.filter((run)=>run.status==='completed').length }}. Выражений в словаре: {{ Object.keys(state.vocabulary).length }}.</p>
        <p>Учебные цели и автоматический переход уровня подтверждаются после входа и серверной проверки ответов.</p>
        <a href="/daily">Изменить уровень или продолжить занятие</a>
      </section>
      <details class="panel" :open="progressOnly">
        <summary>История занятий<span v-if="runs.length"> · {{ runs.length }}</span></summary>
        <p v-if="!runs.length">Начатых занятий пока нет.</p>
        <ol><li v-for="run in runs" :key="run.id">
          <a v-if="!repository?.temporary" :href="'/daily/guest/'+run.id" @click.prevent="open(run.id)">{{ run.snapshot.title }} · {{ statusTitle(run.status) }}</a>
          <afr-button v-else @click="open(run.id)">{{ run.snapshot.title }} · {{ statusTitle(run.status) }}</afr-button>
          <p class="muted">Начато <practice-date :value="run.started_at || run.local_date" :timezone="timezone" /><span v-if="run.finished_at"> · Завершено <practice-date :value="run.finished_at" :timezone="timezone" /></span></p>
          <p v-if="run.result">Самостоятельно: {{ run.result.independent_correct_steps }} из {{ run.result.total_steps }}</p>
        </li></ol>
      </details>
    </template>
  </section>
</template>
<style scoped>
p { line-height:1.65; margin:.5rem 0; }
h2 { font-size:1.2rem; font-weight:bold; margin-bottom:.7rem; }
.panel,.notice { padding:1rem; margin:1rem 0; border:1px solid #cbd5e1; border-radius:.25rem; background:white; }
.notice { background:#eff6ff; }
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
.lesson-main { border-color:#b8d7f5; background:linear-gradient(120deg,#fff,#f0f7ff); padding:1.4rem; }
.lesson-main h2 { font-size:1.55rem; }
.lesson-main :deep(button) { margin-top:.5rem; font-weight:600; }
.lesson-main :deep(button.type-primary:not(.is-disabled)) { background:#2b83e9; color:white; }
.lesson-main :deep(button.type-primary:hover:not(.is-disabled)) { background:#2174d2; }
.panel,.notice { border-radius:2px; }
@media(max-width:600px) { .lesson-main { padding:1rem; } .lesson-main h2 { font-size:1.3rem; } }
:deep(button) { max-width:100%; white-space:normal; }
:deep(:focus-visible) { outline:3px solid #2563eb; outline-offset:3px; }
@media(max-width:700px) { .settings { grid-template-columns:minmax(0,1fr); } }
</style>
