<script>
let pendingFocus;
</script>
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import { restorePracticeScroll } from '@/App/Learning/practiceRunNavigation.js';
import { practiceRouteTransport } from '@/App/Services/Api/daily_practice.js';
import FrenchText from './FrenchText.vue';
import PronunciationNotes from './PronunciationNotes.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import PracticeStatus from './PracticeStatus.vue';
import PracticeLevelPath from './PracticeLevelPath.vue';

const props = defineProps({route: {type: Object, required: true}, selection: Object, owner: Number, guest: Boolean, store: Object, busy: Boolean, trail: Boolean});
const emit = defineEmits(['start', 'continue']);
const canOpen = item => ['completed', 'available', 'active'].includes(item?.status);
const levelTitle = level => level === 'PRE_A1' ? 'С нуля' : level;
const completedCount = computed(() => props.route.lessons.filter(lesson => lesson.completed).length);
const selected = computed(() => !!(props.selection?.level || props.selection?.unit));
const topicUrl = (level, unit) => '/practice?' + new URLSearchParams(unit ? {level, unit} : {level});
const openLesson = lesson => {
  if (lesson.run_id) emit('continue', lesson.run_id);
  else emit('start', lesson.id);
};
const lessonAction = lesson => {
  if (lesson.run_id) return 'Продолжить';
  return lesson.status === 'completed' ? 'Повторить' : 'Начать';
};

const dialog = ref(null);
const closeButton = ref(null);
const fragment = ref('');
let trigger;
let openedHere = false;
const panel = computed(() => {
  if (fragment.value === '#topics' || fragment.value === '#notes') return fragment.value.slice(1);
  if (fragment.value.startsWith('#lesson=')) return 'lesson';
  return '';
});
const referenceCard = computed(() => {
  if (panel.value === 'notes') return props.route.unit;
  if (panel.value === 'lesson') return props.route.lessons.find(lesson => '#lesson=' + encodeURIComponent(lesson.id) === fragment.value);
  return null;
});
const heading = computed(() => ({topics: 'Уровни и темы', notes: 'Памятка', lesson: 'Подробнее'})[panel.value]);
const pagePath = () => window.location.pathname + window.location.search;
const openPanel = (value, event) => {
  trigger = event.currentTarget;
  pendingFocus = {path: pagePath(), id: trigger.id};
  openedHere = true;
  window.location.hash = value;
  syncFragment();
};
const syncFragment = () => { fragment.value = window.location.hash; };
const closePanel = () => {
  if (openedHere) {
    openedHere = false;
    window.history.back();
  } else {
    const state = window.history.state;
    window.history.replaceState(state?.url ? {...state, url: pagePath()} : state, '', pagePath());
    syncFragment();
  }
};
watch(panel, async value => {
  if (value && !dialog.value?.open) {
    await nextTick();
    if (panel.value !== value || !dialog.value) return;
    dialog.value.showModal();
    closeButton.value?.focus();
  } else if (!value && dialog.value?.open) {
    openedHere = false;
    dialog.value.close();
    const focus = pendingFocus;
    if (trigger?.isConnected) trigger.focus({preventScroll: true});
    else document.getElementById(focus?.id || 'practice-topics-trigger')?.focus({preventScroll: true});
    setTimeout(() => { if (pendingFocus === focus) pendingFocus = undefined; }, 2000);
  }
});
onMounted(() => {
  syncFragment();
  if (!props.trail) restorePracticeScroll();
  if (!panel.value && pendingFocus?.path === pagePath()) {
    const {id} = pendingFocus;
    pendingFocus = undefined;
    nextTick(() => document.getElementById(id)?.focus({preventScroll: true}));
  }
  window.addEventListener('hashchange', syncFragment);
});
onBeforeUnmount(() => window.removeEventListener('hashchange', syncFragment));

