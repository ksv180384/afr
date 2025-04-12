<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import AfrUserCard from '@/Admin/Pages/User/Components/AfrUserCard.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';
import api from "@/Admin/Services/Api/index.js";

const props = defineProps({
  authUser: { type: Object, default: null },
  users: { type: Array, default: [] },
  pagination: { type: Object, default: {} },
});

const usersList = ref(props.users);
const updatingToggleBanned = ref(0);

const ban = async (user) => {
  try {
    updatingToggleBanned.value = user.id;

    const res = await api.user.ban({ id: user.id, ban: !user.is_ban });

    usersList.value = usersList.value.map(item => {
      return item.id === user.id ? res.user : item;
    });
  }
  catch (e) {
    console.error(e);
  }
  finally {
    updatingToggleBanned.value = 0;
  }
}


</script>

<template>
  <admin-layout
    :auth-user="authUser"
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
          :route-name="'admin.users'"
        />
      </div>

      <div class="users-container">
        <template v-for="user in usersList">
          <afr-user-card
            :user="user"
            :updated-banned="user.id === updatingToggleBanned"
            @ban="ban"
          />
        </template>
      </div>

      <div class="pagination-container">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.users'"
        />
      </div>
    </div>
  </admin-layout>
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
