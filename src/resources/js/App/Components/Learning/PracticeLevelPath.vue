<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PracticeTopicPicker from './PracticeTopicPicker.vue';
import PracticePathIcon from './PracticePathIcon.vue';
import { practiceTopicFromHash, restorePracticeScroll } from '@/App/Learning/practiceRunNavigation.js';
import { canTrainTopic, trainingModes } from '@/App/Learning/training.js';
import satchelIllustration from './assets/lesson-satchel.webp';
import flagIllustration from './assets/lesson-flag.webp';
import pencilIllustration from './assets/lesson-pencil.webp';
import medalIllustration from './assets/lesson-medal.webp';
import expressionsIllustration from './assets/training-expressions.webp';
import dialoguesIllustration from './assets/training-dialogues.webp';

const props = defineProps({ route: Object, currentLevel: String, selection: Object, busy: Boolean });
const emit = defineEmits(['open', 'train', 'topics']);
const trainingIllustrations = { recall: expressionsIllustration, dialogue: dialoguesIllustration };
const path = ref(null);
const topics = computed(() => props.route.topics ?? []);
const lessons = computed(() => topics.value.flatMap(topic => topic.lessons));
const current = computed(() => lessons.value.find(lesson => lesson.id === props.route.current_lesson_id)
  ?? lessons.value.find(lesson => lesson.status === 'active')
  ?? lessons.value.find(lesson => lesson.status === 'available')
  ?? lessons.value.at(-1));
const states = {
  next: { illustration: flagIllustration, label: 'Начать занятие' },
  started: { illustration: pencilIllustration, label: 'Начать занятие заново' },
  active: { illustration: pencilIllustration, label: 'Продолжить занятие' },
  available: { illustration: satchelIllustration, label: 'Начать занятие' },
  completed: { illustration: medalIllustration, label: 'Повторить занятие' },
  locked: { illustration: satchelIllustration, badge: 'lock', label: 'Занятие закрыто' },
  unavailable: { illustration: satchelIllustration, badge: 'dash', label: 'Занятие недоступно' },
};
const state = lesson => {
  if (lesson.status === 'available') {
    if (lesson.started) return states.started;
    if (lesson.id === current.value?.id) return states.next;
  }
  return states[lesson.status] ?? states.unavailable;
};
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
  const hashTopic = practiceTopicFromHash(window.location.hash);
  if (!container || (window.location.hash && !hashTopic)) return;
  if (restorePracticeScroll()) return;
  const selectedTopic = hashTopic ?? props.selection?.unit;
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
      :id="'topic-' + topic.id"
      :aria-labelledby="'topic-heading-' + topic.id"
      class="topic"
      :style="topicStyle(topicIndex)"
    >
      <img v-if="topicBackgrounds.get(topic.id)" class="topic-background" :src="topicBackgrounds.get(topic.id)"
        alt="" loading="lazy" decoding="async" />
      <header class="topic-divider">
        <h2 :id="'topic-heading-' + topic.id" :aria-label="topic.title">
          <practice-topic-picker :id="topicIndex ? 'practice-topics-' + topic.id : 'practice-topics-trigger'"
            :current-level="currentLevel" :route-level="route.level.id" :topic="topic" :number="topicIndex + 1"
            @click="emit('topics', $event)" />
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
              <practice-path-icon :illustration="state(lesson).illustration" :badge="state(lesson).badge" />
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
              <practice-path-icon class="training-node" :illustration="trainingIllustrations[mode.id]"
                :badge="canTrainTopic(topic) ? undefined : 'lock'" />
              <span class="training-label">{{ mode.label }}</span>
            </button>
          </template>
        </li>
      </ol>
      <p v-if="!topic.lessons.length" class="empty-path" role="status">Занятия готовятся к публикации.</p>
    </section>
    <template v-if="!topics.length">
      <div class="topic-divider">
        <practice-topic-picker id="practice-topics-trigger" :current-level="currentLevel" :route-level="route.level.id"
          @click="emit('topics', $event)" />
      </div>
      <p class="empty-path" role="status">Занятия этого уровня готовятся к публикации.</p>
    </template>
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
  scroll-margin-top: var(--practice-route-top);
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
  min-height: var(--practice-topic-heading-height);
}
.topic-divider h2 {
  display: grid;
  max-width: min(100%, 32rem);
  margin: 0;
}
.lesson-path {
  --step-shift: 64px;
  --step-gap: 40px;
  display: grid;
  justify-items: center;
  gap: var(--step-gap);
  margin: 0;
  padding: 18px 0 42px;
  list-style: none;
}
.lesson-step {
  --training-shift: clamp(90px, 26%, 176px);
  position: relative;
  width: 100%;
  scroll-margin-top: calc(var(--practice-route-top) + var(--practice-topic-heading-height) + 12px);
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
  --node-top: #fff8e6;
  --node-bottom: #f5d49a;
  --node-edge: #bc8d54;
  --node-border: #f0d7a8;
  color: #6d4727;
}
.training-stop--dialogue {
  --training-offset: calc(-1 * var(--training-shift));
  --node-top: #faf3ff;
  --node-bottom: #e9d7f6;
  --node-edge: #a18abd;
  --node-border: #e3d2f0;
  color: #60447f;
}
.training-stop--locked {
  --node-top: #f2f5f6;
  --node-bottom: #dce5e7;
  --node-edge: #bac9ce;
  --node-border: #dce5e7;
  color: #586e79;
}
.training-node {
  --node-size: clamp(56px, 10vw, 66px);
  transition: transform .15s;
}
.training-stop:enabled:hover .training-node { transform: translateY(-2px); }
.training-stop:enabled:active .training-node { transform: translateY(2px); }
.training-label {
  padding: 3px 8px;
  border: 1px solid var(--node-border);
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
  border-radius: var(--node-radius);
}
.lesson-node {
  --node-size: 76px;
  --node-radius: 24px;
  --node-top: #f1faf0;
  --node-bottom: #c5e6d4;
  --node-edge: #83ae99;
  --node-border: #c4dfcd;
  --node-ring: #71be9c;
  position: relative;
  display: grid;
  padding: 0;
  transition: transform .15s;
}
.lesson-node:enabled:hover {
  filter: brightness(1.06);
  transform: translateY(-2px);
}
.lesson-node:enabled:active {
  transform: translateY(2px);
}
.lesson-node:focus-visible {
  outline: 3px solid #165ca7;
  outline-offset: 9px;
}
.status-active .lesson-node {
  --node-top: #f1f8ff;
  --node-bottom: #c3dff2;
  --node-edge: #89adc8;
  --node-border: #c5deed;
  --node-ring: #81b8d7;
}
.status-completed .lesson-node {
  --node-top: #fff9e8;
  --node-bottom: #f3ddb0;
  --node-edge: #c3a06f;
  --node-border: #efdcb6;
}
.status-locked .lesson-node,
.status-unavailable .lesson-node {
  --node-top: #f0f4f4;
  --node-bottom: #dce5e7;
  --node-edge: #bac9ce;
  --node-border: #dce5e7;
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
  .lesson-path {
    --step-shift: clamp(20px, 7vw, 36px);
    --step-gap: 36px;
    padding-top: 16px;
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
