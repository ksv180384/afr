<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  lessons: { type: Array, default: null },
});
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Уроки"
  >
    <Head>
      <title>Уроки</title>
      <meta name="description" content="Уроки" />
      <meta property="og:title" content="Уроки" />
      <meta property="og:description" content="Уроки" />
    </Head>

    <div class="flex flex-col">
      <div class="flex justify-end px-4 py-2">
        <Link :href="route('admin.lessons.create')">
          <el-button type="success" plain>
            Добавить урок
          </el-button>
        </Link>
      </div>
      <div class="flex flex-col">
        <template v-for="lesson in lessons">
          <Link class="link" :href="route('admin.lessons.edit', { 'id': lesson.id })">
            {{ lesson.title }}
          </Link>
        </template>
      </div>
    </div>

  </admin-layout>
</template>

<style scoped>
.link{
  @apply px-3 py-1.5 hover:bg-blue-100;
}
</style>
