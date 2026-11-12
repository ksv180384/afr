<script setup>
import { computed } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

const props = defineProps({status: String, label: String});
const states = {
  completed: ['check', 'Пройдено'],
  active: ['play', 'Начато'],
  in_progress: ['play', 'Начато'],
  paused: ['pause', 'Отложено'],
  available: ['circle', 'Доступно'],
  locked: ['lock', 'Закрыто'],
  unavailable: ['dash', 'Недоступно'],
};
const state = computed(() => states[props.status] ?? states.unavailable);
</script>

<template>
  <span class="practice-status" :class="status">
    <daily-practice-icon :name="state[0]" />
    {{ label || state[1] }}
  </span>
</template>

<style scoped>
.practice-status { display: inline-flex; align-items: center; width: fit-content; gap: .35rem; color: #435970; font-size: .875rem; font-weight: 650; line-height: 1.35; }
.practice-status :deep(svg) { width: 16px; height: 16px; }
.completed { color: #236444; }
.active, .available, .in_progress { color: #174f8c; }
</style>
