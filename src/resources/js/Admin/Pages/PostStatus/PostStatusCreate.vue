<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import PostStatusForm from '@/Admin/Pages/PostStatus/Conponents/PostStatusForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  errors: { type: Object, default: {} },
});

const submit = (form) => {
  form.post(route('admin.post-status.store'), {
    onFinish: (res) => {
    },
  });
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавить статус поста"
  >
    <Head>
      <title>Добавить статус поста - Панель администратора</title>
      <meta name="description" content="Добавить статус поста" />
      <meta property="og:title" content="Добавить статус поста" />
      <meta property="og:description" content="Добавить статус поста" />
    </Head>

    <post-status-form
      :errors="errors"
      @submit="submit"
    />
  </admin-layout>
</template>

<style scoped>

</style>

