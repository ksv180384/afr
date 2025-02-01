<script setup>
import { Head } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrSongCard from '@/App/Components/Song/AfrSongCard.vue';
import AfrEmptyDataPage from "@/App/Components/AfrEmptyDataPage.vue";
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  searchText: { type: String, default: '' },
  songsSearch: { type: Array, default: null },
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
      <title>Поиск песни: {{ searchText }}</title>
      <meta name="description" :content="`Поиск песни: ${searchText}`" />
      <meta property="og:title" :content="`Поиск песни: ${searchText}`" />
      <meta property="og:description" :content="`Поиск песни: ${searchText}`" />
    </Head>

    <div class="flex flex-col bg-sky-50 py-2 min-h-full">
      <template v-if="songsSearch">
        <div class="flex justify-end">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'song.search'"
          />
        </div>

        <afr-song-card :song="song" v-for="song in songsSearch" :key="song.id" />

        <div class="flex justify-end">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'song.search'"
          />
        </div>
      </template>
      <template v-else>
        <afr-empty-data-page text="Подходящих песен не найдено"/>
      </template>
    </div>
  </default-layout>
</template>

<style scoped>

</style>
