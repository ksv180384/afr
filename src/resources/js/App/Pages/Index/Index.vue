<script setup>
import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrPostItem from '@/App/Components/Post/AfrPostItem.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  posts: { type: Array, default: [] },
  paginate: { type: Object, default: {} },
});

const jsonLd = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  'name': 'ApprendreFr',
  'url': 'https://apprendrefr.ru',
  'description': 'Изучайте французский язык онлайн: грамматика, уроки, тексты песен с переводом и русской транскрипцией, словарь с примерами.',
  'inLanguage': 'ru',
};
</script>

<template>
<default-layout
  :auth-user="authUser"
  :words="words"
  :proverb="proverb"
>
  <seo-head
    title="Французский язык — изучение, переводы, транскрипция"
    description="Изучайте французский язык онлайн: грамматика, уроки, тексты песен с переводом и русской транскрипцией, словарь с примерами."
    :json-ld="jsonLd"
  />

  <div class="flex flex-col gap-1">
    <template v-for="post in posts">
      <afr-post-item
        :current-user-id="authUser?.id"
        :post="post"
      />
    </template>
  </div>
</default-layout>
</template>

<style scoped>

</style>
