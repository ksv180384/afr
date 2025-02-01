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
      <title>Тексты песен - Панель администратора</title>
      <meta name="description" content="Тексты песен" />
      <meta property="og:title" content="Тексты песен" />
      <meta property="og:description" content="Тексты песен" />
    </Head>

    <div class="flex px-3 justify-between pt-2">
      <Link :href="route('admin.song.create')">
        <el-button plain type="success">
          Добавить песню
        </el-button>
      </Link>

      <div class="flex justify-end">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.songs'"
        />
      </div>
    </div>

    <div class="flex-1 flex flex-col px-2">
      <template v-for="song in songs" :key="song.id">
        <Link
          :href="route('admin.song.edit', { id: song.id })"
          :title="`${song.artist.name} - ${song.title}`"
          class="flex flex-row items-center px-2 py-2 border-b border-gray-300 hover:bg-blue-100"
        >
          <span class="font-semibold w-[50px] text-xs">#{{ song.id }}</span><span class="font-bold">{{ song.artist.name }}</span> - {{ song.title }}
        </Link>
      </template>
    </div>

    <div class="flex justify-end">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.songs'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>

