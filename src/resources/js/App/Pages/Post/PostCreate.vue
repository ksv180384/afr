<script setup>
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import PostForm from '@/App/Pages/Post/Components/PostForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  statuses: { type: Array, default: [] },
  errors: { type: Object, default: {} },
});


const submit = async (form) => {
  form.post(route('post.store'), {
    onFinish: (res) => {
      // form.reset('password')
    },
  });
};
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>Добавить пост</title>
      <meta name="description" content="Добавить пост" />
      <meta property="og:title" content="Добавить пост" />
      <meta property="og:description" content="Добавить пост" />
    </Head>

    <post-form
      :auth-user="authUser"
      :statuses="statuses"
      :errors="errors"
      @submit="submit"
    />

  </mini-layout>
</template>

<style scoped>

</style>
