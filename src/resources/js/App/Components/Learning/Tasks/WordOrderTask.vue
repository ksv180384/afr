<script setup>
import { computed } from 'vue';
import { presentationOrder } from '@/App/Learning/presentationOrder.js';
import FrenchText from '../FrenchText.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
const props = defineProps({ task: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean, uid: { type: String, required: true } });
const tokens = computed(() => presentationOrder(props.task.tokens, props.uid + ':tokens'));
const emit = defineEmits(['update:modelValue']);
const token = (id) => props.task.tokens.find((item) => item.id === id);
const update = (ids) => emit('update:modelValue', { token_ids: ids });
const remove = (index) => update(props.modelValue.token_ids.filter((_, position) => position !== index));
const move = (index, direction) => {
  const ids = [...props.modelValue.token_ids];
  [ids[index], ids[index + direction]] = [ids[index + direction], ids[index]];
  update(ids);
};
</script>
<template>
  <div>
    <p class="word-instructions">Нажимайте на карточки, чтобы собрать фразу. Порядок можно менять стрелками.</p>
    <ol class="assembled" aria-label="Собранная фраза">
      <li v-for="(id, index) in modelValue.token_ids" :key="id" class="assembled-token">
        <french-text :text="token(id).text" :transcription-ru="token(id).transcription_ru" compact />
        <div class="token-actions">
          <afr-button size="small" :disabled="disabled || index === 0" :aria-label="'Переместить карточку ' + (index + 1) + ' левее'" @click="move(index, -1)">←</afr-button>
          <afr-button size="small" :disabled="disabled || index === modelValue.token_ids.length - 1" :aria-label="'Переместить карточку ' + (index + 1) + ' правее'" @click="move(index, 1)">→</afr-button>
          <afr-button size="small" :disabled="disabled" :aria-label="'Убрать карточку ' + (index + 1)" @click="remove(index)">×</afr-button>
        </div>
      </li>
      <li v-if="!modelValue.token_ids.length" class="word-placeholder">Здесь появится ваша фраза</li>
    </ol>
    <div class="token-bank" role="group" aria-label="Доступные карточки">
      <afr-button v-for="item in tokens.filter((item) => !modelValue.token_ids.includes(item.id))" :key="item.id" class="bank-token" :disabled="disabled" @click="update([...modelValue.token_ids, item.id])">
        <french-text :text="item.text" :transcription-ru="item.transcription_ru" compact />
      </afr-button>
    </div>
    <afr-button size="small" link class="clear-order" :disabled="disabled || !modelValue.token_ids.length" @click="update([])">Очистить фразу</afr-button>
  </div>
</template>
<style scoped>
.word-instructions { color: #475569; margin-bottom: .75rem; }
.assembled { min-height: 64px; border: 1px dashed #cbd5e1; padding: .5rem; border-radius: .25rem; display: flex; flex-wrap: wrap; gap: .5rem; }
.assembled-token { border: 1px solid #93c5fd; border-radius: .25rem; background: #eff6ff; padding: .5rem; min-width: 0; max-width: 100%; }
.token-actions { display: flex; gap: .25rem; margin-top: .25rem; }
.token-actions :deep(button) { min-width: 28px; min-height: 26px; }
.token-bank { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.bank-token { padding: .35rem .5rem; max-width: 100%; }
.clear-order { min-height: 28px; text-decoration: underline; color: #155e91; }
.word-placeholder { color: #64748b; }
button:disabled { opacity: .5; cursor: default; }
</style>
