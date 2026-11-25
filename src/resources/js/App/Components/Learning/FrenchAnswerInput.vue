<script setup>
import { nextTick, ref } from 'vue';
import { insertedValue } from '@/App/Learning/playerUi.js';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
defineProps({
  modelValue: { type: String, default: '' },
  disabled: Boolean,
  readonly: Boolean,
  id: { type: String, required: true },
  label: { type: String, default: 'Ваш ответ по-французски' },
  french: { type: Boolean, default: true },
  spacious: Boolean,
});
const emit = defineEmits(['update:modelValue']);
const input = ref(null);
const characters = ['à', 'â', 'ç', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ù', 'û', 'ü', 'œ', '’'];
const insert = async (character) => {
  const field = input.value.input;
  const next = insertedValue(field.value, character, field.selectionStart ?? field.value.length, field.selectionEnd ?? field.value.length);
  field.focus();
  emit('update:modelValue', next.value);
  await nextTick();
  field.setSelectionRange(next.caret, next.caret);
};
</script>
<template>
  <div :class="{ 'answer-spacious': spacious }">
    <afr-input
      :id="id"
      ref="input"
      class="answer-input"
      :label="label"
      :lang="french ? 'fr' : 'ru'"
      :model-value="modelValue"
      :disabled="disabled"
      :readonly="readonly"
      autocomplete="off"
      autocapitalize="off"
      autocorrect="off"
      :spellcheck="false"
      maxlength="20000"
      @update:model-value="emit('update:modelValue', $event)"
    />
    <div v-if="french" class="character-bar" role="group" aria-label="Французские символы">
      <afr-button
        v-for="character in characters"
        :key="character"
        size="small"
        :disabled="disabled || readonly"
        :aria-label="'Вставить ' + character"
        @pointerdown.prevent
        @click="insert(character)"
      >
        {{ character }}
      </afr-button>
    </div>
  </div>
</template>
<style scoped>
.answer-input { width: 100%; min-width: 0; }
.character-bar { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .35rem; }
.character-bar :deep(button) { min-width: 30px; min-height: 28px; padding: 3px 6px; font-size: .875rem; }

.answer-spacious .answer-input :deep(label) {
  @apply mb-1.5 block text-sm font-semibold text-sky-950;
}

.answer-spacious .answer-input :deep(.afr-input) {
  @apply rounded-lg border-sky-200 shadow-sm focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100;
}

.answer-spacious .answer-input :deep(input) {
  @apply min-h-12 bg-white text-base text-slate-800 outline-none;
}

.answer-spacious .character-bar {
  @apply mt-3 grid grid-cols-5 gap-1.5 min-[480px]:grid-cols-8;
}

.answer-spacious .character-bar :deep(button) {
  @apply min-h-11 min-w-0 rounded-lg border-slate-200 bg-white text-base font-medium text-sky-900 shadow-sm
    hover:border-sky-300 hover:bg-sky-50 focus-visible:outline-sky-700;
}
</style>
