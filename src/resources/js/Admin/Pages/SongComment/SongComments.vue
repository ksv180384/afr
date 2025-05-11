<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api/index.js';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';
import AfrSongCommentItem from '@/Admin/Pages/SongComment/Components/AfrSongCommentItem.vue';


const props = defineProps({
  authUser: { type: Object, default: null },
  comments: { type: Array, default: null },
  pagination: { type: Object, default: null },
});

const commentsList = ref(props.comments);
const isUpdatingToggleShow = ref(0);

const toggleShow = async (id) => {
  try {
    isUpdatingToggleShow.value = id;

    const res = await api.songComment.toggleShow(id);

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
    title="Комментарии к песням"
  >
    <Head>
      <title>Комментарии к песням - Панель администратора</title>
      <meta name="description" content="Комментарии к песням" />
      <meta property="og:title" content="Комментарии к песням" />
      <meta property="og:description" content="Комментарии к песням" />
    </Head>

    <div class="flex px-3 justify-between pt-2">
      <div class="flex justify-end">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.songs-comments'"
        />
      </div>
    </div>

    <div class="flex-1 flex flex-col px-2 gap-1">
      <template v-for="comment in commentsList" :key="comment.id">
        <afr-song-comment-item
          :comment="comment"
          :is-updating="isUpdatingToggleShow === comment.id"
          @toggleShow="toggleShow"
        />
      </template>
    </div>

    <div class="flex justify-end">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.songs-comments'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>

