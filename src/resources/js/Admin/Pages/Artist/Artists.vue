<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@element-plus/icons-vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  artists: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.text ?? '');
let searchTimeoutId = null;

watch(search, () => {
  clearTimeout(searchTimeoutId);
  searchTimeoutId = setTimeout(() => {
    router.get(route('admin.artists'), { text: search.value || undefined, page: 1 }, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  }, 300);
});
</script>

<template>
  <admin-layout :auth-user="authUser" title="Исполнители">
    <Head>
      <title>Исполнители</title>
    </Head>

    <div class="flex items-center gap-2 px-3 py-3">
      <el-input v-model="search" clearable :prefix-icon="Search" placeholder="Поиск исполнителя" />
      <pagination
        v-if="pagination.last_page > 1"
        compact
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.artists'"
      />
    </div>

    <div class="grid gap-2 p-3 sm:grid-cols-2 xl:grid-cols-3">
      <Link
        v-for="artist in artists"
        :key="artist.id"
        :href="route('admin.artists.edit', { id: artist.id })"
        class="flex min-w-0 gap-3 rounded border border-blue-200 bg-white p-3 transition hover:bg-blue-100"
      >
        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded bg-slate-100 text-xs text-slate-400">
          <img v-if="artist.image_url" :src="artist.image_url" :alt="artist.name" class="h-full w-full object-cover">
          <span v-else>Нет фото</span>
        </div>
        <div class="min-w-0">
          <div class="truncate font-semibold">{{ artist.name }}</div>
          <div class="text-sm text-slate-600">Песен: {{ artist.songs_count }}</div>
          <div class="text-sm text-slate-600">Составов: {{ artist.lineups_count }}</div>
          <div v-if="artist.description" class="mt-1 line-clamp-2 text-sm text-slate-500">{{ artist.description }}</div>
        </div>
      </Link>
    </div>

    <div class="flex justify-end p-3">
      <pagination
        v-if="pagination.last_page > 1"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.artists'"
      />
    </div>
  </admin-layout>
</template>
