<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AfrCoverCard from '@/App/Components/Card/AfrCoverCard.vue';
import PracticeNavigation from './PracticeNavigation.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import dailyPracticeBackground from './assets/daily-practice-paris.webp';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
const props=defineProps({authUser:Object});
const store=usePracticeHome();
const owner=computed(()=>props.authUser?.id ?? null);
const home=computed(()=>store.state.owner===owner.value && store.state.ready ? store.state.home : null);
const feedback=usePracticeFeedback({source:'practice-card',status:computed(()=>store.state.owner===owner.value ? store.state.saving : null),error:computed(()=>store.state.owner===owner.value ? store.state.error || home.value?.content_error : '')});
const session=computed(()=>home.value?.active_run || home.value?.candidate);
const level=computed(()=>(session.value?.level ?? home.value?.profile.current_level)==='PRE_A1' ? 'С нуля' : (session.value?.level ?? home.value?.profile.current_level));
const path=computed(()=>home.value?.active_run ? '/daily/'+(owner.value===null?'guest/':'run/')+home.value.active_run.id : null);
let stop;
const refresh=()=>store.refresh(owner.value).catch(()=>{});
let opening = false;
const start = async () => {
  if (opening) return;
  opening = true;
  try {
    const url = await store.start(owner.value);
    await new Promise(resolve => router.visit(url, {onFinish: () => resolve()}));
  } catch {
    // The shared store exposes the error and retains an unconfirmed start.
  } finally {
    opening = false;
  }
};
onMounted(()=>{stop=watch(owner,()=>store.load(owner.value).catch(()=>{}),{immediate:true});window.addEventListener('focus',refresh);});
onBeforeUnmount(()=>{stop?.();window.removeEventListener('focus',refresh);});
</script>
<template>
  <afr-cover-card title="Практика на сегодня" :image="dailyPracticeBackground">
    <template #summary>
      <span v-if="session?.duration_minutes">{{ session.duration_minutes }} мин</span>
    </template>
    <template #default="{ inDialog }">
      <div class="daily-session-card" :aria-busy="store.state.loading || store.state.busy">
        <p v-if="!home && (!store.state.error || !feedback.enabled || inDialog)" class="daily-notice" role="status">
          {{ store.state.error || 'Проверяем сохранённую практику…' }}
        </p>
        <template v-if="home">
          <div class="daily-summary">
            <div class="daily-meta">
              <span v-if="level" class="daily-level">
                <daily-practice-icon name="progress" />
                <span class="sr-only">Текущий уровень</span>
                {{ level }}
              </span>
              <span v-if="session?.duration_minutes" class="daily-time">
                <daily-practice-icon name="clock" />
                Около {{ session.duration_minutes }} мин
              </span>
            </div>
            <h3 v-if="session?.title" class="daily-title">{{ session.title }}</h3>
            <p v-if="session?.theme" class="daily-theme"><span class="sr-only">Тема занятий </span>{{ session.theme }}</p>
          </div>
          <p v-if="home.active_run && home.active_run.level !== home.profile.current_level" class="daily-notice">
            Начатое занятие сохраняет прежний уровень.
          </p>
          <p v-if="home.content_error && (!feedback.enabled || inDialog)" class="daily-notice" role="status">
            {{ home.content_error }}
          </p>
          <Link v-if="path" :href="path" class="daily-start">
            <daily-practice-icon name="play" />
            Продолжить занятие
          </Link>
          <afr-button v-else-if="home.candidate" type="primary" class="daily-start" :loading="store.state.busy" @click="start">
            <daily-practice-icon v-if="!store.state.busy" name="play" />
            {{ store.state.busy ? 'Открываем занятие…' : 'Начать занятие' }}
          </afr-button>
          <p v-else class="daily-notice">Новых уроков пока нет. Можно повторить изученное.</p>
        </template>
        <p v-if="home && store.state.error && (!feedback.enabled || inDialog)" class="daily-notice" role="alert">
          {{ store.state.error }} Данные карточки могли устареть.
        </p>
        <afr-button
          v-if="store.state.error"
          class="daily-retry"
          :disabled="store.state.loading || store.state.busy"
          @click="refresh"
        >
          Обновить карточку
        </afr-button>
        <p v-if="store.state.saving?.message && (!feedback.enabled || inDialog)" class="daily-notice" role="status">
          {{ store.state.saving.message }}
        </p>
        <practice-navigation compact aria-label="Личная практика" />
      </div>
    </template>
  </afr-cover-card>
</template>

<style scoped>
.daily-session-card { @apply grid min-w-0 gap-3 text-slate-700; overflow-wrap: anywhere; }
.daily-summary { @apply grid gap-1; }
.daily-meta { @apply mb-1 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs font-medium; }
.daily-meta span:not(.sr-only) { @apply inline-flex items-center gap-1.5; }
.daily-meta svg { @apply size-3.5; }
.daily-level { @apply rounded-full bg-sky-50 px-2 py-1 text-sky-800; }
.daily-time { @apply text-slate-600; }
.daily-title { @apply text-lg font-bold leading-snug text-sky-950; }
.daily-theme { @apply text-sm leading-snug text-slate-600; }

.daily-start {
  @apply flex min-h-11 w-full items-center justify-center gap-2 whitespace-normal rounded-lg px-3 py-2
    text-center text-sm font-bold leading-snug text-white;
}

.daily-start svg { @apply m-0 size-4 fill-current stroke-0; }
.daily-retry { @apply min-h-11 whitespace-normal; }
.daily-notice { @apply rounded-lg bg-sky-50 px-3 py-2 text-sm leading-snug text-slate-700; }
</style>
