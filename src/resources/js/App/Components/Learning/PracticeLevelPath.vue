<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { restorePracticeScroll } from '@/App/Learning/practiceRunNavigation.js';

const props = defineProps({ route: Object, selection: Object, busy: Boolean });
const emit = defineEmits(['open']);
const path = ref(null);
const topics = computed(() => props.route.topics ?? []);
const lessons = computed(() => topics.value.flatMap(topic => topic.lessons));
const current = computed(() => lessons.value.find(lesson => lesson.id === props.route.current_lesson_id)
  ?? lessons.value.find(lesson => lesson.status === 'active')
  ?? lessons.value.find(lesson => lesson.status === 'available')
  ?? lessons.value.at(-1));
const states = {
  active: { icon: 'play', label: 'Продолжить занятие' },
  available: { icon: 'play', label: 'Начать занятие' },
  completed: { icon: 'check', label: 'Повторить занятие' },
  locked: { icon: 'lock', label: 'Занятие закрыто' },
  unavailable: { icon: 'dash', label: 'Занятие недоступно' },
};
const state = lesson => states[lesson.status] ?? states.unavailable;
const canOpen = lesson => ['active', 'available', 'completed'].includes(lesson.status);
const stepOffsets = [0, -1, 0, 1];
const stepStyle = (index) => {
  const offset = stepOffsets[index % stepOffsets.length];
  return { '--step-offset': offset };
};
const connectorPath = (index) => {
  const offset = stepOffsets[index % stepOffsets.length];
  const nextOffset = stepOffsets[(index + 1) % stepOffsets.length];
  const end = 100 + (nextOffset - offset) * 100;
  return `M 100 0 C 100 50, ${end} 50, ${end} 100`;
};
const palettes = [
  { surface: '#f3f8f2', accent: '#deedda', ink: '#52775c' },
  { surface: '#f2f6fc', accent: '#dce9f7', ink: '#57799c' },
  { surface: '#fcf7ef', accent: '#f5e6cc', ink: '#967247' },
  { surface: '#f7f3fa', accent: '#eaddf2', ink: '#85639a' },
  { surface: '#eff8f7', accent: '#d6eeea', ink: '#4f827a' },
];
// Add AI illustrations as resources/images/practice/topics/<topic ID>.webp (or .png/.jpg).
const backgrounds = import.meta.glob('/resources/images/practice/topics/*.{webp,png,jpg}', {
  eager: true, query: '?url', import: 'default',
});
const topicBackground = topic => topic.background_url ?? ['webp', 'png', 'jpg']
  .map(extension => backgrounds['/resources/images/practice/topics/' + topic.id + '.' + extension])
  .find(Boolean);
const topicBackgrounds = computed(() => new Map(topics.value.map(topic => [topic.id, topicBackground(topic)])));
const topicStyle = index => {
  const palette = palettes[index % palettes.length];
  return {
    '--topic-color': palette.surface,
    '--topic-accent': palette.accent,
    '--topic-ink': palette.ink,
  };
};
let mounted = false;
const scrollToLesson = async () => {
  await nextTick();
  if (!mounted || window.location.hash) return;
  if (restorePracticeScroll()) return;
  const selectedTopic = props.selection?.unit;
  const target = selectedTopic
    ? [...path.value.querySelectorAll('[data-topic]')].find(element => element.dataset.topic === selectedTopic)
    : [...path.value.querySelectorAll('[data-lesson]')].find(element => element.dataset.lesson === current.value?.id);
  target?.scrollIntoView({ block: selectedTopic ? 'start' : 'center', behavior: 'instant' });
};
onMounted(() => {
  mounted = true;
  scrollToLesson();
});
watch(() => [props.route.level.id, props.selection?.unit], scrollToLesson);
</script>

