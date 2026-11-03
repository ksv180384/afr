<script setup>
import FrenchText from '../FrenchText.vue';
import FrenchAnswerInput from '../FrenchAnswerInput.vue';
import SingleChoiceTask from './SingleChoiceTask.vue';
defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, uid: { type: String, required: true } });
const emit = defineEmits(['update:modelValue']);
</script>
<template>
  <div>
    <div class="blank-sentence" aria-label="Фраза с пропуском">
      <french-text v-if="task.text_before" :text="task.text_before" :transcription-ru="task.text_before_transcription_ru" />
      <span class="blank-mark" aria-label="Пропуск">…</span>
      <french-text v-if="task.text_after" :text="task.text_after" :transcription-ru="task.text_after_transcription_ru" />
    </div>
    <single-choice-task v-if="task.input_mode === 'choice'" :task="task" :model-value="modelValue" :disabled="disabled" :uid="uid" @update:model-value="emit('update:modelValue', $event)" />
    <french-answer-input v-else :id="uid + '-blank'" :model-value="modelValue.value" :disabled="disabled" label="Слово для пропуска" :french="task.answer_lang === 'fr'" @update:model-value="emit('update:modelValue', { value: $event })" />
  </div>
</template>
<style scoped>
.blank-sentence { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .4rem; margin-bottom: 1rem; }
.blank-mark { min-width: 3rem; border-bottom: 2px solid #155e91; text-align: center; font-size: 1.4rem; }
</style>
