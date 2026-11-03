<script setup>
import { nextTick, ref } from 'vue';
import { insertedValue } from '@/App/Learning/playerUi.js';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
defineProps({ modelValue: { type: String, default: '' }, disabled: Boolean, id: { type: String, required: true }, label: { type: String, default: 'Ваш ответ по-французски' }, french: { type: Boolean, default: true } });
const emit = defineEmits(['update:modelValue']);
const input = ref(null);
const characters = ['à', 'â', 'ç', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ù', 'û', 'ü', 'œ', '’'];
const insert = async (character) => {
  const field = input.value.input;
  const next = insertedValue(field.value, character, field.selectionStart ?? field.value.length, field.selectionEnd ?? field.value.length);
  emit('update:modelValue', next.value);
  await nextTick();
  field.focus();
  field.setSelectionRange(next.caret, next.caret);
};
</script>
<template>
  <div>
    <afr-input :id="id" ref="input" class="answer-input" :label="label" :lang="french ? 'fr' : 'ru'" :model-value="modelValue" :disabled="disabled" autocomplete="off" autocapitalize="off" :spellcheck="false" maxlength="20000" @update:model-value="emit('update:modelValue', $event)" />
    <div v-if="french" class="character-bar" role="group" aria-label="Французские символы">
      <afr-button v-for="character in characters" :key="character" size="small" :disabled="disabled" :aria-label="'Вставить ' + character" @click="insert(character)">{{ character }}</afr-button>
    </div>
  </div>
</template>
<style scoped>
.answer-input { width: 100%; min-width: 0; }
.character-bar { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .35rem; }
.character-bar :deep(button) { min-width: 30px; min-height: 28px; padding: 3px 6px; font-size: .875rem; }
</style>
