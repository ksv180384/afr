<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import AfrPostCommentItem from '@/Admin/Pages/PostComment/Components/AfrPostCommentItem.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  comments: { type: Array, default: [] },
  pagination: { type: Object, default: {} },
});

const commentsList = ref(props.comments);
const isUpdatingToggleShow = ref(0);

const toggleShow = async (id) => {
  try {
    isUpdatingToggleShow.value = id;

    const res = await api.postComment.toggleShow(id);

    commentsList.value = commentsList.value.map(item => {
      if(item.id === id){
        return res.comment;
      }
      return item;
    });
  }
  catch (e) {
    console.error(e);
  }
  finally {
    isUpdatingToggleShow.value = 0;
  }
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Комментарии постов"
  >
    <Head>
      <title>Комментарии постов - Панель администратора</title>
      <meta name="description" content="Комментарии постов - Добавить статус поста" />
      <meta property="og:title" content="Комментарии постов - Добавить статус поста" />
      <meta property="og:description" content="Комментарии постов - Добавить статус поста" />
    </Head>

    <div class="flex flex-col">
      <div class="flex justify-end">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.posts-comments'"
        />
      </div>
      <template v-for="comment in commentsList" :key="comment.id">
        <afr-post-comment-item
          :comment="comment"
          :is-loaded-toggle-show="comment.id === isUpdatingToggleShow"
          @toggle-show="toggleShow"
        />
      </template>
      <div class="flex justify-end">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.posts-comments'"
        />
      </div>
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
