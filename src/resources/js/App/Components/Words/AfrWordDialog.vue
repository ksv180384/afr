<script setup>
import { computed } from 'vue';

import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrPlayerWord from '@/App/Components/AfrPlayerWord.vue';

const props = defineProps({
  word: { type: String },
  transcription: { type: String },
  translation: { type: String },
  example: { type: String },
  firstLang: { type: String, default: 'fr' },
});

const model = defineModel();

const wordText = computed(() => {
  return props.firstLang === 'ru' ? props.translation : props.word;
});
const translationText = computed(() => {
  return props.firstLang === 'ru' ? props.word : props.translation;
});
</script>

<template>
  <afr-dialog
    v-model="model"
  >
    <template #header>
      <div class="uppercase font-bold">{{ word }}</div>
    </template>

    <div class="max-w-[800px]">
      <div class="flex flex-row">
        <div class="afr-word-modal-item">
          <afr-player-word :word="wordText" class="me-3"/> {{ wordText }}
        </div>
        <div class="afr-word-modal-item">{{ transcription }}</div>
        <div class="afr-word-modal-item Pervod">{{ translationText }}</div>
      </div>
      <div class="p-4 pt-0">
        <div v-html="example" class="leading-8"></div>
      </div>
    </div>
  </afr-dialog>
</template>

<style scoped>
.afr-word-modal-item{
  @apply flex-grow flex justify-center py-6 px-2 w-1/3;
}
</style>
