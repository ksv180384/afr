<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import GameLayout from '@/App/Layouts/GameLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';

defineProps({ authUser: { type: Object, default: null } });

const gameHost = ref(null);
const state = ref('ready');
const error = ref('');
const paused = ref(false);
const hints = ref(2);
const responseProgress = ref(100);
const stats = ref(emptyStats());
const sessionId = ref(null);
const currentQuestion = ref(null);
const timeBoosts = ref(3);
const powerUpActive = ref(false);
const hintTranslation = ref('');
const timeBoostVisible = ref(false);

let game = null;
let progressTimer = null;
let currentDuration = 8000;
let destroyed = false;
let powerTimeout = null;

function emptyStats() {
  return { score: 0, correct_count: 0, wrong_count: 0, missed_count: 0, streak: 0, best_streak: 0, level: 1 };
}

const completed = computed(() => stats.value.correct_count + stats.value.wrong_count + stats.value.missed_count);
const accuracy = computed(() => completed.value ? Math.round(stats.value.correct_count / completed.value * 100) : 0);
const wordNumber = computed(() => Math.min(40, completed.value + (['playing', 'answering'].includes(state.value) ? 1 : 0)));
const combo = computed(() => Math.max(1, stats.value.streak));
const speed = computed(() => (0.25 + Math.floor(stats.value.level / 2) * 0.15).toFixed(2).replace(/0+$/, '').replace(/\.$/, ''));
const levelMultiplier = computed(() => 1 + (stats.value.level - 1) * 0.12);
const speedBonus = computed(() => Math.round(150 * responseProgress.value / 100 * levelMultiplier.value));
const comboBonus = computed(() => Math.min(100, stats.value.streak * 10));
const speedGaugeProgress = computed(() => Math.min(92, 16 + Math.floor(stats.value.level / 2) * 15.2));
const url = (path = '') => `/api/v1/games/falling-translations/sessions${path}`;

async function createEngine() {
  if (game || !gameHost.value) return;
  const { mountFallingTranslations } = await import('@/App/Games/FallingTranslations/bootstrap.js');
  if (!destroyed) game = mountFallingTranslations(gameHost.value, { onAnswer: answer });
}

function startProgress(duration, initialProgress = 100) {
  stopProgress();
  currentDuration = duration;
  responseProgress.value = initialProgress;
  const startedAt = performance.now();

  progressTimer = window.setInterval(() => {
    const elapsedPercent = ((performance.now() - startedAt) / duration) * 100;
    responseProgress.value = Math.max(0, initialProgress - elapsedPercent);
  }, 50);
}

function stopProgress() {
  window.clearInterval(progressTimer);
  progressTimer = null;
}

function resumeProgress() {
  startProgress(currentDuration, responseProgress.value);
}

async function start() {
  error.value = '';
  state.value = 'loading';
  hints.value = 2;
  timeBoosts.value = 3;
  powerUpActive.value = false;
  hintTranslation.value = '';
  timeBoostVisible.value = false;

  try {
    await createEngine();
    const { data } = await axios.post(url());
    sessionId.value = data.session_id;
    stats.value = data.stats;
    state.value = 'playing';
    await nextTick();
    showQuestion(data.question);
  } catch (requestError) {
    state.value = 'ready';
    error.value = requestError.response?.data?.message || 'Не удалось начать игру. Попробуйте ещё раз.';
  }
}

function showQuestion(question) {
  currentQuestion.value = question;
  currentDuration = question.duration_ms;
  startProgress(currentDuration);
  game?.setQuestion(question);
}

async function answer(optionId, wrongAttempts = 0) {
  if (state.value !== 'playing' || !sessionId.value) return;
  state.value = 'answering';
  stopProgress();

  try {
    const responseMs = Math.round(currentDuration * (1 - responseProgress.value / 100));
    const { data } = await axios.post(url(`/${sessionId.value}/answer`), {
      option_id: optionId,
      response_ms: responseMs,
      wrong_attempts: wrongAttempts,
    });
    stats.value = data.stats;
    game?.showResult(data);

    window.setTimeout(async () => {
      if (destroyed) return;
      if (completed.value >= 40) return finish();

      try {
        const next = await axios.post(url(`/${sessionId.value}/next`));
        stats.value = next.data.stats;
        state.value = 'playing';
        showQuestion(next.data.question);
      } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Не удалось загрузить следующее слово.';
        state.value = 'ready';
      }
    }, data.correct ? 1300 : 1180);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Не удалось сохранить ответ.';
    state.value = 'ready';
  }
}

