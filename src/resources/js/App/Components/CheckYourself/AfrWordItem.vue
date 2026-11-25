<script setup>
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import DailyPracticeIcon from '@/App/Components/Learning/DailyPracticeIcon.vue';

defineProps({
  word: { type: Object, required: true },
  incorrect: Boolean,
});
defineEmits(['choose']);
</script>

<template>
  <afr-button
    class="word-option"
    :class="{ 'word-option--incorrect': incorrect }"
    :aria-disabled="incorrect || undefined"
    @click="!incorrect && $emit('choose', word.id)"
  >
    <span>{{ word.translation }}</span>
    <span class="option-mark" aria-hidden="true">
      <daily-practice-icon v-if="incorrect" name="close" />
    </span>
    <span v-if="incorrect" class="sr-only">Неверный перевод</span>
  </afr-button>
</template>

<style scoped>
button.word-option {
  @apply min-h-12 w-full justify-between gap-3 whitespace-normal rounded-xl border border-slate-200
    bg-white px-4 py-2.5 text-left leading-snug text-slate-800 shadow-sm;
  overflow-wrap: anywhere;
}

.word-option:not([aria-disabled]):hover {
  @apply border-sky-300 bg-sky-50 text-sky-900;
}

.word-option:focus-visible {
  @apply outline outline-2 outline-offset-2 outline-sky-700;
}

.option-mark {
  @apply grid size-4 shrink-0 place-items-center rounded-full border border-slate-300;
}

button.word-option--incorrect {
  @apply cursor-default border-rose-200 bg-rose-50 text-rose-900;
}

.word-option--incorrect .option-mark {
  @apply border-0 text-rose-700;
}

.option-mark svg {
  @apply size-4;
}
</style>