const reference = ref(null);
const referenceBusy = ref(false);
const referenceError = ref('');
const loadReference = async (card, onCleanup) => {
  reference.value = null;
  referenceError.value = '';
  referenceBusy.value = false;
  if (!panel.value || panel.value === 'topics') return;
  if (!canOpen(card) || !card.path) {
    if (panel.value === 'lesson') referenceError.value = card?.reason || 'Справка для этого материала недоступна.';
    return;
  }
  let current = true;
  onCleanup(() => { current = false; });
  referenceBusy.value = true;
  try {
    const response = await practiceRouteTransport.reference(card.path);
    if (current) reference.value = response;
  } catch {
    if (current) referenceError.value = 'Не удалось загрузить справку.';
  } finally {
    if (current) referenceBusy.value = false;
  }
};
const referenceAttempt = ref(0);
watch([referenceCard, panel, referenceAttempt, () => props.owner], ([card], previous, onCleanup) => loadReference(card, onCleanup));

const query = ref('');
const completedOnly = ref(false);
const results = ref(null);
const searching = ref(false);
const searchError = ref('');
let searchId = 0;
const resetSearch = () => {
  searchId++;
  results.value = null;
  searching.value = false;
  searchError.value = '';
};
watch([query, completedOnly, panel, () => props.owner, () => props.route], resetSearch);
onBeforeUnmount(resetSearch);
const normalizedQuery = computed(() => query.value.trim().toLocaleLowerCase('ru'));
const matchingLevels = computed(() => props.route.contents.map(level => ({
  ...level,
  units: level.units.filter(unit => (!completedOnly.value || unit.status === 'completed') &&
    (level.title?.toLocaleLowerCase('ru').includes(normalizedQuery.value) || unit.title?.toLocaleLowerCase('ru').includes(normalizedQuery.value))),
})).filter(level => level.units.length || (!completedOnly.value && level.title?.toLocaleLowerCase('ru').includes(normalizedQuery.value))));
const search = async () => {
  resetSearch();
  const id = searchId;
  const owner = props.owner;
  searching.value = true;
  try {
    const response = props.guest
      ? props.store.searchGuest(query.value, completedOnly.value)
      : await practiceRouteTransport.search(query.value.trim(), completedOnly.value);
    if (id !== searchId) return;
    if (!props.guest && response.owner_user_id !== owner) throw new Error('Аккаунт изменился.');
    results.value = response;
  } catch {
    if (id === searchId) searchError.value = 'Не удалось выполнить поиск. Повторите запрос.';
  } finally {
    if (id === searchId) searching.value = false;
  }
};
</script>

