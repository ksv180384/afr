<script setup>

import AfrPlayerWord from '@/App/Components/AfrPlayerWord.vue';

const props = defineProps({
  word: { type: Object, required: true },
});
</script>

<template>
  <div class="p-4 rounded drop-shadow-lg overflow-hidden bg-blue-100">
    <div class="flex flex-col lg:flex-row text-xl pb-4 lg:pb-0">
      <div class="flex flex-row items-center flex-1 px-4 py-4">
        <afr-player-word :word="word.word" class="me-3"/> {{ word.word }}
      </div>
      <div class="flex-1 px-4 py-4">
        {{ word.transcription }}
      </div>
      <div class="flex-1 px-4 py-4">
        {{ word.translation }}
      </div>
    </div>
    <div class="text-xl px-4 py-4 border-t border-gray-400 lg:border-0">
      <div v-html="word.example" class="word-examples leading-8"></div>
    </div>
  </div>
</template>

<style scoped>

</style>
