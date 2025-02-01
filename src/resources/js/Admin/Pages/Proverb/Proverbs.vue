<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from "@/App/Components/Pagination/Pagination.vue";

const props = defineProps({
  authUser: { type: Object, default: null },
  proverbs: { type: Array, default: null },
  pagination: { type: Object, default: null },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Пословицы"
  >
    <Head>
      <title>Пословицы - Панель администратора</title>
      <meta name="description" content="Пословицы" />
      <meta property="og:title" content="Пословицы" />
      <meta property="og:description" content="Пословицы" />
    </Head>

    <div class="flex px-3 justify-between pt-2">
      <Link :href="route('admin.proverb.create')">
        <el-button plain type="success">
          Добавить пословицу
        </el-button>
      </Link>

      <div class="flex justify-end">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.proverbs'"
        />
      </div>
    </div>

    <div class="flex-1 flex flex-col px-2">
      <template v-for="proverb in proverbs" :key="proverb.id">
        <Link
          :href="route('admin.proverb.edit', { id: proverb.id })"
          :title="proverb.text"
          class="px-2 py-2 border-b border-gray-300 hover:bg-blue-100"
        >
          <span class="font-semibold me-4 text-xs">#{{ proverb.id }}</span> {{ proverb.text }}
        </Link>
      </template>
    </div>

    <div class="flex justify-end">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.proverbs'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