<template>
  <section class="learn-route" :class="{ 'learn-route--trail': trail }" :aria-labelledby="trail ? undefined : 'practice-route-title'">
    <header class="route-header" :class="{ 'trail-toolbar': trail }">
      <div v-if="!trail">
        <p>{{ route.level.title || levelTitle(route.level.id) }}</p>
        <h2 id="practice-route-title">{{ route.unit?.title || 'Учебный маршрут' }}</h2>
        <p v-if="route.lessons.length">Пройдено {{ completedCount }} из {{ route.lessons.length }} занятий</p>
      </div>
      <div class="route-actions">
        <button id="practice-topics-trigger" type="button" class="topics-trigger" @click="openPanel('topics', $event)">
          <daily-practice-icon v-if="trail" name="list" />
          Уровни и темы
        </button>
        <Link v-if="selected && !trail" href="/practice">К текущему занятию</Link>
      </div>
    </header>

    <slot v-if="!trail" />

    <practice-level-path v-if="trail" :route="route" :selection="selection" :busy="busy" @open="openLesson" />

    <section v-if="!trail" class="route-lessons" aria-labelledby="practice-lessons-title">
      <header class="route-header">
        <h3 id="practice-lessons-title">Занятия темы</h3>
        <button id="practice-notes-trigger" type="button" @click="openPanel('notes', $event)">Памятка</button>
      </header>
      <p v-if="route.unit?.status === 'locked'" role="status">{{ route.unit.reason }}</p>
      <p v-if="!route.lessons.length" role="status">{{ route.unit?.reason || route.level.reason || 'Занятия ещё не опубликованы.' }}</p>
      <ol v-else>
        <li v-for="lesson in route.lessons" :key="lesson.id" class="route-lesson" :class="'status-' + lesson.status">
          <div class="lesson-text">
            <strong>{{ lesson.title }}</strong>
            <practice-status :status="lesson.status" :icon-only="trail" />
            <p v-if="lesson.reason">{{ lesson.reason }}</p>
          </div>
          <div v-if="canOpen(lesson)" class="route-actions">
            <button type="button" :disabled="busy" @click="openLesson(lesson)">{{ lessonAction(lesson) }}<span class="sr-only"> {{ lesson.title }}</span></button>
            <button v-if="lesson.path" :id="'practice-details-' + lesson.id" type="button" @click="openPanel('lesson=' + encodeURIComponent(lesson.id), $event)">Подробнее<span class="sr-only"> {{ lesson.title }}</span></button>
          </div>
        </li>
      </ol>
    </section>

    <dialog ref="dialog" class="route-dialog" aria-labelledby="practice-panel-title" @cancel.prevent="closePanel">
      <div class="dialog-toolbar">
        <header class="route-header">
          <h2 id="practice-panel-title">{{ heading }}</h2>
          <button ref="closeButton" class="dialog-icon" type="button" aria-label="Закрыть" title="Закрыть" @click="closePanel">
            <daily-practice-icon name="close" />
          </button>
        </header>
        <form v-if="panel === 'topics'" class="route-search" @submit.prevent="search">
          <div class="search-controls">
            <input
              v-model="query"
              type="search"
              maxlength="100"
              aria-label="Название темы или занятия"
              placeholder="Название темы или занятия"
            />
            <button class="dialog-icon search-icon" type="submit" :disabled="searching" aria-label="Найти занятия" title="Найти занятия">
              <daily-practice-icon name="search" />
            </button>
          </div>
          <label class="route-checkbox"><input v-model="completedOnly" type="checkbox" /> Только пройденное</label>
        </form>
      </div>
      <template v-if="panel === 'topics'">
        <p v-if="searching" role="status">Ищем занятия…</p>
        <p v-if="searchError" role="alert">{{ searchError }}</p>
        <section v-if="results">
          <h3>Найденные занятия</h3>
          <p v-if="!results.items.length">Занятия не найдены.</p>
          <ul v-else class="route-choice-list">
            <li v-for="lesson in results.items" :key="lesson.id">
              <div class="choice-row">
                <button v-if="canOpen(lesson)" type="button" :disabled="busy" @click="openLesson(lesson)">{{ lesson.title }}</button>
                <strong v-else>{{ lesson.title }}</strong>
                <practice-status :status="lesson.status" icon-only />
              </div>
              <span>{{ levelTitle(lesson.level) }} · {{ lesson.unit }}</span>
            </li>
          </ul>
          <p v-if="results.more">Показаны первые 40 занятий. Уточните название для более точного поиска.</p>
        </section>
        <h3>Темы по уровням</h3>
        <p v-if="!matchingLevels.length">Темы не найдены.</p>
        <details v-for="level in matchingLevels" :key="level.id" :open="level.id === route.level.id || !!normalizedQuery || completedOnly">
          <summary>{{ level.title || levelTitle(level.id) }} <practice-status :status="level.status" icon-only /></summary>
          <ul class="route-choice-list">
            <li v-for="unit in level.units" :key="unit.id" class="choice-row">
              <Link :href="topicUrl(level.id, unit.id)" :aria-current="route.unit?.id === unit.id ? 'location' : null">{{ unit.title }}</Link>
              <span class="choice-count">{{ unit.session_count }} занятий</span>
              <practice-status :status="unit.status" icon-only />
            </li>
            <li v-if="!level.units.length"><Link :href="topicUrl(level.id)">Открыть уровень</Link></li>
          </ul>
        </details>
      </template>
      <template v-else-if="panel">
        <section v-if="panel === 'notes'" class="practice-rules" aria-label="Как заниматься">
          <p>Уроки открываются по порядку. Пройденное можно повторить.</p>
          <p>При выходе прогресс сохраняется. Помощь до ответа учитывается при зачёте.</p>
        </section>
        <p v-if="referenceBusy" role="status">Загружаем справку…</p>
        <template v-else-if="referenceError">
          <p role="alert">{{ referenceError }}</p>
          <afr-button v-if="canOpen(referenceCard)" @click="referenceAttempt++">Повторить загрузку</afr-button>
        </template>
        <div v-else-if="reference" class="route-reference">
          <h3>{{ reference.title }}</h3>
          <p v-if="reference.summary">{{ reference.summary }}</p>
          <ul v-if="reference.outcomes.length"><li v-for="outcome in reference.outcomes" :key="outcome">{{ outcome }}</li></ul>
          <pronunciation-notes :notes="reference.notes" />
          <section v-if="reference.explanation">
            <h4>{{ reference.explanation.heading }}</h4>
            <p v-for="(text, index) in reference.explanation.paragraphs" :key="index">{{ text }}</p>
            <french-text v-for="(example, index) in reference.explanation.examples" :key="index"
              :text="example.fr" :transcription-ru="example.transcription_ru" :translation="example.ru" />
          </section>
        </div>
      </template>
    </dialog>
  </section>
