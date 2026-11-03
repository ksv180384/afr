<script setup>
import FrenchAnswerInput from '../FrenchAnswerInput.vue';
defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, uid: { type: String, required: true } });
const emit = defineEmits(['update:modelValue']);
</script>
<template>
  <french-answer-input :id="uid + '-short'" :model-value="modelValue.value" :disabled="disabled" :french="task.answer_lang === 'fr'" :label="task.answer_lang === 'fr' ? 'Ваш ответ по-французски' : 'Ваш ответ по-русски'" @update:model-value="emit('update:modelValue', { value: $event })" />
</template>
