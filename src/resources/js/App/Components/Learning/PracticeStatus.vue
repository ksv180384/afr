<script setup>
import { computed, ref } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

const props = defineProps({status: String, label: String, iconOnly: Boolean});
const states = {
  completed: ['check', 'Пройдено'],
  active: ['play', 'Начато'],
  in_progress: ['play', 'Начато'],
  paused: ['pause', 'Отложено'],
  available: ['circle', 'Доступно'],
  locked: ['lock', 'Закрыто'],
  unavailable: ['dash', 'Недоступно'],
};
const expanded = ref(false);
const state = computed(() => states[props.status] ?? states.unavailable);
</script>

<template>
  <button
    v-if="iconOnly"
    type="button"
    class="practice-status status-button"
    :class="[status, { expanded }]"
    :aria-label="label || state[1]"
    :aria-expanded="expanded"
    @click.stop.prevent="expanded = !expanded"
    @blur="expanded = false"
    @keydown.esc.stop="expanded = false"
  >
    <daily-practice-icon :name="state[0]" />
    <span class="status-tooltip" aria-hidden="true">{{ label || state[1] }}</span>
  </button>
  <span v-else class="practice-status" :class="status">
    <daily-practice-icon :name="state[0]" />
    {{ label || state[1] }}
  </span>
</template>

<style scoped>
.practice-status { display: inline-flex; align-items: center; width: fit-content; gap: .35rem; color: #435970; font-size: .875rem; font-weight: 650; line-height: 1.35; }
.practice-status :deep(svg) { width: 16px; height: 16px; }
.completed { color: #236444; }
.active, .in_progress { color: #174f8c; }
.available { color: #08786a; }
.locked { color: #9b5219; }
.paused { color: #8455a5; }
.status-button { position: relative; justify-content: center; width: 44px; min-height: 44px; flex-shrink: 0; }
.status-tooltip {
  display: none;
  position: absolute;
  z-index: 2;
  inset-inline-end: 0;
  top: 100%;
  padding: .35rem .55rem;
  border-radius: .35rem;
  background: #18344c;
  color: #fff;
  white-space: nowrap;
}
.status-button:focus-visible .status-tooltip,
.status-button.expanded .status-tooltip { display: block; }
@media (hover: hover) {
  .status-button:hover .status-tooltip { display: block; }
}
</style>