function togglePause() {
  if (state.value !== 'playing' || powerUpActive.value) return;
  paused.value = !paused.value;
  game?.pause(paused.value);
  paused.value ? stopProgress() : resumeProgress();
}

function freezeGame(duration, { translation = '', timeBoost = false } = {}) {
  powerUpActive.value = true;
  hintTranslation.value = translation;
  timeBoostVisible.value = timeBoost;
  stopProgress();
  game?.pause(true);
  window.clearTimeout(powerTimeout);
  powerTimeout = window.setTimeout(() => {
    powerUpActive.value = false;
    hintTranslation.value = '';
    timeBoostVisible.value = false;
    if (!destroyed && state.value === 'playing' && !paused.value) {
      game?.pause(false);
      resumeProgress();
    }
  }, duration);
}

function useHint() {
  if (state.value !== 'playing' || paused.value || powerUpActive.value || hints.value < 1) return;
  const correct = currentQuestion.value?.options?.find(option => option.id === currentQuestion.value.word.id);
  if (!correct) return;
  hints.value--;
  freezeGame(3000, { translation: correct.text });
}

function addTime() {
  if (state.value !== 'playing' || paused.value || powerUpActive.value || timeBoosts.value < 1) return;
  timeBoosts.value--;
  freezeGame(5000, { timeBoost: true });
}

async function finish() {
  stopProgress();
  if (!sessionId.value) return;
  try {
    const { data } = await axios.post(url(`/${sessionId.value}/finish`));
    stats.value = data.stats;
  } finally {
    state.value = 'finished';
    paused.value = false;
    game?.pause(true);
  }
}

function restart() {
  stopProgress();
  game?.destroy();
  game = null;
  sessionId.value = null;
  stats.value = emptyStats();
  responseProgress.value = 100;
  currentQuestion.value = null;
  powerUpActive.value = false;
  hintTranslation.value = '';
  timeBoostVisible.value = false;
  window.clearTimeout(powerTimeout);
  state.value = 'ready';
  nextTick(start);
}

function onVisibilityChange() {
  if (document.hidden && state.value === 'playing' && !paused.value) togglePause();
}

onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange));
onBeforeUnmount(() => {
  destroyed = true;
  stopProgress();
  window.clearTimeout(powerTimeout);
  document.removeEventListener('visibilitychange', onVisibilityChange);
  game?.destroy();
  if (sessionId.value && state.value !== 'finished') axios.post(url(`/${sessionId.value}/finish`)).catch(() => {});
});
</script>

