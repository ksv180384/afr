<script setup>
import { computed } from 'vue';
import { presentationOrder } from '@/App/Learning/presentationOrder.js';
import FrenchText from '../FrenchText.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
const props = defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, uid: { type: String, required: true } });
const rightItems = computed(() => presentationOrder(props.task.right_items, props.uid + ':pairs'));
const emit = defineEmits(['update:modelValue']);
const selected = (id) => props.modelValue.pairs.find((pair) => pair.left_id === id)?.right_id ?? '';
const choose = (left, right) => {
  const pairs = props.modelValue.pairs.filter((pair) => pair.left_id !== left && pair.right_id !== right);
  if (right) pairs.push({ left_id: left, right_id: right });
  emit('update:modelValue', { pairs });
};
const optionText = (item) => item.text + (item.text_lang === 'fr' ? ' · ' + item.transcription_ru : '');
</script>
<template>
  <fieldset :disabled="disabled">
    <legend class="pair-instructions">Подберите пару к каждому выражению. Каждый вариант используется один раз.</legend>
    <div v-for="(left, index) in task.left_items" :key="left.id" class="pair-row">
      <div>
        <french-text v-if="left.text_lang === 'fr'" :text="left.text" :transcription-ru="left.transcription_ru" compact />
        <span v-else>{{ left.text }}</span>
      </div>
      <div class="pair-answer">
        <label :for="uid + '-pair-' + index" class="sr-only">Пара для {{ left.text }}</label>
        <afr-select :id="uid + '-pair-' + index" native :disabled="disabled" :model-value="selected(left.id)" @update:model-value="choose(left.id, $event)">
          <afr-option value="" label="Выберите пару" />
          <afr-option v-for="right in rightItems" :key="right.id" :value="right.id" :label="optionText(right)" />
        </afr-select>
        <template v-if="selected(left.id)">
          <french-text v-for="right in task.right_items.filter((item) => item.id === selected(left.id) && item.text_lang === 'fr')" :key="right.id" :text="right.text" :transcription-ru="right.transcription_ru" compact />
        </template>
      </div>
    </div>
    <p class="pair-hint">Выбор занятого варианта перенесёт его в новую пару.</p>
  </fieldset>
</template>
<style scoped>
fieldset { min-width: 0; }
.pair-instructions, .pair-hint { color: #475569; font-size: .95rem; margin-bottom: .75rem; }
.pair-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: .5rem; padding: .5rem 0; border-bottom: 1px solid #dbeafe; }
.pair-answer { min-width: 0; display: grid; gap: .5rem; }
.pair-hint { margin-top: .75rem; }
@media (max-width: 480px) { .pair-row { grid-template-columns: 1fr; } }
</style>
