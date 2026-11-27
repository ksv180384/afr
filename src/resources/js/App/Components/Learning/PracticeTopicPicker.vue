<script setup>
import { computed } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

const props = defineProps({
  currentLevel: String,
  routeLevel: { type: String, required: true },
  topic: Object,
  number: Number,
});
const code = level => level === 'PRE_A1' ? 'Pre-A1' : level;
const topicCaption = computed(() => {
  const level = props.currentLevel && props.currentLevel !== props.routeLevel ? 'Уровень ' + code(props.routeLevel) : '';
  const topic = props.topic ? 'Тема ' + String(props.number).padStart(2, '0') : '';
  return [level, topic].filter(Boolean).join(' · ');
});
</script>

<template>
  <button type="button" class="topic-picker" aria-haspopup="dialog" aria-controls="practice-route-dialog"
    title="Выбрать уровень или тему">
    <span class="picker-level">
      <span class="picker-caption">{{ currentLevel ? 'Ваш уровень' : 'Уровень' }}</span>
      <strong>{{ code(currentLevel || routeLevel) }}</strong>
    </span>
    <span class="picker-topic">
      <span v-if="topicCaption" class="picker-caption">{{ topicCaption }}</span>
      <span class="picker-name">{{ topic?.title || 'Уровни и темы' }}</span>
    </span>
    <daily-practice-icon name="chevron" class="picker-chevron" />
    <span class="sr-only">Выбрать уровень или тему</span>
  </button>
</template>

<style scoped>
button.topic-picker {
  display: inline-grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: .65rem;
  max-width: 100%;
  min-height: 44px;
  padding: .3rem .75rem .3rem .3rem;
  border: 1px solid #d8e4df;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 3px 10px #25475b0d;
  color: #25475b;
  text-align: left;
}
.topic-picker:hover { border-color: #9ab8a9; }
.picker-level, .picker-topic { display: grid; gap: 1px; }
.picker-level {
  justify-items: center;
  padding: .25rem .55rem;
  border-radius: 11px;
  background: var(--topic-color, #f0f6f2);
  color: #365d4c;
  white-space: nowrap;
}
.picker-level strong { font-size: .9rem; font-weight: 750; line-height: 1.15; }
.picker-caption { color: #536c63; font-size: .6875rem; font-weight: 600; line-height: 1.2; }
.picker-name {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.2;
}
.topic-picker .picker-chevron { width: 16px; height: 16px; color: #506c60; transform: rotate(90deg); }
</style>
