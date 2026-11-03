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
const session=computed(()=>home.value?.run || home.value?.candidate);
const level=computed(()=>(session.value?.level ?? home.value?.profile.current_level)==='PRE_A1' ? 'С нуля' : (session.value?.level ?? home.value?.profile.current_level));
const path=computed(()=>home.value?.run ? '/daily/'+(owner.value===null?'guest/':'run/')+home.value.run.id : null);
let stop;
const refresh=()=>store.refresh(owner.value).catch(()=>{});
const start=()=>store.start(owner.value).then((url)=>router.visit(url)).catch(()=>{});
onMounted(()=>{stop=watch(owner,refresh,{immediate:true});window.addEventListener('focus',refresh);});
onBeforeUnmount(()=>{stop?.();window.removeEventListener('focus',refresh);});
</script>
<template>
  <section class="daily-session-card" :style="{ backgroundImage: `url(${dailyPracticeBackground})` }" aria-labelledby="daily-card-heading" :aria-busy="store.state.loading || store.state.busy">
    <h2 id="daily-card-heading">Практика на сегодня</h2>
    <p v-if="!home && (!store.state.error || !feedback.enabled)" class="daily-notice" role="status">{{ store.state.error || 'Проверяем сохранённую практику…' }}</p>
    <template v-else>
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
      <p v-if="home.run && home.run.level!==home.profile.current_level" class="daily-notice">Начатое занятие сохраняет прежний уровень. Его можно закончить либо отложить в разделе «Сегодня».</p>
      <p v-if="home.activity_today" class="daily-notice">Сегодня уже занимались. Можно продолжить практику.</p>
      <p v-if="home.content_error && !feedback.enabled" class="daily-notice" role="status">{{ home.content_error }}</p>
      <div v-if="path || home.candidate" class="daily-action">
        <Link v-if="path" :href="path" class="daily-start">
          <span class="daily-play"><daily-practice-icon name="play" /></span>
          {{ home.run.status==='completed' ? 'Посмотреть результат' : 'Продолжить занятие' }}
        </Link>
        <afr-button v-else type="primary" class="daily-start" :loading="store.state.busy" @click="start">
          <span v-if="!store.state.busy" class="daily-play"><daily-practice-icon name="play" /></span>
          {{ store.state.busy ? 'Открываем занятие…' : 'Начать занятие' }}
        </afr-button>
      </div>
      <p v-else class="daily-notice">Новых доступных занятий этого уровня пока нет. Доступны повторение, словарь и ручной выбор уровня.</p>
    </template>
    <p v-if="home && store.state.error && !feedback.enabled" class="daily-notice" role="alert">{{ store.state.error }} Данные карточки могли устареть.</p>
    <afr-button v-if="store.state.error" class="daily-retry" :disabled="store.state.loading || store.state.busy" @click="refresh">Обновить карточку</afr-button>
    <p v-if="store.state.saving?.message && !feedback.enabled" class="daily-notice" role="status">{{ store.state.saving.message }}</p>
    <nav class="daily-links" aria-label="Личная практика">
      <Link href="/daily"><daily-practice-icon name="calendar" /><span>Сегодня</span><daily-practice-icon name="chevron" /></Link>
      <Link href="/daily/vocabulary"><daily-practice-icon name="book" /><span>Мой словарь</span><daily-practice-icon name="chevron" /></Link>
      <Link href="/daily/progress"><daily-practice-icon name="progress" /><span>Прогресс</span><daily-practice-icon name="chevron" /></Link>
      <Link href="/practice"><daily-practice-icon name="list" /><span>Все задания по уровням</span><daily-practice-icon name="chevron" /></Link>
    </nav>
  </section>
</template>
<style scoped>
.daily-session-card {
  @apply rounded-sm;
  min-width: 0;
  padding: 12px 14px 5px;
  border: 2px solid #91bfdf;
  background-color: #d5eaff;
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
  box-shadow: inset 0 0 0 2px #ffffff60, 0 1px 3px #174c8326;
  color: #062874;
  overflow-wrap: anywhere;
}

h2 {
  width: fit-content;
  max-width: 100%;
  margin-inline: auto;
  padding: 7px 10px;
  border-radius: 9px;
  background: #f6fbffdf;
  color: #061869;
  font-size: 20px;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
}