<template>
  <game-layout>
    <seo-head title="Падающие переводы — игра" description="Выбирайте правильные переводы французских слов, пока карточки падают." no-index />

    <section class="game-page">
      <div ref="gameHost" class="game-host" aria-label="Игровое поле с падающими вариантами перевода" />

      <header v-if="['playing', 'answering'].includes(state)" class="hud" aria-live="polite">
        <button class="pause-button" :disabled="powerUpActive" type="button" aria-label="Поставить игру на паузу" @click="togglePause">
          <span /><span />
        </button>

        <div class="hud-stat score-stat">
          <span>СЧЁТ</span>
          <strong>{{ stats.score.toLocaleString('ru-RU') }}</strong>
        </div>

        <div class="combo-stat" :class="{ hot: stats.streak >= 3 }">
          <span>КОМБО</span>
          <strong>x{{ combo }}</strong>
          <small>{{ stats.streak > 0 ? `БОНУС +${comboBonus}` : 'ДЕРЖИ ТЕМП!' }}</small>
        </div>

        <div class="hud-stat word-stat">
          <span>СЛОВО</span>
          <strong>{{ wordNumber }} / 40</strong>
        </div>

        <div class="speed-stat">
          <span>СКОРОСТЬ</span>
          <strong>{{ speed }}x</strong>
          <svg class="speed-gauge" viewBox="0 0 72 62" aria-hidden="true">
            <path class="gauge-track" pathLength="100" d="M 9 53 A 27 27 0 1 1 63 53" />
            <path class="gauge-fill" pathLength="100" d="M 9 53 A 27 27 0 1 1 63 53" :style="{ strokeDasharray: `${speedGaugeProgress} 100` }" />
            <circle cx="9" cy="53" r="3.5" />
          </svg>
        </div>
      </header>

      <Transition name="reveal">
        <div v-if="hintTranslation || timeBoostVisible" class="power-message" role="status">
          <small>{{ timeBoostVisible ? 'ВРЕМЯ ОСТАНОВЛЕНО' : 'ПРАВИЛЬНЫЙ ПЕРЕВОД' }}</small>
          <strong>{{ timeBoostVisible ? '+5 секунд' : hintTranslation }}</strong>
        </div>
      </Transition>

      <footer v-if="['playing', 'answering'].includes(state)" class="game-footer">
        <button class="power-button time-button" :disabled="timeBoosts < 1 || state !== 'playing' || paused || powerUpActive" type="button" title="Остановить падение на 5 секунд" @click="addTime">
          <span class="round-icon">
            <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="25" r="15"/><path d="M24 15v11l8 5M18 7h12M24 7v4"/></svg>
            <b>{{ timeBoosts }}</b>
          </span>
          <span>+5 СЕКУНД</span>
        </button>

        <div class="bonus-block">
          <span>БОНУС ЗА СКОРОСТЬ</span>
          <div class="bonus-row">
            <div class="bonus-track"><i :style="{ width: `${responseProgress}%` }" /></div>
            <strong>+{{ speedBonus.toLocaleString('ru-RU') }}</strong>
          </div>
        </div>

        <button class="power-button hint-button" :disabled="hints < 1 || state !== 'playing' || paused || powerUpActive" type="button" title="Показать правильный перевод" @click="useHint">
          <span class="round-icon">
            <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M16 29c-2.4-2-4-5.1-4-8.3C12 14.8 17.2 10 24 10s12 4.8 12 10.7c0 3.2-1.6 6.3-4 8.3-2.1 1.8-3 3.2-3.2 5H19.2c-.2-1.8-1.1-3.2-3.2-5Z"/><path d="M19 38h10M21 42h6"/></svg>
            <b>{{ hints }}</b>
          </span>
          <span>ПОДСКАЗКА</span>
        </button>
      </footer>

      <div v-if="state === 'ready' || state === 'loading'" class="overlay intro-overlay">
        <div class="panel intro-panel">
          <p class="eyebrow">ФРАНЦУЗСКИЙ В ДВИЖЕНИИ</p>
          <h1>Падающие<br>переводы</h1>
          <p>Выбирайте верный перевод. Каждые 4 слова растёт уровень: поочерёдно увеличиваются скорость и количество вариантов.</p>
          <button class="primary-button" type="button" :disabled="state === 'loading'" @click="start">
            {{ state === 'loading' ? 'Загрузка…' : 'Начать игру' }}
          </button>
          <Link class="games-link" :href="route('games')">Все игры</Link>
          <p v-if="error" class="error">{{ error }}</p>
        </div>
      </div>

      <div v-if="paused" class="overlay pause-overlay">
        <div class="panel pause-panel">
          <p class="eyebrow">ИГРА ОСТАНОВЛЕНА</p>
          <h2>Пауза</h2>
          <button class="primary-button" type="button" @click="togglePause">Продолжить</button>
          <Link class="games-link" :href="route('games')">Выйти к играм</Link>
        </div>
      </div>

      <div v-if="state === 'finished'" class="overlay result-overlay">
        <div class="panel result-panel">
          <p class="eyebrow">СЕССИЯ ЗАВЕРШЕНА</p>
          <h2>{{ stats.score.toLocaleString('ru-RU') }} очков</h2>
          <div class="result-grid">
            <div><b>{{ accuracy }}%</b><span>точность</span></div>
            <div><b>{{ stats.best_streak }}</b><span>лучшая серия</span></div>
            <div><b>{{ stats.correct_count }}</b><span>верных слов</span></div>
          </div>
          <button class="primary-button" type="button" @click="restart">Играть ещё раз</button>
          <Link class="games-link" :href="route('games')">Все игры</Link>
        </div>
      </div>
    </section>
  </game-layout>
</template>

