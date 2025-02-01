<script setup>
import { Head } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrPostItem from '@/App/Components/Post/AfrPostItem.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  posts: { type: Array, default: [] },
  paginate: { type: Object, default: {} },
});

</script>

<template>
<default-layout
  :auth-user="authUser"
  :words="words"
  :proverb="proverb"
>
  <Head>
    <title>Французский язык - изучение</title>
    <meta name="description" content="Французский язык - изучение, переводы, русская транскрипция" />
    <meta property="og:title" content="Французский язык - изучение, переводы, русская транскрипция" />
    <meta property="og:description" content="Французский язык - изучение, переводы, русская транскрипция" />
  </Head>

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
