<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PracticeLayout from '@/App/Layouts/PracticeLayout.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import SessionPlayer from '@/App/Components/Learning/SessionPlayer.vue';
import GuestPractice from '@/App/Components/Learning/GuestPractice.vue';

import PracticeDate from '@/App/Components/Learning/PracticeDate.vue';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { createAccountAdapter, deliverAccountQueue, startAccountRun } from '@/App/Learning/accountAdapter.js';
import { guestSyncRevision } from '@/App/Learning/guestSyncState.js';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { browserTimezone } from '@/App/Learning/practiceTime.js';

const props = defineProps({ authUser:Object, runId:String, guestRunId:String, enabled:Boolean, levels:Array, screen:String, selectedSession:Object });
const homeStore = usePracticeHome();
const repository = homeStore.accountRepository;
const owner = props.authUser?.id;
const home = ref(null);
const history = ref(null);
const busy = ref(false);
const error = ref('');
const saving = ref({kind:'idle',message:''});
const timezone = ref(browserTimezone());
const pendingStart = ref(false);
const pendingActions = ref(false);
const player = ref(null);
const syncing = ref(false);
let disposed = false;
const retryAction = ref(null);
const onStatus = next => {
  if (next.kind === 'sending') return;
  if (saving.value.kind !== next.kind || saving.value.message !== next.message) saving.value = next;
};
const feedback = usePracticeFeedback({source:'practice-session',status:saving,error});
const locked = computed(() => ['pending','auth','storage'].includes(saving.value.kind));
const adapter = props.runId && owner ? createAccountAdapter({ownerUserId:owner,runId:props.runId,repository,transport:accountTransport,onStatus}) : null;
const statusTitle = status => ({in_progress:'Начато',paused:'Отложено',completed:'Завершено'})[status] ?? status;
const kindTitle = kind => ({daily:'Занятие на сегодня',extra:'Дополнительная практика',review:'Повторение выражений',vocabulary_test:'Тест слов'})[kind] ?? kind;
const levelTitle = computed(() => (currentLesson.value?.level ?? home.value?.profile.current_level) === 'PRE_A1' ? 'С нуля' : (currentLesson.value?.level ?? home.value?.profile.current_level));
const currentLesson = computed(() => home.value?.run?.status !== 'completed' && home.value?.run ? home.value.run : home.value?.candidate);
const loadHome = async (page = history.value?.current_page || 1) => {
  const result = await homeStore.refresh(owner,page,{includeHistory:props.screen === 'progress' || !!history.value});
  if (!result || disposed) return;
  home.value = result.home;
  history.value = result.history;
  timezone.value = result.home.profile.persisted ? result.home.profile.timezone : browserTimezone();
};
const openState = state => { if (state?.run_id && !disposed) router.visit('/daily/run/' + state.run_id); };
const reconnect = async () => {
  if (syncing.value || busy.value || !owner || !props.enabled || disposed) return;
  syncing.value = true;
  error.value = '';
  try {
    const rows = await repository.pending(owner);
    if (!rows.length && !props.runId) onStatus({kind:'idle',message:''});
    const queues = [...new Set(rows.map(row => row.run_id))].filter(id => id !== props.runId);
    for (const runId of queues) {
      const restored = await deliverAccountQueue({ownerUserId:owner,runId,repository,transport:accountTransport,onStatus:props.runId ? () => {} : onStatus});
      if (runId === null && restored) { openState(restored); }
    }
  } catch (failure) {
    error.value = failure.response?.data?.message || failure.message || 'Не удалось восстановить сохранённое действие. Повторите вручную.';
  } finally {
    try { const rows = await repository.pending(owner); pendingStart.value = rows.some(row => row.run_id === null); pendingActions.value = rows.length > 0; } catch {}
    try {
      if (!props.runId) await loadHome();
    } catch (failure) {
      error.value ||= failure.response?.data?.message || 'Не удалось загрузить практику. Нажмите «Повторить».';
    }
    syncing.value = false;
  }
};
const perform = async operation => {
  if (busy.value || syncing.value) return;
  busy.value = true; error.value = '';
  try { const result=await operation(); retryAction.value=null; return result; }
  catch (failure) {
    const status = failure.response?.status;
    retryAction.value = !status || status === 409 || status === 429 || status >= 500 ? operation : null;
    error.value = failure.response?.data?.message || failure.message || 'Не удалось открыть занятие.';
  } finally {
    try { const rows = await repository.pending(owner); pendingStart.value = rows.some(row => row.run_id === null); pendingActions.value = rows.length > 0; } catch {}
    busy.value = false;
  }
};
const retryFailed = () => retryAction.value ? perform(retryAction.value) : reconnect();
const onOnline = () => { if (pendingStart.value || saving.value.kind === 'pending') reconnect(); };
const loadHistory = event => {
  if (event.target.open && !history.value && !busy.value) perform(async () => { history.value = await accountTransport.history(1); });
};
const start = (kind = 'daily', replace = false) => perform(async () => {
  const state = await startAccountRun({ownerUserId:owner,repository,transport:accountTransport,onStatus,input:{
    client_event_id:crypto.randomUUID(),expected_revision:home.value.profile.revision,kind,timezone:timezone.value,
    ...(replace ? {replace_assignment:true} : {}),...(kind === 'extra' ? {session_id:home.value.candidate.id} : {}),
  }});
  openState(state);
});
const repeat = input => perform(async () => {
  const checkedProfile = home.value ? null : await accountTransport.profile();
  const profile = checkedProfile ?? home.value.profile;
  if (profile.owner_user_id !== owner) throw new Error('Войдите в свой аккаунт, чтобы продолжить.');
  const state = await startAccountRun({ownerUserId:owner,repository,transport:accountTransport,onStatus,profile:checkedProfile,input:{
    client_event_id:crypto.randomUUID(),expected_revision:profile.revision,timezone:timezone.value,
    ...(input.continue_run_id ? {kind:'vocabulary_test',continue_run_id:input.continue_run_id} : {kind:'extra',session_id:input.session_id}),
  }});
  openState(state);
});
watch(guestSyncRevision, () => { if (owner && props.enabled) reconnect(); });
onMounted(() => { reconnect(); window.addEventListener('online',onOnline); });
onBeforeUnmount(() => { disposed = true; window.removeEventListener('online',onOnline); });
</script>
<template>
  <Head :title="screen==='progress'?'Прогресс':'Практика на сегодня'"><meta name="robots" content="noindex, nofollow" /></Head>
  <practice-layout :auth-user="authUser" :title="screen==='progress'?'Прогресс':'Практика на сегодня'" :active="screen || 'today'" :navigation="enabled">
    <div class="daily-account">
      <p v-if="!enabled" role="status">Раздел ещё не открыт для прохождения.</p>
      <guest-practice v-else-if="!authUser" :run-id="guestRunId" :selected-session="selectedSession" :levels="levels" :progress-only="screen==='progress'" />
      <template v-else>
        <p v-if="saving.kind === 'auth'" class="muted"><Link href="/login?practice=1">Войти в аккаунт</Link>, чтобы продолжить занятие. Ваш ответ сохранён.</p>
        <p v-if="saving.message && !feedback.enabled" role="status">{{ saving.message }}</p>
        <p v-if="error" role="alert">{{ error }}</p>
        <div v-if="pendingActions || retryAction || (!home && error)" class="actions"><afr-button :disabled="busy || syncing" @click="retryFailed">Повторить</afr-button><span class="muted">Автоматические повторы остановлены. Сохранённое действие можно отправить вручную.</span></div>
        <session-player v-if="adapter" ref="player" :adapter="adapter" :locked="locked" :timezone="runId ? undefined : timezone" @repeat="repeat" @test-next="repeat" @vocabulary="router.visit('/daily/vocabulary')" @exit="router.visit('/daily')" />
        <template v-else-if="home">
          <template v-if="screen!=='progress'">
            <section v-if="selectedSession" class="panel lesson-main">
              <p class="eyebrow">Выбранное занятие</p><h2>{{ selectedSession.title }}</h2>
              <afr-button type="primary" :loading="busy" :disabled="pendingStart" @click="repeat({session_id:selectedSession.id})">{{ selectedSession.completed ? 'Пройти повторно' : 'Начать занятие' }}</afr-button>
            </section>
            <section v-else class="panel lesson-main">
              <p class="eyebrow">{{ levelTitle }}<span v-if="currentLesson?.theme"> · {{ currentLesson.theme }}</span><span v-if="currentLesson"> · Около {{ currentLesson.duration_minutes }} мин</span></p>
              <h2>{{ currentLesson?.title || 'Вы всё прошли на этом уровне' }}</h2>
              <p v-if="home.activity_today" class="muted">Сегодня вы уже занимались. Можно продолжить практику.</p>
              <p v-else-if="currentLesson" class="muted">Несколько минут французского в удобном темпе. Прогресс сохраняется автоматически.</p>
              <div class="actions">
                <Link v-if="home.run && home.run.status !== 'completed'" class="primary-link" :href="'/daily/run/'+home.run.id">Продолжить занятие</Link>
                <afr-button v-else-if="home.candidate" type="primary" :loading="busy" :disabled="pendingStart" @click="start('daily',!!home.run)">Начать занятие</afr-button>
                <Link v-else href="/practice">Выбрать материал</Link>
              </div>
              <p v-if="home.run && home.run.level !== home.profile.current_level && home.candidate?.level === home.profile.current_level"><afr-button :disabled="busy || pendingStart" @click="start('daily',true)">Начать занятие уровня {{ levelTitle }}</afr-button></p>
            </section>
            <div class="daily-overview"><span>Уровень: <strong>{{ levelTitle }}</strong></span><Link href="/profile">Изменить в профиле</Link><span v-if="home.progress">Завершено занятий: <strong>{{ home.progress.completed_runs }}</strong></span><Link href="/daily/progress">Мой прогресс</Link></div>
            <details class="panel"><summary>Другие занятия</summary><div class="actions">
              <afr-button v-if="home.candidate" :disabled="busy || pendingStart" @click="start('extra')">Дополнительная практика</afr-button>
              <afr-button :disabled="busy || pendingStart" @click="start('review')">Повторить выражения</afr-button>
              <afr-button :disabled="busy || pendingStart" @click="start('vocabulary_test')">Тест словаря</afr-button>
            </div></details>
          </template>
          <template v-else>
            <section class="panel"><h2>Ваш прогресс</h2><div class="progress-stats"><p>Уровень <strong>{{ levelTitle }}</strong></p><p>Занятий завершено <strong>{{ home.progress?.completed_runs || 0 }}</strong></p><p v-if="home.progress?.required">Целей освоено <strong>{{ home.progress.mastered }} из {{ home.progress.required }}</strong></p></div><Link href="/profile">Изменить уровень и часовой пояс</Link></section>
            <section v-if="home.progress" class="panel">
              <h2>Следующий уровень</h2>
              <p v-if="home.progress.completion_route?.total && home.progress.completion_route.next_level">Для перехода на {{ home.progress.completion_route.next_level }} завершите опубликованные занятия текущего уровня: {{ home.progress.completion_route.completed }} из {{ home.progress.completion_route.total }}. Уровень в профиле повысится автоматически.</p>
              <p v-else-if="!home.progress.enabled">{{ home.progress.disabled_reason }}</p>
              <template v-else>
                <p>До {{ home.progress.next_level }}: освоить {{ home.progress.required_to_master }} целей и пройти два разных контроля.</p>
                <p>Контролей пройдено: {{ home.progress.checkpoint_count }}.</p>
                <div class="actions"><afr-button v-for="(item,index) in home.progress.available_checkpoints" :key="item.id" :disabled="busy || pendingStart" @click="repeat({session_id:item.id})">Контроль {{ index+1 }}: {{ item.title }}</afr-button></div>
                <details class="progress-details"><summary>Как учитывается прогресс</summary>
                  <p>Цель подтверждается двумя самостоятельными ответами в разных занятиях. Два разных контроля нужно пройти не ниже {{ home.progress.policy.checkpoint_score_percent }}% в разные дни.</p>
                  <p v-if="home.progress.manual_guard">После ручной смены уровня нужен новый контроль.</p>
                  <ul><li v-for="goal in home.progress.goals" :key="goal.id">{{ goal.title }} — {{ goal.mastered ? 'Освоена' : 'В процессе' }}</li></ul>
                </details>
              </template>
              <p v-if="home.progress.latest_level_event?.source==='automatic'">Ваш уровень повышен до {{ home.progress.latest_level_event.new_level }}.</p>
            </section>
          </template>
          <details class="panel history" :open="screen==='progress'" @toggle="loadHistory"><summary>История занятий<span v-if="history?.total"> · {{ history.total }}</span></summary>
            <template v-if="history">
            <p v-if="!history.data.length" class="muted">Здесь появятся ваши занятия.</p>
            <ol><li v-for="run in history.data" :key="run.id"><Link :href="'/daily/run/'+run.id">{{ kindTitle(run.kind) }}</Link><span class="history-status">{{ statusTitle(run.status) }}</span>
              <p class="muted">Начато <practice-date :value="run.started_at || run.local_date" :timezone="timezone" /><span v-if="run.finished_at"> · Завершено <practice-date :value="run.finished_at" :timezone="timezone" /></span></p>
              <p v-if="run.result">Самостоятельно: {{ run.result.independent_correct_steps }} из {{ run.result.total_steps }}</p>
            </li></ol>
            <div v-if="history.last_page>1" class="actions"><afr-button :disabled="busy || history.current_page<=1" @click="perform(()=>loadHome(history.current_page-1))">Назад</afr-button><span>{{ history.current_page }} из {{ history.last_page }}</span><afr-button :disabled="busy || history.current_page>=history.last_page" @click="perform(()=>loadHome(history.current_page+1))">Дальше</afr-button></div>
            </template>
          </details>
        </template>
        <p v-else role="status" class="muted">Загружаем вашу практику…</p>
      </template>
    </div></practice-layout>
