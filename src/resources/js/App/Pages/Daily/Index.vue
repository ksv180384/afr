<script setup>
import { randomUuid } from '@/App/Learning/randomUuid.js';
import ReviewSuggestionCard from '@/App/Components/Learning/ReviewSuggestionCard.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PracticeLayout from '@/App/Layouts/PracticeLayout.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import SessionPlayer from '@/App/Components/Learning/SessionPlayer.vue';
import GuestPractice from '@/App/Components/Learning/GuestPractice.vue';

import PracticeRouteTopic from '@/App/Components/Learning/PracticeRouteTopic.vue';
import PracticeStatus from '@/App/Components/Learning/PracticeStatus.vue';
import PracticeErrorNotice from '@/App/Components/Learning/PracticeErrorNotice.vue';
import PracticeDate from '@/App/Components/Learning/PracticeDate.vue';
import { returnToPractice, visitPracticeRun } from '@/App/Learning/practiceRunNavigation.js';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { createAccountAdapter, deliverAccountQueue, startAccountRun } from '@/App/Learning/accountAdapter.js';
import { guestSyncRevision } from '@/App/Learning/guestSyncState.js';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { practiceErrorMessage } from '@/App/Learning/practiceNotifications.js';
import { browserTimezone } from '@/App/Learning/practiceTime.js';