.daily-summary {
  margin: 3px 20px 0 10px;
  border-radius: 10px;
  background: linear-gradient(90deg, #f5faffeb, #f5faffce 75%, #f5faff78);
}

.daily-detail {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  padding: 7px 9px;
  color: #06449f;
}

.daily-detail + .daily-detail { border-top: 1px solid #a8bdce70; }
.daily-detail p { min-width: 0; font-size: 13px; line-height: 1.45; color: #062874; }
.daily-label { display: block; margin-bottom: 1px; color: #163f87; }
.daily-detail strong { display: block; font-weight: 700; }
.daily-title p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.daily-duration { padding-top: 9px; padding-bottom: 9px; }

.daily-action {
  display: flex;
  padding: 9px;
  margin: 7px 3px 20px;
  border: 1px solid #ffffffd9;
  border-radius: 12px;
  background: #f4faffc9;
  box-shadow: 0 3px 10px #124c8b1f;
}

.daily-start {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 48px;
  padding: 8px;
  border: 1px solid #2e91ff;
  border-radius: 8px;
  background: linear-gradient(#3b99ff, #0874f5 60%, #0064df);
  box-shadow: inset 0 1px 1px #ffffff80, 0 2px 5px #076bd33b;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
  text-decoration: none;
  white-space: nowrap;
  transition: filter .15s, box-shadow .15s;
}

.daily-start:hover { filter: brightness(1.07); box-shadow: inset 0 1px 1px #ffffff80, 0 3px 9px #076bd35c; }
.daily-start:disabled { cursor: wait; opacity: .75; }
.daily-play { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; background: #ffffff38; }
.daily-play :deep(svg) { width: 21px; height: 21px; fill: currentColor; stroke-width: 0; }

.daily-links {
  display: grid;
  margin: 0 -3px;
  border: 1px solid #d1e7fa;
  border-radius: 2px;
  overflow: hidden;
  background: #f0f8ffe6;
}

.daily-links a { display: flex; align-items: center; gap: 12px; min-width: 0; min-height: 36px; padding: 5px 11px; border-radius: 0; color: #064aa7; font-size: 13px; line-height: 1.4; }
.daily-links a + a { border-top: 1px solid #a8bdce80; }
.daily-links a:hover { background: #ffffffc9; color: #003a8c; }
.daily-links span { flex: 1; text-decoration: underline; text-underline-offset: 2px; }
.daily-links a > :last-child { width: 17px; height: 17px; }
.daily-notice { padding: 9px 11px; margin: 7px 0; border-radius: 8px; background: #f6fbfff0; font-size: 12px; line-height: 1.55; }
.daily-retry { width: 100%; min-height: 44px; margin: 6px 0; white-space: normal; }
:focus-visible { outline: 3px solid #064aa7; outline-offset: 3px; }
.daily-links a:focus-visible { outline-offset: -3px; }

@media (max-width: 1023px) {
  .daily-session-card { padding: 8px; background-position: center 46%; }
  h2 { padding: 4px 8px; font-size: 17px; }
  .daily-summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; margin: 4px 0 0; }
  .daily-detail { gap: 8px; padding: 3px 8px; }
  .daily-detail p { font-size: 12px; }
  .daily-detail :deep(svg) { width: 18px; height: 18px; }
  .daily-level { grid-column: 1; grid-row: 1; }
  .daily-level p { display: flex; flex-wrap: wrap; gap: 0 5px; }
  .daily-label { margin: 0; }
  .daily-theme { grid-column: 1 / -1; grid-row: 2; }
  .daily-theme p { display: flex; flex-wrap: wrap; gap: 0 5px; }
  .daily-title { grid-column: 1 / -1; grid-row: 3; }
  .daily-duration { grid-column: 2; grid-row: 1; }
  .daily-detail.daily-duration { border-top: 0; }
  .daily-action { padding: 3px; margin: 4px 0 6px; border-radius: 9px; }
  .daily-start { min-height: 44px; padding: 6px 10px; font-size: 15px; }
  .daily-play { width: 26px; height: 26px; }
  .daily-links { grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
  .daily-links a { gap: 7px; min-height: 44px; padding: 5px 8px; font-size: 12px; }
  .daily-links a + a { border-top: 0; }
  .daily-links a:nth-child(even) { border-left: 1px solid #a8bdce80; }
  .daily-links a:nth-child(n + 3) { border-top: 1px solid #a8bdce80; }
  .daily-links a > :first-child { width: 19px; height: 19px; }
  .daily-links a > :last-child { width: 13px; height: 13px; }
}

@media (max-width: 359px) {
  .daily-level .daily-label { font-size: 11px; }
  .daily-detail { gap: 6px; padding-right: 6px; padding-left: 6px; }
  .daily-links a { gap: 5px; padding-right: 6px; padding-left: 6px; }
}
</style>