</template>
<style scoped>
h2 { font-size:1.2rem; font-weight:bold; margin-bottom:.6rem; }
p { line-height:1.6; margin:.5rem 0; }
.panel { padding:1rem; margin:1rem 0; background:white; border:1px solid #cbd5e1; border-radius:2px; }
.lesson-main { border-color:#b8d7f5; background:linear-gradient(120deg,#fff,#f0f7ff); padding:1.4rem; }
.lesson-main h2 { font-size:1.55rem; }
.eyebrow { font-size:.875rem; color:#426a89; }
.lesson-main .actions { margin-top:1rem; }
.lesson-main :deep(button),.primary-link { font-weight:600; }
.primary-link { display:inline-flex; align-items:center; justify-content:center; background:#2b83e9; border:1px solid #2b83e9; color:white; padding:5px 12px; border-radius:4px; text-decoration:none; }
.lesson-main :deep(button.type-primary:not(.is-disabled)) { background:#2b83e9; color:white; }
.lesson-main :deep(button.type-primary:hover:not(.is-disabled)) { background:#2174d2; }
.primary-link:hover { background:#3b82f6; }
.daily-overview { display:flex; flex-wrap:wrap; gap:.4rem 1rem; align-items:center; font-size:.9rem; margin:1rem 0; }
.actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:.75rem; }
summary { cursor:pointer; font-weight:600; }
.progress-details { margin-top:1rem; }
.progress-stats { display:flex; flex-wrap:wrap; gap:.5rem 1.5rem; }
.progress-stats strong { display:block; font-size:1.4rem; }
a { color:#155e91; text-decoration:underline; }
.muted { color:#526a7b; font-size:.9rem; }
.history li { padding:.7rem 0; border-bottom:1px solid #e2e8f0; }
.history-status { margin-left:.7rem; font-size:.875rem; color:#526a7b; }
:deep(button) { max-width:100%; white-space:normal; }
:deep(:focus-visible) { outline:2px solid #2563eb; outline-offset:3px; }
@media(max-width:600px) { .lesson-main { padding:1rem; } .lesson-main h2 { font-size:1.3rem; } }
</style>
