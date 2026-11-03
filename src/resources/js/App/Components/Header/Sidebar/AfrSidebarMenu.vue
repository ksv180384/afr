<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';

import { ref } from 'vue';
import { prepareGuestAuthentication } from '@/App/Learning/guestSyncState.js';
import { isMenuItemActive } from '@/App/Store/menu.js';
import AfrSidebarAvatar from '@/App/Components/Header/Sidebar/AfrSidebarAvatar.vue';

const props = defineProps({
  user: { type: Object, default: null },
  menu: { type: Array, default: [] },
});

const page = usePage();
const error = ref('');
const authenticate = async (path) => {
  error.value = '';
  try { router.visit(path + (await prepareGuestAuthentication() ? '?practice=1' : '')); }
  catch (failure) { error.value = failure.message; }
};

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
          <a :href="route('login')" class="link" @click.prevent="authenticate('/login')">Вход</a>
        </div>
        <div class="flex-1 text-center">
          <a :href="route('register')" class="link" @click.prevent="authenticate('/register')">Регистрация</a>
        </div>
      </div>
    </template>
  </div>

  <p v-if="error" role="alert">{{ error }}</p>
  <div class="p-4">
    <nav class="sidebar-nav">
      <ul>
        <template v-for="menuItem in menu">
          <li :class="{ 'active': isMenuItemActive(page.url, menuItem) }">
            <Link :href="route(menuItem.name)" :aria-current="isMenuItemActive(page.url, menuItem) ? 'page' : null">{{ menuItem.title }}</Link>
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
  @apply pb-8;
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
