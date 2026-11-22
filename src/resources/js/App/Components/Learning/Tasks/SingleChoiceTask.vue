<script setup>
import { computed } from 'vue';
import { presentationOrder } from '@/App/Learning/presentationOrder.js';
import FrenchText from '../FrenchText.vue';
const props = defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, showTranscription: { type: Boolean, default: true }, uid: { type: String, required: true } });
const choices = computed(() => presentationOrder(props.task.choices, props.uid + ':choices'));
const emit = defineEmits(['update:modelValue']);
</script>
<template>
  <fieldset class="choice-set" :disabled="disabled">
    <legend :class="task.skill === 'listening' ? 'choice-legend' : 'sr-only'">Выберите один ответ</legend>
    <label v-for="choice in choices" :key="choice.id" class="choice-row" :class="{ selected: modelValue.choice_id === choice.id }">
      <input class="sr-only" type="radio" :name="'choice-' + uid" :value="choice.id" :checked="modelValue.choice_id === choice.id" @change="emit('update:modelValue', { choice_id: choice.id })">
      <french-text :show-transcription="showTranscription" v-if="choice.text_lang === 'fr'" :text="choice.text" :transcription-ru="choice.transcription_ru" compact />
      <span v-else>{{ choice.text }}</span>
    </label>
  </fieldset>
</template>
<style scoped>
.choice-set { display: grid; gap: .65rem; min-width: 0; }
.choice-legend { margin-bottom: .5rem; color: var(--practice-muted, #475569); font-size: .875rem; font-weight: 600; }
.choice-row { display: flex; gap: .8rem; align-items: center; border: 2px solid #dce5ed; border-radius: .75rem; padding: .75rem .9rem; background: white; min-height: 56px; cursor: pointer; overflow-wrap: anywhere; box-shadow: 0 2px 0 #e5ebf1; }
.choice-row:hover { background: #f7faff; border-color: #aac4dc; }
.choice-row:has(input:focus-visible) { outline: 3px solid #195bb2; outline-offset: 2px; }
.choice-row.selected { border-color: #3979b8; background: #edf5ff; box-shadow: 0 2px 0 #3979b8; }
fieldset:disabled .choice-row { cursor: default; }
</style>
