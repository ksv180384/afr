<script setup>
import { Head } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrUserCard from '@/App/Pages/User/Components/AfrUserCard.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  users: { type: Array, default: [] },
  pagination: { type: Object, default: {} },
});

</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <Head>
      <title>Список пользователей</title>
      <meta name="description" content="Список пользователей" />
      <meta property="og:title" content="Список пользователей" />
      <meta property="og:description" content="Список пользователей" />
    </Head>

    <div class="users-wrapper">
      <div class="pagination-container">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'users'"
        />
      </div>

      <div class="users-container">
        <template v-for="user in users">
          <afr-user-card :user="user"/>
        </template>
      </div>

      <div class="pagination-container">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'users'"
        />
      </div>
    </div>
  </default-layout>
</template>

<style scoped>
.users-wrapper{
  @apply bg-sky-50;
}

.pagination-container{
  @apply flex justify-end px-3;
}

.users-container{
  @apply py-4 px-2 grid grid-cols-[repeat(1,1fr)] md:grid-cols-[repeat(3,1fr)] xl:grid-cols-[repeat(4,1fr)] gap-2;
}
</style>
