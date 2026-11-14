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
  const nextOffset = stepOffsets[(index + 1) % stepOffsets.length];
  return { '--step-offset': offset, '--next-step-delta': nextOffset - offset };
};
const colors = ['#eaf4ef', '#edf2fa', '#fcf1e5', '#f4edf7', '#e9f4f7'];
// Add AI illustrations as resources/images/practice/topics/<topic ID>.webp (or .png/.jpg).
const backgrounds = import.meta.glob('/resources/images/practice/topics/*.{webp,png,jpg}', {
  eager: true, query: '?url', import: 'default',
});
const topicStyle = (topic, index) => {
  const image = topic.background_url ?? ['webp', 'png', 'jpg']
    .map(extension => backgrounds['/resources/images/practice/topics/' + topic.id + '.' + extension])
    .find(Boolean);
  return {
    '--topic-color': colors[index % colors.length],
    backgroundImage: image ? 'url("' + image + '")' : undefined,
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
      :style="topicStyle(topic, topicIndex)"
    >
      <header class="topic-divider">
        <span aria-hidden="true" />
        <h2 :id="'topic-' + topic.id" :title="topic.title">{{ topic.title }}</h2>
        <span aria-hidden="true" />
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
        </li>
      </ol>
      <p v-if="!topic.lessons.length" class="empty-path" role="status">Занятия готовятся к публикации.</p>
    </section>
    <p v-if="!topics.length" class="empty-path" role="status">Занятия этого уровня готовятся к публикации.</p>
  </div>
</template>

<style scoped>
.level-path {
  overflow: clip;
  border-radius: 24px;
}
.topic {
  position: relative;
  padding-inline: 24px;
  background-color: var(--topic-color);
  background-position: center;
  background-size: cover;
  scroll-margin-top: 132px;
}
.topic-divider {
  position: sticky;
  top: var(--practice-route-top, 68px);
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  height: var(--practice-topic-heading-height);
}
.topic-divider span {
  height: 1px;
  flex: 1;
  background: #18344c30;
}
.topic-divider h2 {
  max-width: 75%;
  margin: 0;
  padding: 7px 16px;
  overflow: hidden;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 4px 14px #18344c26;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #25475b;
  text-align: center;
  font-size: 1.1rem;
  font-weight: 750;
  line-height: 1.5;
}
.lesson-path {
  --step-shift: 64px;
  --step-gap: 48px;
  display: grid;
  justify-items: center;
  gap: var(--step-gap);
  margin: 0;
  padding: calc(var(--practice-topic-controls-height) - var(--practice-topic-heading-height) + 24px) 0 48px;
  list-style: none;
}
.lesson-step {
  position: relative;
  display: flex;
  align-items: center;
  flex-direction: column;
  gap: 14px;
  width: min(210px, 65%);
  transform: translateX(calc(var(--step-offset) * var(--step-shift)));
  scroll-margin-top: calc(var(--practice-route-top) + var(--practice-topic-controls-height) + 12px);
}
.lesson-step::after {
  content: '';
  position: absolute;
  --connector-x: calc(var(--next-step-delta) * var(--step-shift));
  --connector-length: hypot(var(--connector-x), var(--step-gap));
  top: 100%;
  left: calc(50% - 2px);
  width: 4px;
  height: var(--connector-length);
  transform-origin: top center;
  transform: rotate(atan2(calc(0px - var(--connector-x)), var(--step-gap)));
  background: radial-gradient(circle, #52786b99 1.6px, transparent 2px) center / 4px 8px round;
  clip-path: inset(5px 0 8px);
  pointer-events: none;
}
.lesson-step:last-child::after {
  display: none;
}
.level-path .lesson-step .lesson-node {
  border-radius: 50%;
}
.lesson-node {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border: 3px solid #fff;
  border-radius: 50%;
  color: #fff;
  background: #31856a;
  box-shadow: 0 6px 0 #21604d, 0 8px 16px #18344c15;
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
  box-shadow: 0 2px 0 #21604d;
}
.lesson-node:focus-visible {
  outline: 3px solid #165ca7;
  outline-offset: 7px;
}
.status-active .lesson-node {
  background: #236bac;
  box-shadow: 0 6px 0 #184974;
}
.status-locked .lesson-node,
.status-unavailable .lesson-node {
  background: #dce4e6;
  color: #76868e;
  box-shadow: 0 6px 0 #bdcbd0;
}
.is-current .lesson-node {
  background: #2556b3;
  box-shadow: 0 6px 0 #193c86;
}
.lesson-title {
  padding: 5px 12px;
  border-radius: 10px;
  background: #fffffff2;
  color: #25475b;
  font-size: .95rem;
  font-weight: 650;
  text-align: center;
  line-height: 1.45;
}
.empty-path {
  padding: 24px;
  text-align: center;
  color: #526a7b;
}
@media (max-width: 600px) {
  .topic {
    padding-inline: 12px;
  }
  .topic-divider {
    gap: 6px;
  }
  .topic-divider h2 {
    max-width: 100%;
    font-size: 1rem;
  }
  .topic-divider span {
    display: none;
  }
  .lesson-path {
    --step-shift: 42px;
    --step-gap: 42px;
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
