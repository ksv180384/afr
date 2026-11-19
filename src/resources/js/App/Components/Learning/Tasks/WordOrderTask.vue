<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { presentationOrder } from '@/App/Learning/presentationOrder.js';
import FrenchText from '../FrenchText.vue';
const props = defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, showTranscription: { type: Boolean, default: true }, uid: { type: String, required: true } });
const emit = defineEmits(['update:modelValue']);
const tokens = computed(() => presentationOrder(props.task.tokens, props.uid + ':tokens'));
const token = id => props.task.tokens.find(item => item.id === id);
const assembled = ref(null);
const bank = ref(null);
const bankHeight = ref(100);
const drag = ref(null);
const announcement = ref('');
let observer;
let suppressClick = false;
let clickTimer;
let scrollFrame;
const update = ids => { if (!props.disabled) emit('update:modelValue', { token_ids: ids }); };
const toggle = id => {
  if (suppressClick || props.disabled) return;
  const ids = props.modelValue.token_ids;
  update(ids.includes(id) ? ids.filter(value => value !== id) : [...ids, id]);
};
const move = (id, direction) => {
  const ids = [...props.modelValue.token_ids];
  const index = ids.indexOf(id);
  if (index < 0 || index + direction < 0 || index + direction >= ids.length) return;
  [ids[index], ids[index + direction]] = [ids[index + direction], ids[index]];
  update(ids);
  announcement.value = `${token(id).text}: позиция ${index + direction + 1}`;
};
const inside = (element, x, y) => {
  const rect = element?.getBoundingClientRect();
  return rect && x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
};
const locateDrop = (x, y, id) => {
  if (inside(bank.value, x, y)) return { zone: 'bank', index: 0 };
  if (!inside(assembled.value, x, y)) return { zone: null, index: 0 };
  const items = [...assembled.value.querySelectorAll('[data-token]')].filter(item => item.dataset.token !== id);
  const index = items.findIndex(item => {
    const rect = item.getBoundingClientRect();
    return y < rect.top || (y <= rect.bottom && x < rect.left + rect.width / 2);
  });
  return { zone: 'answer', index: index < 0 ? items.length : index };
};
const scrollWhileDragging = () => {
  const state = drag.value;
  if (!state?.active) return;
  const pane = state.target.closest?.('.question-pane');
  if (pane) {
    const rect = pane.getBoundingClientRect();
    const distance = state.y < rect.top + 48 ? -10 : state.y > rect.bottom - 48 ? 10 : 0;
    if (distance) {
      pane.scrollTop += distance;
      Object.assign(state, locateDrop(state.x, state.y, state.id));
    }
  }
  scrollFrame = requestAnimationFrame(scrollWhileDragging);
};
const beginDrag = (event, id) => {
  if (props.disabled || !event.isPrimary || event.button !== 0) return;
  event.currentTarget.setPointerCapture(event.pointerId);
  drag.value = { id, pointerId: event.pointerId, target: event.currentTarget, startX: event.clientX, startY: event.clientY, x: event.clientX, y: event.clientY, active: false, zone: null, index: 0 };
};
const moveDrag = event => {
  const state = drag.value;
  if (!state || state.pointerId !== event.pointerId) return;
  state.x = event.clientX;
  state.y = event.clientY;
  if (!state.active && Math.hypot(state.x - state.startX, state.y - state.startY) > 6) {
    state.active = true;
    scrollFrame = requestAnimationFrame(scrollWhileDragging);
  }
  if (!state.active) return;
  event.preventDefault();
  Object.assign(state, locateDrop(state.x, state.y, state.id));
};
const cancelDrag = () => {
  if (scrollFrame != null) cancelAnimationFrame(scrollFrame);
  scrollFrame = null;
  const state = drag.value;
  drag.value = null;
  if (state?.target.hasPointerCapture?.(state.pointerId)) state.target.releasePointerCapture(state.pointerId);
};
const finishDrag = event => {
  const state = drag.value;
  if (!state || state.pointerId !== event.pointerId) return;
  if (state.active) {
    suppressClick = true;
    clearTimeout(clickTimer);
    clickTimer = setTimeout(() => { suppressClick = false; }, 0);
    const drop = locateDrop(event.clientX, event.clientY, state.id);
    const ids = props.modelValue.token_ids.filter(id => id !== state.id);
    if (drop.zone === 'answer') { ids.splice(drop.index, 0, state.id); update(ids); }
    else if (drop.zone === 'bank') update(ids);
  }
  cancelDrag();
};
const insertionId = computed(() => {
  if (!drag.value?.active || drag.value.zone !== 'answer') return null;
  return props.modelValue.token_ids.filter(id => id !== drag.value.id)[drag.value.index] ?? 'end';
});
watch(() => props.disabled, disabled => { if (disabled) cancelDrag(); });
onMounted(() => {
  observer = new ResizeObserver(() => { bankHeight.value = bank.value?.offsetHeight ?? 100; });
  observer.observe(bank.value);
});
onBeforeUnmount(() => { cancelDrag(); observer?.disconnect(); clearTimeout(clickTimer); });
</script>
<template>
  <div class="word-order-task" @keydown.esc="cancelDrag">
    <p class="word-instructions" :id="uid + '-instructions'">Нажимайте или перетаскивайте слова. В ответе их можно менять местами.</p>
    <p class="sr-only" :id="uid + '-keyboard'">Чтобы поменять порядок с клавиатуры, используйте Alt и стрелки влево или вправо. Нажмите на слово в ответе, чтобы убрать его.</p>
    <ol ref="assembled" class="assembled" :class="{ 'drop-active': drag?.active && drag.zone === 'answer', 'drop-at-end': insertionId === 'end' }" :style="{ minHeight: Math.max(110, bankHeight + 16) + 'px' }" aria-label="Собранная фраза">
      <li v-for="id in modelValue.token_ids" :key="id" :data-token="id" class="assembled-token" :class="{ 'insert-before': insertionId === id, dragging: drag?.active && drag.id === id }">
        <button type="button" class="word-tile" :disabled="disabled" :aria-label="token(id).text + '. Убрать из ответа'" :aria-describedby="uid + '-keyboard'"
          @click="toggle(id)" @keydown.alt.left.prevent="move(id, -1)" @keydown.alt.right.prevent="move(id, 1)"
          @pointerdown="beginDrag($event, id)" @pointermove="moveDrag" @pointerup="finishDrag" @pointercancel="cancelDrag" @lostpointercapture="cancelDrag" @dragstart.prevent>
          <french-text :show-transcription="showTranscription" :text="token(id).text" :transcription-ru="token(id).transcription_ru" compact />
        </button>
      </li>
      <li v-if="!modelValue.token_ids.length" class="word-placeholder">Перенесите слова сюда</li>
    </ol>
    <div ref="bank" class="token-bank" :class="{ 'drop-active': drag?.active && drag.zone === 'bank' }" role="group" aria-label="Доступные карточки">
      <div v-for="item in tokens" :key="item.id" class="bank-slot" :class="{ used: modelValue.token_ids.includes(item.id) }">
        <button type="button" class="word-tile" :disabled="disabled || modelValue.token_ids.includes(item.id)" :tabindex="modelValue.token_ids.includes(item.id) ? -1 : 0" :aria-hidden="modelValue.token_ids.includes(item.id) || undefined" :aria-label="item.text + '. Добавить в ответ'"
          @click="toggle(item.id)" @pointerdown="beginDrag($event, item.id)" @pointermove="moveDrag" @pointerup="finishDrag" @pointercancel="cancelDrag" @lostpointercapture="cancelDrag" @dragstart.prevent>
          <french-text :show-transcription="showTranscription" :text="item.text" :transcription-ru="item.transcription_ru" compact />
        </button>
      </div>
    </div>
    <button type="button" class="clear-order" :disabled="disabled || !modelValue.token_ids.length" @click="update([])">Очистить фразу</button>
    <span class="sr-only" role="status">{{ announcement }}</span>
    <Teleport to="body">
      <div v-if="drag?.active" class="word-drag-ghost" :style="{ left: drag.x + 'px', top: drag.y + 'px' }" aria-hidden="true">
        <french-text :show-transcription="showTranscription" :text="token(drag.id).text" :transcription-ru="token(drag.id).transcription_ru" compact />
      </div>
    </Teleport>
  </div>
