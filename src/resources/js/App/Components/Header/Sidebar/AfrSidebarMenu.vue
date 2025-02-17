<script setup>
import { Link, usePage } from '@inertiajs/vue3';

import AfrSidebarAvatar from '@/App/Components/Header/Sidebar/AfrSidebarAvatar.vue';

const props = defineProps({
  user: { type: Object, default: null },
  menu: { type: Array, default: [] },
});

const page = usePage();

const goToProfile = () => {
  router.visit(route('profile.edit'));
}
</script>

<template>
<div class="afr-sidebar-menu">
  <div class="py-8 flex-1">
    <template v-if="user">
      <afr-sidebar-avatar
        class="mx-auto"
        :avatar="user.avatar_link"
        @click="goToProfile"
      />
      <div class="text-center font-bold text-md mt-2">
        {{ user.name }}
      </div>
    </template>
    <template v-else>
      <div class="flex">
        <div class="flex-1 text-center">
          <Link :href="route('login')" class="link">Вход</Link>
        </div>
        <div class="flex-1 text-center">
          <Link :href="route('register')" class="link">Регистрация</Link>
        </div>
      </div>
    </template>
  </div>

  <div class="p-4">
    <nav class="sidebar-nav">
      <ul>
        <template v-for="menuItem in menu">
          <li :class="{ 'active': page.url.split('/')[1] === menuItem.path.split('/')[1] }">
            <Link :href="route(menuItem.name)">{{ menuItem.title }}</Link>
          </li>
        </template>
      </ul>
    </nav>
  </div>
</div>
</template>

<style scoped>
.afr-sidebar-menu{
  @apply flex flex-col h-full;
}

.sidebar-nav{
  @apply pb-10;
}

.sidebar-nav li{
  @apply text-xl;
}

.sidebar-nav li.active{
  @apply bg-blue-200;
}

:deep(.sidebar-nav li a){
  @apply py-3 flex w-full justify-center text-sky-800 border-b border-blue-200;
}

:deep(.sidebar-nav li:last-child a){
  @apply border-b-0;
}
</style>
