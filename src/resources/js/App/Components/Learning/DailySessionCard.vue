<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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
  <section class="daily-session-card" :style="{ backgroundImage: `url(${dailyPracticeBackground})` }" aria-labelledby="daily-card-heading" :aria-busy="store.state.loading || store.state.busy">
    <h2 id="daily-card-heading">Практика на сегодня</h2>
    <p v-if="!home && (!store.state.error || !feedback.enabled)" class="daily-notice" role="status">{{ store.state.error || 'Проверяем сохранённую практику…' }}</p>
    <template v-else-if="home">
      <div class="daily-summary">
        <div class="daily-detail daily-level">
          <daily-practice-icon name="progress" />
          <p><span class="daily-label">Текущий уровень:</span><strong>{{ level }}</strong></p>
        </div>
        <div v-if="session?.theme" class="daily-detail daily-theme">
          <daily-practice-icon name="book" />
          <p><span class="daily-label">Тема:</span><strong>{{ session.theme }}</strong></p>
        </div>
        <div v-if="session" class="daily-detail daily-title">
          <daily-practice-icon name="message" />
          <p :title="session.title">{{ session.title }}</p>
        </div>
        <div v-if="session?.duration_minutes" class="daily-detail daily-duration">
          <daily-practice-icon name="clock" />
          <p>Около {{ session.duration_minutes }} мин</p>
        </div>
      </div>
      <p v-if="home.active_run && home.active_run.level!==home.profile.current_level" class="daily-notice">Начатое занятие сохраняет прежний уровень.</p>
      <p v-if="home.content_error && !feedback.enabled" class="daily-notice" role="status">{{ home.content_error }}</p>
      <div v-if="path || home.candidate" class="daily-action">
        <Link v-if="path" :href="path" class="daily-start">
          <span class="daily-play"><daily-practice-icon name="play" /></span>
          Продолжить занятие
        </Link>
        <afr-button v-else type="primary" class="daily-start" :loading="store.state.busy" @click="start">
          <span v-if="!store.state.busy" class="daily-play"><daily-practice-icon name="play" /></span>
          {{ store.state.busy ? 'Открываем занятие…' : 'Начать занятие' }}
        </afr-button>
      </div>
      <p v-else class="daily-notice">Новых уроков пока нет. Можно повторить изученное.</p>
    </template>
    <p v-if="home && store.state.error && !feedback.enabled" class="daily-notice" role="alert">{{ store.state.error }} Данные карточки могли устареть.</p>
    <afr-button v-if="store.state.error" class="daily-retry" :disabled="store.state.loading || store.state.busy" @click="refresh">Обновить карточку</afr-button>
    <p v-if="store.state.saving?.message && !feedback.enabled" class="daily-notice" role="status">{{ store.state.saving.message }}</p>
    <nav class="daily-links" aria-label="Личная практика">
      <Link href="/practice"><daily-practice-icon name="list" /><span>Учиться</span></Link>
      <Link href="/daily/vocabulary"><daily-practice-icon name="book" /><span>Повторять</span></Link>
      <Link href="/daily/progress"><daily-practice-icon name="progress" /><span>Мой прогресс</span></Link>
    </nav>
  </section>
</template>
<style scoped>
.daily-session-card {
  display: grid;
  gap: .65rem;
  min-width: 0;
  padding: var(--practice-pad);
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-radius);
  color: var(--practice-ink);
  background-color: var(--practice-accent);
  background-position: center;
  background-size: cover;
  overflow-wrap: anywhere;
}
h2 { padding: .4rem .6rem; border-radius: var(--practice-control-radius); background: var(--practice-surface); font-size: 1.2rem; line-height: 1.3; font-weight: 750; }
.daily-summary, .daily-links, .daily-notice { border: 1px solid var(--practice-border); border-radius: var(--practice-control-radius); background: var(--practice-surface); }
.daily-detail { display: flex; align-items: center; gap: .5rem; min-width: 0; padding: .45rem .6rem; color: var(--practice-blue); }
.daily-detail + .daily-detail { border-top: 1px solid #e4ebf3; }
.daily-detail p { min-width: 0; font-size: .875rem; color: var(--practice-ink); }
.daily-label { margin-inline-end: .35rem; color: var(--practice-muted); }
.daily-title p { font-weight: 700; }
.daily-start {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  width: 100%;
  min-height: var(--practice-target);
  padding: .45rem .65rem;
  border: 1px solid var(--practice-blue);
  border-radius: var(--practice-control-radius);
  background: var(--practice-blue);
  color: #fff;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
  text-decoration: none;
  white-space: normal;
}
.daily-start:disabled { opacity: .75; }
.daily-play { display: flex; flex-shrink: 0; }
.daily-play :deep(svg) { width: 20px; height: 20px; fill: currentColor; stroke-width: 0; }
.daily-links { display: grid; padding: .2rem .35rem; }
.daily-links a { display: flex; align-items: center; gap: .5rem; min-width: 0; min-height: var(--practice-target); padding: .25rem; color: var(--practice-blue); font-size: .875rem; }
.daily-links a:hover { text-decoration: underline; }
.daily-links a > :first-child { width: 18px; height: 18px; }
.daily-notice { padding: .5rem .65rem; font-size: .875rem; line-height: 1.5; }
.daily-retry { width: 100%; min-height: var(--practice-target); white-space: normal; }
</style>
