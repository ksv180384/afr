<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@element-plus/icons-vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  songs: { type: Array, default: null },
  pagination: { type: Object, default: null },
  filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.text ?? '');
let searchTimeoutId = null;

watch(search, () => {
  if (searchTimeoutId) {
    clearTimeout(searchTimeoutId);
  }

  searchTimeoutId = setTimeout(() => {
    router.get(
      route('admin.songs'),
      {
        text: search.value || undefined,
        page: 1,
      },
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
      },
    );
  }, 300);
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

    <div class="grid grid-cols-1 gap-2 px-3 pt-2 md:grid-cols-[180px_minmax(260px,416px)_280px] md:items-center">
      <Link :href="route('admin.song.create')" class="justify-self-start">
        <el-button plain type="success">
          Добавить песню
        </el-button>
      </Link>

      <div class="w-full md:justify-self-center">
        <el-input
          v-model="search"
          clearable
          :prefix-icon="Search"
          placeholder="Поиск по исполнителю или названию песни"
        />
      </div>

      <div class="flex min-h-[26px] w-full justify-end md:w-[280px] md:justify-self-end">
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