</template>

<style scoped>
.route-header.trail-toolbar {
  position: sticky;
  grid-area: 1 / 1;
  align-self: start;
  margin-top: var(--practice-topic-button-offset);
  top: calc(var(--practice-route-top) + var(--practice-topic-button-offset));
  z-index: 3;
  justify-content: flex-end;
  padding-inline: 16px;
  pointer-events: none;
}
.trail-toolbar .route-actions .topics-trigger {
  gap: 8px;
  padding-inline: 12px;
  border-color: #d6e3dc;
  border-radius: 13px;
  background: #fffffff5;
  color: #42645a;
  font-size: .875rem;
  box-shadow: 0 4px 12px #25475b08;
  pointer-events: auto;
}
.trail-toolbar .topics-trigger:hover {
  background: #f5faf6;
  border-color: #adcbb8;
}
.trail-toolbar .topics-trigger:focus-visible {
  outline: 3px solid #165ca7;
  outline-offset: 3px;
}
.learn-route {
  display: grid;
  gap: .85rem;
  min-width: 0;
  isolation: isolate;
}
.learn-route--trail {
  --practice-route-top: 68px;
  --practice-topic-heading-height: 64px;
  --practice-topic-button-offset: calc(var(--practice-topic-heading-height) + 6px);
  --practice-topic-controls-height: calc(var(--practice-topic-button-offset) + 44px);
}
.learn-route--trail > :deep(.level-path) { grid-area: 1 / 1; }
.route-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:.6rem 1rem; }
.route-header h2 { margin:.2rem 0; font-size:1.4rem; font-weight:700; line-height:1.3; }
.route-header p { margin:0; color:var(--practice-muted); }
.route-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem .75rem; }
.route-actions a, .route-actions button, .route-header > button, .route-dialog a { display:inline-flex; align-items:center; min-height:44px; }
.route-actions a, .route-actions button, .route-header > button, .route-dialog a { color:#155e91; text-decoration:underline; }
.route-actions .topics-trigger { padding:.4rem .85rem; border:1px solid #93b8dc; border-radius:var(--practice-control-radius); background:var(--practice-accent); text-decoration:none; font-weight:650; }
.route-actions :deep(.afr-button), .route-dialog :deep(.afr-button) { text-decoration:none; }
.route-lessons { padding:var(--practice-pad); border:1px solid var(--practice-border); border-radius:var(--practice-radius); background:var(--practice-surface); }
.route-lessons h3, .route-dialog h3 { font-size:1.05rem; font-weight:700; margin:.45rem 0; }
.route-lesson { display:grid; grid-template-columns:minmax(0,1fr) minmax(0, auto); align-items:start; gap:.6rem; padding:.65rem 0; border-top:1px solid #e2e8f0; }
.lesson-text { display:grid; gap:.2rem; min-width:0; }
.lesson-text p { color:var(--practice-muted); font-size:.875rem; margin:0; }
.route-dialog { box-sizing:border-box; position:fixed; inset:0 0 0 auto; margin:0; width:min(100%,35rem); max-width:100%; height:100dvh; max-height:100%; overflow:auto; padding:0 1.25rem 1rem; overflow-x:hidden; background:#fff; color:#17364d; border-left:1px solid #cbd5e1; }
.route-dialog::backdrop { background:#10284088; }
.route-dialog[open] { animation: slide-in .25s ease-out; }
@keyframes slide-in {
  from { transform:translateX(100%); }
  to { transform:translateX(0); }
}
.dialog-toolbar {
  position: sticky;
  top: 0;
  z-index: 3;
  padding: .35rem 0 .45rem;
  margin-bottom: .55rem;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
}
.dialog-toolbar .route-header {
  align-items: center;
  flex-wrap: nowrap;
  gap: .5rem;
}
.dialog-toolbar .route-header h2 {
  margin: 0;
  font-size: 1.15rem;
}
.dialog-toolbar .dialog-icon {
  display: grid;
  place-items: center;
  flex: 0 0 44px;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  color: #155e91;
}
.dialog-icon:hover, .search-icon { background: var(--practice-accent); }
.practice-rules { display:grid; gap:.4rem; margin:0 0 1rem; color:var(--practice-muted); }
.route-search {
  display: grid;
  gap: .2rem;
}
.search-controls {
  display: flex;
  align-items: center;
  gap: .4rem;
}
.route-search input[type="search"] {
  flex: 1;
  min-width: 0;
  min-height: 44px;
  padding: .45rem .65rem;
  border: 1px solid #b9c9da;
  border-radius: .4rem;
  background: #f8fafc;
}
.route-checkbox {
  display: flex;
  align-items: center;
  min-height: 28px;
  gap: .45rem;
  font-size: .875rem;
  cursor: pointer;
}
.route-dialog summary { display:flex; align-items:center; min-height:44px; gap:.45rem; cursor:pointer; }
.route-dialog summary { display:list-item; padding:.65rem 0; font-weight:650; }
.route-choice-list li { display:grid; padding:.35rem 0; border-bottom:1px solid #e2e8f0; }
.choice-row, .route-choice-list li.choice-row { display:grid; grid-template-columns:minmax(0, 1fr) auto auto; align-items:center; gap:.5rem; }
.choice-row:not(li) { grid-template-columns:minmax(0, 1fr) auto; }
.choice-count { white-space:nowrap; }
.route-choice-list button:not(.status-button) { text-align:left; color:#155e91; text-decoration:underline; }
.route-choice-list li > span:not(.practice-status) { color:var(--practice-muted); font-size:.875rem; }
.route-dialog summary .practice-status { margin-inline-start:.35rem; }
.route-choice-list [aria-current] { font-weight:700; }
.route-reference { display:grid; gap:.65rem; }
.route-reference ul { list-style:disc; padding-left:1.25rem; }
@media (prefers-reduced-motion: reduce) {
  .route-dialog[open] { animation:none; }
}
@media(max-width:800px) {
  .route-dialog { width:100%; padding-inline:1rem; padding-bottom:calc(1rem + env(safe-area-inset-bottom)); }
  .route-lesson { grid-template-columns:minmax(0,1fr); }
}
</style>