<template>
  <div ref="path" class="level-path" :aria-label="route.level.title || 'Занятия уровня'">
    <section
      v-for="(topic, topicIndex) in topics"
      :key="topic.id"
      :data-topic="topic.id"
      :aria-labelledby="'topic-' + topic.id"
      class="topic"
      :style="topicStyle(topicIndex)"
    >
      <img v-if="topicBackgrounds.get(topic.id)" class="topic-background" :src="topicBackgrounds.get(topic.id)"
        alt="" loading="lazy" decoding="async" />
      <header class="topic-divider">
        <h2 :id="'topic-' + topic.id" :title="topic.title">
          <span class="topic-number" aria-hidden="true">{{ String(topicIndex + 1).padStart(2, '0') }}</span>
          <span class="topic-name">{{ topic.title }}</span>
        </h2>
      </header>
      <ol class="lesson-path">
        <li
          v-for="(lesson, index) in topic.lessons"
          :key="lesson.id"
          :data-lesson="lesson.id"
          class="lesson-step"
          :class="['status-' + lesson.status, { 'is-current': lesson.id === current?.id }]"
          :style="stepStyle(index)"
        >
          <button
            type="button"
            class="lesson-node"
            :disabled="busy || !canOpen(lesson)"
            :aria-label="state(lesson).label + ' «' + lesson.title + '»'"
            :aria-current="lesson.id === current?.id ? 'step' : undefined"
            :title="state(lesson).label"
            @click="emit('open', lesson)"
          >
            <daily-practice-icon :name="state(lesson).icon" />
          </button>
          <span class="lesson-title">{{ lesson.title }}</span>
          <svg
            v-if="index < topic.lessons.length - 1"
            class="lesson-connector"
            viewBox="0 0 200 100"
            preserveAspectRatio="none"
            aria-hidden="true"
            focusable="false"
          >
            <path :d="connectorPath(index)" vector-effect="non-scaling-stroke" />
          </svg>
        </li>
      </ol>
      <p v-if="!topic.lessons.length" class="empty-path" role="status">Занятия готовятся к публикации.</p>
    </section>
    <p v-if="!topics.length" class="empty-path" role="status">Занятия этого уровня готовятся к публикации.</p>
  </div>
</template>

