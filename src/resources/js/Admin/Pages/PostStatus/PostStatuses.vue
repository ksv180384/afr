<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  postStatuses: { type: Array, default: [] },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Статусы постов"
  >
    <Head>
      <title>Статусы постов - Панель администратора</title>
      <meta name="description" content="Статусы постов - Панель администратора" />
      <meta property="og:title" content="Статусы постов - Панель администратора" />
      <meta property="og:description" content="Статусы постов - Панель администратора" />
    </Head>

    <div class="flex px-3 justify-end">
      <Link :href="route('admin.post-status.create')">
        <el-button plain type="success">
          Добавить статус
        </el-button>
      </Link>
    </div>

    <div class="flex flex-col px-2">
      <template v-for="status in postStatuses" :key="status.id">
        <Link
          :href="route('admin.post-status.edit', { id: status.id })"
          :title="status.title"
          class="px-2 py-2 border-b border-gray-300"
        >
          <div class="font-semibold text-xl">
            {{ status.title }}
          </div>
          <div>
            {{ status.alias }}
          </div>
        </Link>
      </template>
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
