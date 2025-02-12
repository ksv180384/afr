<script setup>
import { Link } from '@inertiajs/vue3';
import menu from '@/Admin/Store/menu.js';

import AfrAdminMenuItem from '@/Admin/Components/Menu/AfrAdminMenuItem.vue';
</script>

<template>
<div class="menu-admin">
  <div>
    <template v-if="menu">
      <afr-admin-menu-item>
        <a href="/">На сайт</a>
      </afr-admin-menu-item>
      <afr-admin-menu-item
        v-for="menuItem in menu"
        :key="menuItem.name"
      >
        <Link :href="route(menuItem.name)">
          {{ menuItem.title }}
        </Link>
      </afr-admin-menu-item>
    </template>
  </div>

  <div class="mt-8 px-2">
    <afr-admin-menu-item>
      Анекдоты
    </afr-admin-menu-item>
    <afr-admin-menu-item>
      Грамматика
    </afr-admin-menu-item>
    <afr-admin-menu-item>
      Уроки
    </afr-admin-menu-item>
  </div>
</div>
</template>

<style scoped>
.menu-admin{

}

:deep(.link-item a){
  @apply px-2 py-1.5 text-sky-900 hover:bg-blue-200 cursor-pointer w-full block
  transition ease-in-out duration-500;
}
</style>
