<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  songs: { type: Array, default: null },
  pagination: { type: Object, default: null },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Тексты песен"
  >
    <Head>
      <title>Ненайденные пестни - Панель администратора</title>
      <meta name="description" content="Тексты песен" />
      <meta property="og:title" content="Тексты песен" />
      <meta property="og:description" content="Тексты песен" />
    </Head>

      <div class="flex justify-end px-2 py-1">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.songs-undiscovered'"
        />
      </div>


    <div class="flex-1 flex flex-col px-1">
      <template v-for="song in songs" :key="song.id">

        <div class="flex flex-row items-center py-2 px-4 border-b hover:bg-blue-100">
          <div class="flex flex-1 flex-col">
            <div class="flex flex-row items-center">
              <span class="font-semibold w-[30px] text-xs" :class="[song.song_id ? 'text-green-600' : 'text-red-600']">#{{ song.id }}</span>
              <span class="font-bold">{{ song.artist }}</span> - {{ song.title }}
            </div>
            <div>
              {{ song.title_file }}
            </div>
          </div>

          <div :title="song.created_at">
            {{ song.created }}
          </div>
        </div>

      </template>
    </div>

    <div class="flex justify-end px-2 py-1">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.songs-undiscovered'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>

