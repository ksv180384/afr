<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import AfrPostItem from '@/Admin/Components/Post/AfrPostItem.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  posts: { type: Array, default: [] },
  postStatuses: { type: Array, default: [] },
});

const updatedPostId = ref(0);

const changeStatus = async (postId, params) => {

  try {
    updatedPostId.value = postId;
    const res = await api.post.updateStatus(postId, params);
  }
  catch (e) {
    console.error(e);
  }
  finally {
    updatedPostId.value = 0;
  }
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Посты"
  >
    <Head>
      <title>Посты - Панель администратора</title>
      <meta name="description" content="Посты - Добавить статус поста" />
      <meta property="og:title" content="Посты - Добавить статус поста" />
      <meta property="og:description" content="Посты - Добавить статус поста" />
    </Head>

    <div class="flex flex-col gap-4 px-2">
      <template v-for="post in posts" :key="post.id">
        <afr-post-item
          :post="post"
          :post-statuses="postStatuses"
          :is-updating-status="post.id === updatedPostId"
          @change-status="changeStatus"
        />
      </template>
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
