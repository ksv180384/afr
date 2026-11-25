<script setup>
import DailyPracticeIcon from '@/App/Components/Learning/DailyPracticeIcon.vue';

defineProps({
  position: { type: Number, required: true },
  total: { type: Number, required: true },
  score: { type: Number, default: null },
});
</script>

<template>
  <div class="word-progress">
    <span>Слово <strong>{{ position }}</strong> из {{ total }}</span>
    <span v-if="score !== null" class="word-score">
      <daily-practice-icon name="star" />
      Баллы <strong>{{ score }}</strong>
    </span>
    <progress :value="position" :max="total" aria-label="Текущее слово" />
  </div>
</template>

<style scoped>
.word-progress {
  @apply grid grid-cols-[1fr_auto] items-center gap-y-2 text-sm tabular-nums text-slate-600;
}

.word-progress strong { @apply font-semibold text-sky-950; }
.word-score { @apply inline-flex items-center gap-1.5; }
.word-score svg { @apply size-4 text-sky-700; }

.word-progress progress {
  @apply col-span-full h-1 w-full appearance-none overflow-hidden rounded-full border-0 bg-sky-100;
}

.word-progress progress::-webkit-progress-bar { @apply bg-sky-100; }
.word-progress progress::-webkit-progress-value { @apply rounded-full bg-sky-700; }
.word-progress progress::-moz-progress-bar { @apply rounded-full bg-sky-700; }
</style>