<style scoped>
.level-path {
  display: grid;
  gap: 20px;
  overflow: clip;
  padding-bottom: 8px;
}
.topic {
  position: relative;
  isolation: isolate;
  padding: 8px 24px 0;
  border: 1px solid #dfe9e280;
  border-radius: 28px;
  background-color: var(--topic-color);
  background-image: radial-gradient(ellipse at 0% 20%, var(--topic-accent) 0, transparent 44%),
    radial-gradient(ellipse at 100% 78%, var(--topic-accent) 0, transparent 40%),
    linear-gradient(90deg, transparent, #ffffff80 45%, #ffffff80 55%, transparent);
  box-shadow: 0 4px 16px #25475b05;
  scroll-margin-top: 132px;
}
.topic-background {
  position: absolute;
  inset: 0;
  z-index: -1;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: inherit;
}
.topic-divider {
  position: sticky;
  top: var(--practice-route-top, 68px);
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  height: var(--practice-topic-heading-height);
}
.topic-divider h2 {
  position: relative;
  max-width: min(100%, 480px);
  margin: 0;
  padding: 10px 48px;
  border: 1px solid #e6ece7;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 4px 6px #25475b04, 0 10px 26px #25475b0d;
  color: #25475b;
  text-align: center;
  font-size: 1.1rem;
  font-weight: 750;
  line-height: 1.5;
}
.topic-number {
  position: absolute;
  top: 50%;
  left: 10px;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  transform: translateY(-50%);
  border-radius: 10px;
  color: var(--topic-ink);
  background: var(--topic-color);
  font-size: .7rem;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
}
.topic-name {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.lesson-path {
  --step-shift: 64px;
  --step-gap: 40px;
  display: grid;
  justify-items: center;
  gap: var(--step-gap);
  margin: 0;
  padding: calc(var(--practice-topic-controls-height) - var(--practice-topic-heading-height) + 18px) 0 42px;
  list-style: none;
}
.lesson-step {
  position: relative;
  display: flex;
  align-items: center;
  flex-direction: column;
  gap: 12px;
  width: min(210px, 65%);
  transform: translateX(calc(var(--step-offset) * var(--step-shift)));
  scroll-margin-top: calc(var(--practice-route-top) + var(--practice-topic-controls-height) + 12px);
}
.lesson-connector {
  position: absolute;
  top: calc(100% + 5px);
  left: calc(50% - var(--step-shift));
  width: calc(2 * var(--step-shift));
  height: calc(var(--step-gap) - 10px);
  overflow: visible;
  color: var(--topic-ink);
  opacity: .35;
  pointer-events: none;
}
.lesson-connector path {
  fill: none;
  stroke: currentColor;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-dasharray: 1 8;
}
.level-path .lesson-step .lesson-node {
  border-radius: 50%;
}
.lesson-node {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  padding: 0;
  border: 3px solid #fff;
  border-radius: 50%;
  color: #fff;
  background: linear-gradient(160deg, #57a4a2, #388485);
  box-shadow: 0 5px 0 #2d686b, 0 9px 16px #25475b12;
  transition: transform .15s, box-shadow .15s;
}
.lesson-node :deep(svg) {
  width: 28px;
  height: 28px;
  stroke-width: 2.5;
}
.lesson-node:enabled:hover {
  transform: translateY(-3px);
}
.lesson-node:enabled:active {
  transform: translateY(4px);
  box-shadow: 0 2px 0 var(--lesson-pressed-shadow, #21604d);
}
.lesson-node:focus-visible {
  outline: 3px solid #165ca7;
  outline-offset: 7px;
}
.status-active .lesson-node {
  background: linear-gradient(160deg, #66aabf, #4389a0);
  box-shadow: 0 5px 0 #316d80, 0 9px 16px #25475b12;
}
.status-completed .lesson-node {
  background: linear-gradient(160deg, #7aaa93, #568b74);
  box-shadow: 0 5px 0 #42705b, 0 9px 16px #25475b0a;
}
.status-locked .lesson-node,
.status-unavailable .lesson-node {
  background: linear-gradient(160deg, #e8edef, #dce4e6);
  color: #82929a;
  box-shadow: 0 4px 0 #c6d1d5;
}
.is-current .lesson-node {
  --lesson-pressed-shadow: #267045;
  border-color: transparent;
  background: linear-gradient(155deg, #51aa63, #30854b);
  box-shadow: 0 6px 0 var(--lesson-pressed-shadow), 0 12px 24px #30854b26;
}
.lesson-title {
  max-width: 100%;
  padding: 6px 13px;
  border: 1px solid #ffffffb3;
  border-radius: 12px;
  background: #ffffffeb;
  box-shadow: 0 3px 8px #25475b05;
  color: #25475b;
  font-size: .9rem;
  font-weight: 650;
  text-align: center;
  line-height: 1.45;
  overflow-wrap: anywhere;
}
.is-current .lesson-title {
  color: #28633c;
  background: #f3fcf1;
  border-color: #d5e8cf;
}
.empty-path {
  padding: 24px;
  text-align: center;
  color: #526a7b;
}
@media (max-width: 600px) {
  .level-path {
    gap: 14px;
  }
  .topic {
    padding-inline: 10px;
    border-radius: 22px;
  }
  .topic-divider h2 {
    max-width: 100%;
    padding-inline: 40px;
    border-radius: 15px;
    font-size: 1rem;
  }
  .topic-number {
    left: 8px;
    width: 25px;
    height: 25px;
  }
  .lesson-path {
    --step-shift: 36px;
    --step-gap: 36px;
    padding-top: calc(var(--practice-topic-controls-height) - var(--practice-topic-heading-height) + 16px);
    padding-bottom: 40px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .level-path .lesson-step .lesson-node {
    transition: none;
  }
}
</style>
