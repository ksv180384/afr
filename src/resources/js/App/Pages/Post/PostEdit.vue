<script setup>
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import PostForm from '@/App/Pages/Post/Components/PostForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  post: { type: Object, default: null },
  statuses: { type: Array, default: [] },
  errors: { type: Object, default: {} },
});

const submit = async (form) => {
  form.post(route('post.update', { id: props.post.id }), {
    onFinish: (res) => {
    },
  });
};
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>Редактировать пост</title>
      <meta name="description" content="Редактировать пост" />
      <meta property="og:title" content="Редактировать пост" />
      <meta property="og:description" content="Редактировать пост" />
    </Head>

    <post-form
      :auth-user="authUser"
      :post="post"
      :statuses="statuses"
      :errors="errors"
      @submit="submit"
    />

  </mini-layout>
</template>

<style scoped>

</style>
