<script setup>
import { Head } from '@inertiajs/vue3';

import MyPostsLayout from '@/App/Layouts/MyPostsLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';
import AfrUserPostItem from '@/App/Pages/UserPost/AfrUserPostItem.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  posts: { type: Array, default: [] },
  pagination: { type: Object, default: {} },
});

</script>

<template>
  <my-posts-layout
    :auth-user="authUser"
  >
    <Head>
      <title>Мои посты</title>
      <meta name="description" content="Мои посты" />
      <meta property="og:title" content="Мои посты" />
      <meta property="og:description" content="Мои посты" />
    </Head>

    <div class="flex flex-col min-h-full">
      <template v-if="posts.length">
        <div class="flex justify-end">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'user.posts'"
          />
        </div>

        <div class="flex flex-col gap-6 p1-3">
          <template v-for="post in posts">
            <afr-user-post-item
              :current-user-id="authUser?.id"
              :post="post"
            />
          </template>
        </div>

        <div class="flex justify-end">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'user.posts'"
          />
        </div>
      </template>
      <template v-else>
        <div class="flex justify-center items-center min-h-full text-3xl text-gray-400">
          Нет постов
        </div>
      </template>
    </div>
  </my-posts-layout>
</template>

<style scoped>

</style>