const props = defineProps({ authUser:Object, runId:String, guestRunId:String, enabled:Boolean, levels:Array, screen:String, selectedSession:Object, routeSelection:Object, embedded:Boolean });
const homeStore = usePracticeHome();
const repository = homeStore.accountRepository;
const owner = computed(() => props.authUser?.id ?? null);
const home = computed(() => homeStore.state.owner === owner.value ? homeStore.state.home : null);
const history = computed(() => homeStore.state.owner === owner.value ? homeStore.state.history : null);
const busy = ref(false);
const error = ref('');
const saving = ref({kind:'idle',message:''});
const timezone = computed(() => home.value?.profile.timezone_configured ? home.value.profile.timezone : browserTimezone());
const pendingStart = ref(false);
const conflictingRun = ref(null);
const player = ref(null);
const syncing = ref(false);
let disposed = false;
const onStatus = next => {
  if (next.kind === 'sending') return;
  if (saving.value.kind !== next.kind || saving.value.message !== next.message) saving.value = next;
};
const currentActor = actor => !disposed && actor === owner.value;
const actorStatus = actor => next => { if (currentActor(actor)) onStatus(next); };
const updatePending = async actor => {
  try {
    const rows = await repository.pending(actor);
    if (currentActor(actor)) pendingStart.value = rows.some(row => row.run_id === null);
  } catch {}
};
const feedback = usePracticeFeedback({source:'practice-session', status:saving, error});
const locked = computed(() => ['pending','auth','storage','conflict'].includes(saving.value.kind));
const adapter = computed(() => {
  const actor = owner.value;
  const runId = props.runId;
  return runId && actor ? createAccountAdapter({
    ownerUserId: actor, runId, repository, transport: accountTransport,
    onStatus: next => { if (currentActor(actor) && props.runId === runId) onStatus(next); },
    onMutation: () => homeStore.invalidate(actor),
  }) : null;
});
const kindTitle = kind => ({daily:'Занятие на сегодня',extra:'Дополнительная практика',review:'Повторение выражений',vocabulary_test:'Тест слов'})[kind] ?? kind;
const levelTitle = computed(() => (currentLesson.value?.level ?? home.value?.profile.current_level) === 'PRE_A1' ? 'С нуля' : (currentLesson.value?.level ?? home.value?.profile.current_level));
const currentLesson = computed(() => home.value?.active_run ?? home.value?.candidate);
const showNext = computed(() => {
  const route = home.value?.route;
  return home.value?.active_run || !(props.routeSelection?.level || props.routeSelection?.unit) ||
    route?.lessons.some(lesson => lesson.id === home.value?.candidate?.id);
});
const focused = computed(() => props.enabled && !!(props.authUser ? props.runId : props.guestRunId));
const pageTitle = computed(() => props.screen === 'progress' ? 'Мой прогресс' : props.runId || props.guestRunId ? 'Занятие' : 'Практика');
const selectedRun = computed(() => home.value?.route.lessons.find(lesson => lesson.id === props.selectedSession?.id)?.run_id);
const loadHome = () => homeStore.load(owner.value, 1, {selection: props.routeSelection ?? {}});
const loadError = failure => practiceErrorMessage(failure, 'Не удалось загрузить практику.');
const refreshSuggestion = () => perform(() => loadHome());
const openRun = id => visitPracticeRun('/daily/run/' + id, props.routeSelection);
const openState = state => state?.run_id && !disposed ? openRun(state.run_id) : undefined;
const continueRun = id => perform(() => openRun(id));
const exitRun = runId => perform(async () => {
  if (runId) {
    const actor = owner.value;
    const pending = await repository.pending(actor, runId);
    await Promise.all(pending.map(event => repository.mark(actor, event.local_id, 'dismissed')));
    await accountTransport.call('discard', runId, {});
    homeStore.invalidate(actor);
  }
  await returnToPractice(homeStore, owner.value);
});
const startLesson = (sessionId = null) => perform(async () => {
  const actor = owner.value;
  const path = await homeStore.start(actor, sessionId, actorStatus(actor));
  if (currentActor(actor)) await visitPracticeRun(path, props.routeSelection);
});
const reconnect = async () => {
  const actor = owner.value;
  if (syncing.value || busy.value || !actor || !props.enabled || disposed) return;
  homeStore.setOwner(actor);
  syncing.value = true;
  error.value = '';
  conflictingRun.value = null;
  let queueRun;
  try {
    const rows = await repository.pending(actor);
    if (currentActor(actor) && !rows.length && !props.runId) onStatus({kind:'idle',message:''});
    const queues = [...new Set(rows.map(row => row.run_id))].filter(id => id !== props.runId);
    for (const runId of queues) {
      if (actor !== owner.value || disposed) break;
      queueRun = runId;
      const restored = await deliverAccountQueue({ownerUserId:actor,runId,repository,transport:accountTransport,onMutation:()=>homeStore.invalidate(actor),onStatus:props.runId ? () => {} : actorStatus(actor)});
      if (runId === null && restored && currentActor(actor)) await openState(restored);
    }
  } catch (failure) {
    if (currentActor(actor) && !props.runId && failure.response?.data?.code === 'playback_conflict') conflictingRun.value = queueRun;
    else if (currentActor(actor)) error.value = practiceErrorMessage(failure, 'Не удалось отправить сохранённое действие.');
  } finally {
    await updatePending(actor);
    try {
      if (!props.runId && actor === owner.value && !disposed) await loadHome();
    } catch (failure) {
      if (currentActor(actor)) error.value ||= loadError(failure);
    }
    if (currentActor(actor)) syncing.value = false;
  }
};
const perform = async operation => {
  const actor = owner.value;
  if (busy.value || syncing.value) return;
  busy.value = true; error.value = '';
  try {
    return await operation();
  }
  catch (failure) {
    if (!currentActor(actor)) return;
    error.value = practiceErrorMessage(failure, 'Не удалось выполнить действие.');
  } finally {
    await updatePending(actor);
    if (currentActor(actor)) busy.value = false;
  }
};
const onOnline = () => {
  if (adapter.value && saving.value.kind === 'warning') adapter.value.sync().catch(() => {});
  else if (pendingStart.value || saving.value.kind === 'pending' || (!props.runId && !home.value)) reconnect();
};
const loadHistory = event => {
  if (event.target.open && !history.value) homeStore.loadHistory(owner.value).catch(failure => { error.value = practiceErrorMessage(failure, 'Не удалось загрузить историю занятий.'); });
};
const startReview = (mode = 'manual') => perform(async () => {
  const actor = owner.value;
  const path = await homeStore.startReview(actor, {mode, onStatus:actorStatus(actor)});
  if (currentActor(actor)) await visitPracticeRun(path, props.routeSelection);
});
const repeat = input => {
  if (input.session_id) return startLesson(input.session_id);
  return perform(async () => {
    const actor = owner.value;
    const profile = home.value?.profile ?? await accountTransport.profile();
    if (profile.owner_user_id !== actor || actor !== owner.value) throw new Error('Войдите в свой аккаунт, чтобы продолжить.');
    const state = await startAccountRun({ownerUserId:actor,repository,transport:accountTransport,onStatus:actorStatus(actor),profile,onMutation:()=>homeStore.invalidate(actor),input:{
      client_event_id:randomUuid(),expected_revision:profile.revision,timezone:timezone.value,
      kind:'vocabulary_test',continue_run_id:input.continue_run_id,
    }});
    if (currentActor(actor)) await openState(state);
  });
};
watch(guestSyncRevision, () => { if (owner.value && props.enabled) reconnect(); });
watch(owner, actor => {
  homeStore.setOwner(actor);
  error.value = '';
  pendingStart.value = false;
  conflictingRun.value = null;
  busy.value = false;
  syncing.value = false;
  onStatus({kind:'idle',message:''});
  if (actor && props.enabled) reconnect();
});
watch(() => props.routeSelection, () => {
  if (owner.value && props.enabled && !props.runId) {
    error.value = '';
    loadHome().catch(failure => { error.value = loadError(failure); });
  }
});
onMounted(() => { reconnect(); window.addEventListener('online',onOnline); });
onBeforeUnmount(() => { disposed = true; window.removeEventListener('online',onOnline); });
</script>
<template>
  <Head v-if="!embedded" :title="pageTitle"><meta name="robots" content="noindex, nofollow" /></Head>
  <component :is="embedded ? 'div' : PracticeLayout"
    v-bind="embedded ? {} : {authUser, title: pageTitle, active: screen === 'progress' ? 'progress' : 'learn', navigation: enabled && !focused, focused}">
    <div class="daily-account">
      <p v-if="!enabled" role="status">Раздел ещё не открыт для прохождения.</p>
      <p v-else-if="runId && !authUser" role="status"><Link href="/login?practice=1">Войдите в аккаунт</Link>, чтобы открыть сохранённое занятие.</p>
      <guest-practice v-else-if="!authUser" :run-id="guestRunId" :selected-session="selectedSession" :route-selection="routeSelection" :levels="levels" :progress-only="screen==='progress'" :trail="embedded" />
      <template v-else>
        <p v-if="saving.kind === 'auth'" class="muted"><Link href="/login?practice=1">Войти в аккаунт</Link>, чтобы продолжить занятие. Ваш ответ сохранён.</p>
        <p v-if="!error && saving.message && !feedback.enabled" role="status">{{ saving.message }}</p>
        <practice-error-notice v-if="error && !feedback.enabled && (adapter || home)" :message="error" />
        <div v-if="saving.kind === 'conflict' && (adapter || conflictingRun)" class="actions">
          <afr-button v-if="adapter" @click="player?.reconcile()">Загрузить состояние сервера</afr-button>
          <afr-button v-else :disabled="busy || syncing" @click="continueRun(conflictingRun)">Открыть сохранённое занятие</afr-button>
        </div>
        <session-player v-if="adapter" :key="runId" ref="player" :adapter="adapter" :locked="locked"
          :route-owner="owner" :route-store="homeStore" :navigation-busy="busy || syncing || pendingStart"
          @next-lesson="startLesson()" @continue-run="continueRun" @review="startReview" @repeat="repeat" @test-next="repeat" @vocabulary="router.visit('/daily/vocabulary')" @exit="exitRun" />
        <template v-else-if="home">
          <template v-if="screen!=='progress'">
            <practice-route-topic :trail="embedded" :route="home.route" :current-level="home.profile.current_level" :owner="owner" :selection="routeSelection"
              :busy="busy || syncing || pendingStart" @start="startLesson" @continue="continueRun">
            <section v-if="selectedSession" class="practice-panel lesson-main">
              <p class="eyebrow">Выбранное занятие</p><h2>{{ selectedSession.title }}</h2>
              <afr-button v-if="selectedRun" type="primary" :disabled="busy || syncing"
                @click="continueRun(selectedRun)">Продолжить занятие</afr-button>
              <afr-button v-else type="primary" :loading="busy" :disabled="pendingStart || syncing" @click="startLesson(selectedSession.id)">{{ selectedSession.completed ? 'Пройти повторно' : 'Начать занятие' }}</afr-button>
            </section>
            <section v-else-if="showNext" class="practice-panel lesson-main">
              <p class="eyebrow">{{ levelTitle }}<span v-if="currentLesson?.theme"> · {{ currentLesson.theme }}</span><span v-if="currentLesson"> · Около {{ currentLesson.duration_minutes }} мин</span></p>
              <h2>{{ currentLesson?.title || (home.route?.level.status === 'unavailable' ? 'Материалы ещё недоступны' : 'Вы всё прошли на этом уровне') }}</h2>
              <p v-if="currentLesson?.goal">{{ currentLesson.goal }}</p>
              <div class="actions practice-cta">
                <afr-button v-if="home.active_run" type="primary" :disabled="busy || syncing" @click="continueRun(home.active_run.id)">Продолжить занятие</afr-button>
                <afr-button v-else-if="home.candidate" type="primary" :loading="busy" :disabled="pendingStart || syncing" @click="startLesson()">Начать занятие</afr-button>
                <Link v-else href="/daily/vocabulary">Открыть повторение</Link>
              </div>
            </section>
            </practice-route-topic>
            <div v-if="!embedded" class="daily-overview"><Link href="/profile">Изменить уровень в профиле</Link></div>
            <review-suggestion-card v-if="!embedded" :suggestion="home.review_suggestion" :owner="owner" :store="homeStore" :busy="busy || syncing || pendingStart"
              @start="startReview('suggested')" @dismissed="refreshSuggestion" />
          </template>
          <template v-else>
            <section class="practice-panel"><h2>Ваш прогресс</h2><div class="progress-stats"><p>Уровень <strong>{{ home.profile.current_level === 'PRE_A1' ? 'С нуля' : home.profile.current_level }}</strong></p><p>Занятий завершено <strong>{{ home.progress?.completed_runs || 0 }}</strong></p></div><Link href="/profile">Изменить уровень и часовой пояс</Link></section>
            <section v-if="home.progress" class="practice-panel">
              <h2>Следующий уровень</h2>
              <p v-if="home.progress.completion_route?.total && home.progress.completion_route.next_level">Для перехода на {{ home.progress.completion_route.next_level }} завершите опубликованные занятия текущего уровня: {{ home.progress.completion_route.completed }} из {{ home.progress.completion_route.total }}. Уровень в профиле повысится автоматически.</p>
              <p v-else-if="!home.progress.completion_route?.next_level">Это последний уровень курса.</p>
              <p v-else>Занятия этого уровня ещё готовятся.</p>
              <p v-if="home.progress.latest_level_event?.source==='automatic'">Ваш уровень повышен до {{ home.progress.latest_level_event.new_level }}.</p>
            </section>
          </template>
          <details v-if="screen==='progress'" class="practice-panel history" @toggle="loadHistory"><summary>История занятий<span v-if="history?.total"> · {{ history.total }}</span></summary>
            <template v-if="history">
            <p v-if="!history.data.length" class="muted">Здесь появятся ваши занятия.</p>
            <ol><li v-for="run in history.data" :key="run.id"><Link :href="'/daily/run/'+run.id">{{ kindTitle(run.kind) }}</Link><practice-status class="history-status" :status="run.status" :label="run.status === 'completed' ? 'Завершено' : null" />
              <p class="muted">Начато <practice-date :value="run.started_at || run.local_date" :timezone="timezone" /><span v-if="run.finished_at"> · Завершено <practice-date :value="run.finished_at" :timezone="timezone" /></span></p>
              <p v-if="run.result">Самостоятельно: {{ run.result.independent_correct_steps }} из {{ run.result.total_steps }}</p>
            </li></ol>
            <div v-if="history.last_page>1" class="actions"><afr-button :disabled="busy || history.current_page<=1" @click="perform(()=>homeStore.loadHistory(owner, history.current_page-1))">Назад</afr-button><span>{{ history.current_page }} из {{ history.last_page }}</span><afr-button :disabled="busy || history.current_page>=history.last_page" @click="perform(()=>homeStore.loadHistory(owner, history.current_page+1))">Дальше</afr-button></div>
            </template>
          </details>
        </template>
        <practice-error-notice v-else-if="error" title="Учебный маршрут недоступен" :message="error" :announce="!feedback.enabled" />
        <p v-else role="status" class="muted">Загружаем учебный маршрут…</p>
      </template>
    </div></component>
</template>
<style scoped>
h2 { font-size:1.2rem; font-weight:bold; margin-bottom:.6rem; }
p { line-height:1.6; margin:.5rem 0; }
.eyebrow { font-size:.875rem; color:#426a89; }
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
</style>
