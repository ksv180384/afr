<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import PostStatusForm from '@/Admin/Pages/PostStatus/Conponents/PostStatusForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  postStatus: { type: Object, default: {} },
  errors: { type: Object, default: {} },
});

const submit = (form) => {
  form.post(route('admin.post-status.update', { id: props.postStatus.id }), {
    onFinish: (res) => {
    },
  });
}

const deleteItem = (form) => {
  form.post(route('admin.post-status.delete', { id: props.postStatus.id }), {
    onFinish: (res) => {
    },
  });
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    :title="`Редактирование статуса: ${postStatus.title}`"
  >
    <Head>
      <title>Статус: {{ postStatus.title }} - Панель администратора</title>
      <meta name="description" :content="`>Статус: ${postStatus.title} - Панель администратора`" />
      <meta property="og:title" :content="`>Статус: ${postStatus.title} - Панель администратора`" />
      <meta property="og:description" :content="`>Статус: ${postStatus.title} - Панель администратора`" />
    </Head>

    <post-status-form
      :post-status="postStatus"
      :errors="errors"
      @submit="submit"
      @delete="deleteItem"
    />
  </admin-layout>
</template>

<style scoped>

</style>