</template>
<style scoped>
.word-instructions { color: #64748b; font-size: .9rem; margin-bottom: .9rem; }
.assembled { position: relative; border: 2px dashed #cad9e7; padding: .65rem; border-radius: .85rem; display: flex; flex-wrap: wrap; align-content: flex-start; align-items: flex-start; gap: .5rem; background: #f8fafc; }
.assembled-token { position: relative; min-width: 0; max-width: 100%; }
.word-tile { display: flex; text-align: left; border: 1px solid #c6d5e3; border-radius: .65rem; background: #fff; padding: .55rem .8rem; min-height: 46px; max-width: 100%; box-shadow: 0 3px 0 #dce5ed; cursor: grab; touch-action: none; user-select: none; -webkit-user-select: none; }
.word-tile :deep(*) { pointer-events: none; }
.word-tile:not(:disabled):hover { border-color: #6898c7; background: #f3f8ff; }
.word-tile:active { cursor: grabbing; }
.assembled .word-tile { border-color: #a9c6e2; background: #eef5ff; }
.token-bank { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .5rem; margin-top: 1rem; border-radius: .75rem; }
.bank-slot { max-width: 100%; border-radius: .65rem; background: #e7edf3; }
.used .word-tile { visibility: hidden; }
.clear-order { min-height: 44px; color: #165ca7; font-size: .85rem; margin-top: .35rem; }
.clear-order:disabled { opacity: .4; }
.word-placeholder { color: #8393a5; font-size: .95rem; margin: auto; align-self: center; padding: 1.5rem .5rem; }
.drop-active { outline: 2px solid #7ba9d8; outline-offset: 3px; }
.insert-before::before { content: ''; position: absolute; width: 3px; background: #165ca7; border-radius: 2px; inset: 0 auto 0 -5px; }
.drop-at-end::after { content: ''; width: 3px; height: 48px; background: #165ca7; border-radius: 2px; }
.dragging { opacity: .3; }
.word-drag-ghost { position: fixed; z-index: 100; pointer-events: none; transform: translate(-50%, -65%); padding: .55rem .8rem; max-width: 80vw; border-radius: .65rem; border: 2px solid #165ca7; background: #fff; color: #18344c; box-shadow: 0 8px 24px #17365430; }
button:disabled { cursor: default; }
</style>
