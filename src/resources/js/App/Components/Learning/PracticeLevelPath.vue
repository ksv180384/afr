<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { restorePracticeScroll } from '@/App/Learning/practiceRunNavigation.js';
import { canTrainTopic, trainingModes } from '@/App/Learning/training.js';
import expressionsIllustration from './assets/training-expressions.webp';
import dialoguesIllustration from './assets/training-dialogues.webp';

const props = defineProps({ route: Object, selection: Object, busy: Boolean });
const emit = defineEmits(['open', 'train']);
const trainingIllustrations = { recall: expressionsIllustration, dialogue: dialoguesIllustration };
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
const trainingRow = (modeIndex, length) => Math.floor(length * (modeIndex + 1) / 3);
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
let scrollFrame;
const scrollToLesson = () => {
  if (document.readyState !== 'complete') return;
  cancelAnimationFrame(scrollFrame);
  // Inertia resets scroll two frames after swapping the page.
  scrollFrame = requestAnimationFrame(() => {
    scrollFrame = requestAnimationFrame(() => {
      scrollFrame = requestAnimationFrame(applyScroll);
    });
  });
};
const applyScroll = () => {
  const container = path.value;
  if (!container || window.location.hash) return;
  if (restorePracticeScroll()) return;
  const selectedTopic = props.selection?.unit;
  const target = selectedTopic
    ? [...container.querySelectorAll('[data-topic]')].find(element => element.dataset.topic === selectedTopic)
    : [...container.querySelectorAll('[data-lesson]')].find(element => element.dataset.lesson === current.value?.id);
  target?.scrollIntoView({ block: selectedTopic ? 'start' : 'center', behavior: 'instant' });
};
onMounted(() => {
  if (document.readyState === 'complete') scrollToLesson();
  else window.addEventListener('load', scrollToLesson, { once: true });
});
watch([() => props.route.level.id, () => props.selection?.unit, () => current.value?.id], scrollToLesson, { flush: 'post' });
onBeforeUnmount(() => {
  window.removeEventListener('load', scrollToLesson);
  cancelAnimationFrame(scrollFrame);
});
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
          <div class="lesson-stop">
            <button
              type="button"
              class="lesson-node"
              :disabled="busy || !canOpen(lesson)"
              :aria-label="state(lesson).label + ' «' + lesson.title + '»'"
              :aria-current="lesson.id === current?.id ? 'step' : undefined"
              :title="state(lesson).label"
              @click="emit('open', lesson)"
            >
              <daily-practice-icon :name="state(lesson).icon" :class="{ 'lesson-icon--play': state(lesson).icon === 'play' }" />
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
          </div>
          <template v-for="(mode, modeIndex) in trainingModes" :key="mode.id">
            <button
              v-if="index === trainingRow(modeIndex, topic.lessons.length)"
              :id="'practice-training-' + topic.id + '-' + mode.id"
              type="button"
              class="training-stop"
              :class="['training-stop--' + mode.id, { 'training-stop--locked': !canTrainTopic(topic) }]"
              :disabled="busy || !canTrainTopic(topic)"
              :aria-label="'Тренировка «' + mode.label + '» по теме «' + topic.title + '»' + (canTrainTopic(topic) ? '' : '. ' + topic.reason)"
              :title="canTrainTopic(topic) ? undefined : topic.reason"
              @click="emit('train', topic, mode, $event)"
            >
              <span class="training-node">
                <img :src="trainingIllustrations[mode.id]" alt="" width="160" height="160" loading="lazy" decoding="async" />
                <daily-practice-icon v-if="!canTrainTopic(topic)" class="training-lock" name="lock" />
              </span>
              <span class="training-label">{{ mode.label }}</span>
            </button>
          </template>
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
  opacity: .2;
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
  --training-shift: clamp(90px, 26%, 176px);
  position: relative;
  width: 100%;
  scroll-margin-top: calc(var(--practice-route-top) + var(--practice-topic-controls-height) + 12px);
}
.lesson-stop {
  position: relative;
  display: flex;
  align-items: center;
  flex-direction: column;
  gap: 12px;
  width: min(210px, 65%);
  margin-inline: auto;
  transform: translateX(calc(var(--step-offset) * var(--step-shift)));
}
.training-stop {
  position: absolute;
  top: 3px;
  left: calc(50% + var(--training-offset));
  display: grid;
  justify-items: center;
  gap: 6px;
  width: max-content;
  padding: 0;
  transform: translateX(-50%);
}
.training-stop--recall {
  --training-offset: var(--training-shift);
  --training-top: #fff8e6;
  --training-bottom: #f5d49a;
  --training-edge: #bc8d54;
  --training-border: #f0d7a8;
  color: #6d4727;
}
.training-stop--dialogue {
  --training-offset: calc(-1 * var(--training-shift));
  --training-top: #faf3ff;
  --training-bottom: #e9d7f6;
  --training-edge: #a18abd;
  --training-border: #e3d2f0;
  color: #60447f;
}
.training-stop--locked {
  --training-top: #f2f5f6;
  --training-bottom: #dce5e7;
  --training-edge: #bac9ce;
  --training-border: #dce5e7;
  color: #586e79;
}
.training-node {
  --training-size: clamp(56px, 10vw, 66px);
  position: relative;
  display: grid;
  place-items: center;
  box-sizing: border-box;
  width: var(--training-size);
  height: var(--training-size);
  border: 2px solid #fff;
  border-radius: 50%;
  background: radial-gradient(ellipse at 40% 8%, #ffffffa6, transparent 66%),
    linear-gradient(155deg, var(--training-top), var(--training-bottom));
  box-shadow: inset 0 0 0 1px var(--training-border), 0 3px 0 var(--training-edge), 0 5px 10px #25475b1a;
  transition: transform .15s;
}
.training-node img { width: 84%; height: 84%; object-fit: contain; }
.training-stop--locked img { filter: grayscale(1); opacity: .6; }
.training-node .training-lock {
  position: absolute;
  right: -2px;
  bottom: -2px;
  width: 21px;
  height: 21px;
  padding: 3px;
  border: 1px solid var(--training-edge);
  border-radius: 50%;
  background: white;
}
.training-stop:enabled:hover .training-node { transform: translateY(-2px); }
.training-stop:enabled:active .training-node { transform: translateY(2px); }
.training-label {
  padding: 3px 8px;
  border: 1px solid var(--training-border);
  border-radius: 12px;
  background: #fff;
  font-size: .8125rem;
  font-weight: 700;
  line-height: 1.2;
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
  border-radius: 24px;
}
.lesson-node {
  --node-top: #218e7e;
  --node-bottom: #087064;
  --node-edge: #07564d;
  --node-ring: #71be9c;
  --node-depth: 4px;
  position: relative;
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  padding: 0;
  border: 2px solid #fff;
  color: #fff;
  background: radial-gradient(ellipse at 20% 0%, #ffffff30, transparent 68%),
    linear-gradient(155deg, var(--node-top), var(--node-bottom));
  box-shadow: 0 var(--node-depth) 0 var(--node-edge), 0 8px 16px #25475b1a;
  transition: transform .15s, box-shadow .15s, filter .15s;
}
.lesson-node::before {
  position: absolute;
  inset: 5px;
  border: 1px solid currentColor;
  border-radius: 17px;
  opacity: .25;
  pointer-events: none;
  content: '';
}
.lesson-node :deep(svg) {
  width: 32px;
  height: 32px;
  stroke-width: 2.2;
}
.lesson-node .lesson-icon--play {
  fill: currentColor;
  stroke-width: 1.5;
  transform: translateX(-1px);
}
.lesson-node:enabled:hover {
  filter: brightness(1.06);
  transform: translateY(-2px);
}
.lesson-node:enabled:active {
  --node-depth: 2px;
  transform: translateY(2px);
}
.lesson-node:focus-visible {
  outline: 3px solid #165ca7;
  outline-offset: 9px;
}
.status-active .lesson-node {
  --node-top: #327f9a;
  --node-bottom: #235b7a;
  --node-edge: #1c4b65;
  --node-ring: #81b8d7;
}
.status-completed .lesson-node {
  --node-top: #e6f7df;
  --node-bottom: #bddfb7;
  --node-edge: #86b680;
  color: #28623b;
}
.status-locked .lesson-node,
.status-unavailable .lesson-node {
  --node-top: #f0f4f4;
  --node-bottom: #dce5e7;
  --node-edge: #bac9ce;
  color: #586e79;
}
.is-current:is(.status-active, .status-available) .lesson-node::after {
  position: absolute;
  inset: -7px;
  border: 2px solid var(--node-ring);
  border-radius: 29px;
  pointer-events: none;
  content: '';
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
    --step-shift: clamp(20px, 7vw, 36px);
    --step-gap: 36px;
    padding-top: calc(var(--practice-topic-controls-height) - var(--practice-topic-heading-height) + 16px);
    padding-bottom: 40px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .level-path .lesson-step .lesson-node {
    transition: none;
  }
  .training-node { transition: none; }
}
</style>
