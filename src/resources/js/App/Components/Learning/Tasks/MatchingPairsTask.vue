<script setup>
import { computed, ref, watch } from 'vue';
import { presentationOrder } from '@/App/Learning/presentationOrder.js';
import FrenchText from '../FrenchText.vue';
const props = defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, showTranscription: { type: Boolean, default: true }, uid: { type: String, required: true } });
const emit = defineEmits(['update:modelValue']);
const rightItems = computed(() => presentationOrder(props.task.right_items, props.uid + ':pairs'));
const pending = ref(null);
const announcement = ref('');
const pairIndex = (side, id) => props.modelValue.pairs.findIndex(pair => pair[side + '_id'] === id);
const selected = (side, id) => pending.value?.side === side && pending.value.id === id;
const choose = (side, id) => {
  if (props.disabled) return;
  if (selected(side, id)) { pending.value = null; return; }
  if (!pending.value || pending.value.side === side) { pending.value = { side, id }; return; }
  const left = side === 'left' ? id : pending.value.id;
  const right = side === 'right' ? id : pending.value.id;
  const pairs = props.modelValue.pairs.filter(pair => pair.left_id !== left && pair.right_id !== right);
  pairs.push({ left_id: left, right_id: right });
  emit('update:modelValue', { pairs });
  pending.value = null;
  announcement.value = `Пара составлена. Готово ${pairs.length} из ${props.task.left_items.length}.`;
};
watch(() => props.disabled, () => { pending.value = null; });
watch(() => props.modelValue.pairs.length, length => { if (!length) pending.value = null; });
const label = (side, item) => item.text + (pairIndex(side, item.id) >= 0 ? `, пара ${pairIndex(side, item.id) + 1}. Нажмите, чтобы изменить` : '');
</script>
<template>
  <fieldset :disabled="disabled" class="matching-task">
    <legend class="pair-instructions">Выберите две карточки, которые подходят друг к другу. Можно начать с любой стороны.</legend>
    <div class="pair-grid">
      <template v-for="(left, index) in task.left_items" :key="left.id">
        <template v-for="entry in [{ side: 'left', item: left }, { side: 'right', item: rightItems[index] }].filter(entry => entry.item)" :key="entry.side">
          <button type="button" class="pair-tile" :class="{ selected: selected(entry.side, entry.item.id), paired: pairIndex(entry.side, entry.item.id) >= 0 }"
            :disabled="disabled" :aria-pressed="selected(entry.side, entry.item.id)" :aria-label="label(entry.side, entry.item)"
            @click="choose(entry.side, entry.item.id)">
            <span v-if="pairIndex(entry.side, entry.item.id) >= 0" class="pair-number filled" aria-hidden="true">{{ pairIndex(entry.side, entry.item.id) + 1 }}</span>
            <french-text v-if="entry.item.text_lang === 'fr'" :show-transcription="showTranscription" :text="entry.item.text" :transcription-ru="entry.item.transcription_ru" compact />
            <span v-else>{{ entry.item.text }}</span>
          </button>
        </template>
      </template>
    </div>
    <div class="pair-caption">
      <p>{{ pending ? 'Теперь выберите пару в другой колонке' : `Составлено пар: ${modelValue.pairs.length} из ${task.left_items.length}` }}</p>
      <button type="button" class="reset-pairs" :disabled="disabled || !modelValue.pairs.length" @click="pending = null; emit('update:modelValue', { pairs: [] })">Сбросить</button>
    </div>
    <span class="sr-only" role="status">{{ announcement }}</span>
  </fieldset>
</template>
<style scoped>
fieldset { min-width: 0; }
.pair-instructions { color: #64748b; font-size: .9rem; margin-bottom: 1rem; }
.pair-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .65rem 1rem; }
.pair-tile { position: relative; display: flex; align-items: center; min-width: 0; min-height: 64px; padding: .7rem 2.3rem .7rem .85rem; border: 2px solid #dce5ed; border-radius: .75rem; background: #fff; text-align: left; line-height: 1.4; box-shadow: 0 2px 0 #e5ebf1; }
.pair-tile:not(:disabled):hover { border-color: #9cb9d6; background: #f7faff; }
.pair-tile.paired { background: #eef4fb; border-color: #bacde1; }
.pair-tile.selected { background: #e5f0ff; border-color: #165ca7; box-shadow: 0 2px 0 #165ca7; }
.pair-number { position: absolute; right: .5rem; top: .5rem; display: grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; font-size: .75rem; font-weight: 700; }
.pair-number.filled { color: #fff; background: #51779b; }
.selected .pair-number { background: #165ca7; color: #fff; }
.pair-caption { display: flex; justify-content: space-between; align-items: center; gap: .5rem; min-height: 44px; margin-top: .5rem; color: #64748b; font-size: .8rem; }
.reset-pairs { padding: .4rem; color: #165ca7; }
.reset-pairs:disabled { opacity: .4; }
@media (max-width: 480px) { .pair-grid { gap: .5rem; } .pair-tile { padding: .6rem 2rem .6rem .5rem; font-size: .9rem; } .pair-number { right: .3rem; } }
</style>
