<script setup>
import { computed } from 'vue';
import { presentationOrder } from '@/App/Learning/presentationOrder.js';
import FrenchText from '../FrenchText.vue';
const props = defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, uid: { type: String, required: true } });
const choices = computed(() => presentationOrder(props.task.choices, props.uid + ':choices'));
const emit = defineEmits(['update:modelValue']);
</script>
<template>
  <fieldset class="choice-set" :disabled="disabled">
    <legend class="sr-only">Выберите один ответ</legend>
    <label v-for="choice in choices" :key="choice.id" class="choice-row" :class="{ selected: modelValue.choice_id === choice.id }">
      <input type="radio" :name="'choice-' + uid" :value="choice.id" :checked="modelValue.choice_id === choice.id" @change="emit('update:modelValue', { choice_id: choice.id })">
      <french-text v-if="choice.text_lang === 'fr'" :text="choice.text" :transcription-ru="choice.transcription_ru" compact />
      <span v-else>{{ choice.text }}</span>
    </label>
  </fieldset>
</template>
<style scoped>
.choice-set { display: grid; gap: .5rem; min-width: 0; }
.choice-row { display: flex; gap: .5rem; align-items: flex-start; border: 1px solid #d1d5db; border-radius: .25rem; padding: .5rem .65rem; background: white; cursor: pointer; overflow-wrap: anywhere; }
.choice-row.selected { border-color: #93c5fd; background: #eff6ff; }
input { width:14px; height:14px; margin-top: .3rem; flex-shrink: 0; accent-color:#3b82f6; }
fieldset:disabled .choice-row { cursor: default; }
</style>
