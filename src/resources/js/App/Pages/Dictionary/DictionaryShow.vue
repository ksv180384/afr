<script setup>
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrWordCard from '@/App/Components/Words/AfrWordCard.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  word: { type: Object, default: {} },
});

const title = `${props.word.word} - ${props.word.translation}`;
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>{{ title }}</title>
      <meta name="description" :content="`${word.word} - ${word.translation} (${word.transcription}) перевод, транскрипция, примеры`" />
      <meta property="og:title" :content="word" />
      <meta property="og:description" :content="`${word.word} - ${word.translation} (${word.transcription}) перевод, транскрипция, примеры`" />
    </Head>

    <div class="flex flex-col min-h-full justify-center p-4">
      <afr-word-card :word="word"/>
    </div>

  </mini-layout>
</template>

<style scoped>
:deep(.word-examples p){
  @apply py-2;
}
</style>