<style scoped>
.game-page { position: relative; min-height: 100svh; overflow: hidden; background: #79d7ec; }
.game-host { position: absolute; inset: 0; }
.hud { pointer-events: none; position: absolute; z-index: 20; top: clamp(16px, 4vh, 44px); left: 50%; width: min(1180px, 92vw); transform: translateX(-50%); display: grid; grid-template-columns: 78px 1.05fr 1fr 1fr 1fr; align-items: start; gap: clamp(12px, 2vw, 38px); color: #fff; filter: drop-shadow(0 3px 4px #3a799755); }
.pause-button { pointer-events: auto; width: 72px; height: 72px; border: 2px solid #ffffffb3; border-radius: 25px; background: #ffffff30; box-shadow: inset 0 1px #fff, 0 12px 28px #34738a45; backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; gap: 9px; transition: transform .2s ease, background .2s ease; }
.pause-button:hover { transform: translateY(-2px) scale(1.03); background: #ffffff4a; }
.pause-button span { width: 8px; height: 31px; border-radius: 7px; background: #fff; box-shadow: 0 2px 4px #3d7485; }
.hud-stat, .combo-stat, .speed-stat { min-width: 0; text-align: center; }
.hud-stat > span, .combo-stat > span, .speed-stat > span { display: block; margin-bottom: 3px; font-size: clamp(10px, 1.1vw, 14px); font-weight: 900; letter-spacing: .08em; text-shadow: 0 2px 3px #326e86; }
.hud-stat strong { display: block; white-space: nowrap; font-size: clamp(28px, 4vw, 47px); line-height: 1; text-shadow: 0 3px 4px #397991; }
.combo-stat { color: #9150e8; transform: translateY(-4px); }
.combo-stat strong { display: block; font-size: clamp(40px, 5vw, 61px); line-height: .95; text-shadow: 0 4px 0 #ffffff6b, 0 8px 14px #6246a54a; animation: comboFloat 2.1s ease-in-out infinite; }
.combo-stat small { display: block; margin-top: 8px; font-size: clamp(9px, 1vw, 13px); font-weight: 900; }
.combo-stat.hot strong { color: #c640ea; animation: comboHot .55s ease-in-out infinite alternate; }
.speed-stat strong { display: block; color: #6ee8a6; font-size: clamp(28px, 4vw, 45px); line-height: 1; text-shadow: 0 3px 4px #347e83; }
.speed-gauge { display: block; width: clamp(58px, 6vw, 78px); height: clamp(45px, 5vw, 62px); margin: -2px auto 0; overflow: visible; fill: #fff; filter: drop-shadow(0 0 7px #fff9); }
.speed-gauge path { fill: none; stroke-linecap: round; stroke-width: 7; }
.gauge-track { stroke: #ffffff70; }
.gauge-fill { stroke: #b9ffd0; stroke-dashoffset: 0; transition: stroke-dasharray .5s ease; }
.game-footer { pointer-events: none; position: absolute; z-index: 20; left: 50%; bottom: clamp(42px, 6vh, 72px); width: min(1040px, 92vw); transform: translateX(-50%); display: grid; grid-template-columns: 92px minmax(220px, 1fr) 92px; align-items: end; gap: clamp(16px, 3vw, 42px); color: #fff; filter: drop-shadow(0 3px 4px #397b7855); }
.bonus-block > span { font-size: clamp(10px, 1.1vw, 14px); font-weight: 900; letter-spacing: .07em; }
.bonus-row { display: flex; align-items: center; gap: 16px; margin-top: 8px; }
.bonus-track { flex: 1; height: 20px; padding: 3px; border: 2px solid #efffc9d9; border-radius: 30px; background: #24675970; box-shadow: inset 0 2px 5px #16463e88, 0 0 11px #fff5; overflow: hidden; }
.bonus-track i { display: block; height: 100%; border-radius: 30px; background: linear-gradient(90deg, #55ee79, #baffc9); box-shadow: 0 0 14px #7dff9b; transition: width .08s linear; }
.bonus-row strong { min-width: 92px; color: #b7ffc0; font-size: clamp(21px, 3vw, 32px); white-space: nowrap; }
.power-button { pointer-events: auto; position: relative; border: 0; background: transparent; color: #fff; display: flex; flex-direction: column; align-items: center; gap: 7px; cursor: pointer; transition: transform .2s, opacity .2s; }
.power-button:hover:not(:disabled) { transform: translateY(-4px) scale(1.04); }
.power-button:disabled, .pause-button:disabled { opacity: .38; cursor: not-allowed; }
.power-button > span:last-child { font-size: 11px; font-weight: 900; white-space: nowrap; }
.round-icon { position: relative; display: grid; place-items: center; width: 72px; height: 72px; border: 3px solid #fff; border-radius: 50%; background: #427b796b; box-shadow: inset 0 2px #fff8, 0 8px 20px #376d7059; }
.round-icon svg { width: 42px; height: 42px; fill: none; stroke: #fff; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.round-icon b { position: absolute; right: -6px; top: -8px; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #55b9f1, #7050e7); box-shadow: 0 3px 8px #315a7590; font-size: 15px; }
.time-button .round-icon { background: #517d5f70; }
.power-message { position: absolute; z-index: 24; left: 50%; top: 68%; transform: translate(-50%, -50%); min-width: 210px; padding: 14px 26px; border: 2px solid #fff; border-radius: 999px; background: #ffffffdf; box-shadow: 0 10px 36px #3a788360, 0 0 26px #fff9; color: #28566b; text-align: center; pointer-events: none; }
.power-message small, .power-message strong { display: block; }
.power-message small { color: #6692a2; font-size: 9px; font-weight: 900; letter-spacing: .12em; }
.power-message strong { margin-top: 3px; font-size: clamp(22px, 4vw, 34px); }
.reveal-enter-active, .reveal-leave-active { transition: opacity .2s, transform .25s; }
.reveal-enter-from, .reveal-leave-to { opacity: 0; transform: translate(-50%, -44%) scale(.85); }
.overlay { position: absolute; z-index: 40; inset: 0; display: grid; place-items: center; padding: 20px; background: #1f60773b; backdrop-filter: blur(8px); }
.panel { width: min(540px, 94vw); padding: clamp(29px, 5vw, 48px); border: 1px solid #ffffffc4; border-radius: 34px; background: #ffffffd9; box-shadow: 0 28px 80px #39768b66, inset 0 1px #fff; text-align: center; color: #214c61; animation: panelIn .38s cubic-bezier(.34,1.56,.64,1); }
.eyebrow { margin: 0 0 10px; color: #4b9b82; font-size: 11px; font-weight: 900; letter-spacing: .2em; }
.panel h1 { margin: 0; font-size: clamp(42px, 6vw, 65px); line-height: .95; color: #24546b; }
.panel h2 { margin: 0; font-size: clamp(36px, 5vw, 54px); color: #24546b; }
.panel > p:not(.eyebrow, .error) { max-width: 410px; margin: 20px auto 0; color: #50788b; line-height: 1.55; font-weight: 600; }
.primary-button { margin-top: 27px; padding: 14px 30px; border: 1px solid #fff; border-radius: 999px; background: linear-gradient(135deg, #dfff91, #aaf48b); box-shadow: 0 9px 24px #72bf7070, inset 0 1px #fff; color: #25554c; font-size: 16px; font-weight: 900; transition: transform .2s, box-shadow .2s; }
.primary-button:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 13px 29px #72bf7082, inset 0 1px #fff; }
.primary-button:disabled { opacity: .6; cursor: wait; }
.games-link { display: block; width: fit-content; margin: 18px auto 0; color: #4b7285; font-size: 13px; font-weight: 800; text-decoration: underline; text-underline-offset: 3px; }
.error { margin: 15px 0 0; color: #bd4663; font-weight: 700; }
.result-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 28px 0 4px; }
.result-grid div { display: flex; flex-direction: column; gap: 4px; padding: 0 7px; border-right: 1px solid #88b4b8; }
.result-grid div:last-child { border-right: 0; }
.result-grid b { color: #3d9b70; font-size: 27px; }
.result-grid span { color: #63808d; font-size: 11px; }
@keyframes comboFloat { 50% { transform: translateY(-4px) scale(1.025); } }
@keyframes comboHot { to { transform: scale(1.08) rotate(1deg); filter: drop-shadow(0 0 8px #fff); } }
@keyframes panelIn { from { opacity: 0; transform: translateY(18px) scale(.94); } }

@media (max-width: 680px) {
  .hud { top: 10px; width: 97vw; grid-template-columns: 42px repeat(4, minmax(0, 1fr)); gap: 2px; align-items: start; }
  .pause-button { width: 40px; height: 40px; border-radius: 14px; }
  .pause-button span { width: 5px; height: 19px; }
  .hud-stat > span, .combo-stat > span, .speed-stat > span { font-size: 7px; letter-spacing: .02em; }
  .hud-stat strong, .speed-stat strong { font-size: clamp(16px, 5vw, 21px); }
  .combo-stat { transform: none; }
  .combo-stat strong { font-size: clamp(23px, 7vw, 30px); }
  .combo-stat small { display: none; }
  .speed-gauge { width: 38px; height: 31px; margin-top: -4px; }
  .speed-gauge path { stroke-width: 8; }
  .game-footer { bottom: 28px; width: 94vw; grid-template-columns: 54px minmax(0, 1fr) 54px; gap: 8px; }
  .bonus-block > span { font-size: 9px; }
  .bonus-row { gap: 7px; margin-top: 4px; }
  .bonus-track { height: 14px; padding: 2px; }
  .bonus-row strong { min-width: 48px; font-size: 14px; }
  .round-icon { width: 50px; height: 50px; border-width: 2px; }
  .round-icon svg { width: 31px; height: 31px; }
  .round-icon b { width: 22px; height: 22px; right: -5px; top: -6px; font-size: 12px; }
  .power-button > span:last-child { display: none; }
  .power-message { top: 71%; min-width: 180px; padding: 10px 19px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
