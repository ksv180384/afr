<script setup>
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import AfrWordCard from '@/App/Components/Words/AfrWordCard.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  word: { type: Object, default: {} },
});

const title = `${props.word.word} - ${props.word.translation}`;
const description = `${props.word.word} - ${props.word.translation} (${props.word.transcription}) перевод, транскрипция, примеры`;

const jsonLd = {
  '@context': 'https://schema.org',
  '@type': 'DefinedTerm',
  'name': props.word.word,
  'description': props.word.translation,
  'inDefinedTermSet': 'Французско-русский словарь',
};
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <seo-head
      :title="title"
      :description="description"
      :json-ld="jsonLd"
    />

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
