<script setup>
import { ref, watch, watchEffect } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@element-plus/icons-vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  songs: { type: Array, default: null },
  pagination: { type: Object, default: null },
  filters: { type: Object, default: () => ({}) },
  countSongs: { type: Number, default: 0 },
});

const search = ref(props.filters.text ?? '');
const songSortOptions = ['name', 'created_desc', 'created_asc'];
const songSortOrder = ref(
  songSortOptions.includes(props.filters.sort) ? props.filters.sort : 'name',
);
let searchTimeoutId = null;

const loadSongs = () => {
  router.get(
    route('admin.songs'),
    {
      text: search.value || undefined,
      sort: songSortOrder.value === 'name' ? undefined : songSortOrder.value,
      page: 1,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

watch(search, () => {
  if (searchTimeoutId) {
    clearTimeout(searchTimeoutId);
  }

  searchTimeoutId = setTimeout(loadSongs, 300);
});

watch(songSortOrder, loadSongs);

watchEffect(() => {
  if (!songSortOptions.includes(songSortOrder.value)) {
    songSortOrder.value = 'name';
  }
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

    <div class="grid grid-cols-[minmax(0,1fr)_160px] items-center gap-2 px-3 pt-2 sm:grid-cols-[minmax(0,1fr)_190px] lg:grid-cols-[auto_minmax(160px,1fr)_190px_auto]">
      <div class="col-start-1 row-start-1 flex items-center gap-2 whitespace-nowrap">
        <Link :href="route('admin.song.create')">
          <el-button plain type="success">
            <span class="hidden sm:inline">Добавить песню</span>
            <span class="sm:hidden">Добавить</span>
          </el-button>
        </Link>

        <span title="Всего песен">Всего: {{ countSongs }}</span>
      </div>

      <div class="col-start-1 row-start-2 min-w-0 lg:col-start-2 lg:row-start-1">
        <el-input
          v-model="search"
          clearable
          :prefix-icon="Search"
          placeholder="Поиск по ID, исполнителю или названию песни"
        />
      </div>

      <el-select
        v-model="songSortOrder"
        aria-label="Сортировка песен"
        class="col-start-2 row-start-2 w-[160px] sm:w-[190px] lg:col-start-3 lg:row-start-1"
      >
        <el-option label="По исполнителю" value="name" />
        <el-option label="Сначала новые" value="created_desc" />
        <el-option label="Сначала старые" value="created_asc" />
      </el-select>

      <div class="col-start-2 row-start-1 flex min-h-[26px] justify-end lg:col-start-4">
        <pagination
          compact
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
          :title="`${song.artist_name} - ${song.title}`"
          class="flex flex-row items-center px-2 py-2 border-b border-gray-300 hover:bg-blue-100"
        >
          <span class="w-[50px] shrink-0 text-xs font-semibold">#{{ song.id }}</span>
          <span class="min-w-0 flex-1 truncate">
            <span class="font-bold">{{ song.artist_name }}</span> - {{ song.title }}
          </span>
          <time
            class="shrink-0 pl-4 text-xs text-gray-500"
            title="Дата добавления"
          >
            {{ song.created_at }}
          </time>
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

