<script setup>
import { Head } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrWordCard from '@/App/Components/Words/AfrWordCard.vue';
import AfrEmptyDataPage from '@/App/Components/AfrEmptyDataPage.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  searchText: { type: String, default: '' },
  wordSearch: { type: Array, default: null },
  pagination: { type: Object, default: {} },
});
</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <Head>
      <title>Слово: {{ searchText }}</title>
      <meta name="description" :content="`Слово: ${searchText}`" />
      <meta property="og:title" :content="`Слово: ${searchText}`" />
      <meta property="og:description" :content="`Слово: ${searchText}`" />
    </Head>

    <div class="flex flex-col gap-4 bg-sky-50 px-4 py-2 min-h-full">
      <template v-if="wordSearch">
        <div class="flex justify-end">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'word.search'"
          />
        </div>

        <afr-word-card :word="word" v-for="word in wordSearch" :key="word.id" />

        <div class="flex justify-end">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'word.search'"
          />
        </div>
      </template>
      <template v-else>
        <afr-empty-data-page text="Подходящих слов не найдено"/>
      </template>
    </div>
  </default-layout>
</template>

<style scoped>

</style>
