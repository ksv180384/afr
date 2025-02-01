<script setup>
import { Head } from '@inertiajs/vue3';

import PostForm from '@/App/Pages/Post/Components/PostForm.vue';
import MyPostsLayout from '@/App/Layouts/MyPostsLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  post: { type: Object, default: null },
  statuses: { type: Array, default: [] },
  previousUrl: { type: String, default: null },
  errors: { type: Object, default: {} },
});

const submit = async (form) => {
  form.post(route('user.post.update', { id: props.post.id, redirect: props.previousUrl }), {
    onFinish: (res) => {
    },
  });
};
</script>

<template>
  <my-posts-layout
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

  </my-posts-layout>
</template>

<style scoped>

</style>
