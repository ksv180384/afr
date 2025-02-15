<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import AfrTabs from '@/App/Components/Tabs/AfrTabs.vue';
import AfrTabPlane from '@/App/Components/Tabs/AfrTabPlane.vue';
import AfrSidebarMenu from '@/App/Components/Header/Sidebar/AfrSidebarMenu.vue';
import AfrSidebarWidgetsMenu from '@/App/Components/Header/Sidebar/AfrSidebarWidgetsMenu.vue';
import AfrMenuLeft from '@/App/Components/Menu/MenuLeft/AfrMenuLeft.vue';
import AfrMenuLeftItem from '@/App/Components/Menu/MenuLeft/AfrMenuLeftItem.vue';

const props = defineProps({
  user: { type: Object, default: {} },
  menu: { type: Array, default: [] },
  widgetsMenu: { type: Array, default: null },
  subMenu: { type: Array, default: null },
});

const page = usePage();
const activeTab = ref(props.subMenu ? 'sub_menu' : (page.url.startsWith('/widget') ? 'widgets' : 'menu'));

</script>

<template>
  <afr-tabs v-model="activeTab" position="bottom">
    <afr-tab-plane label="Меню" name="menu">
      <afr-sidebar-menu :menu="menu" :user="user"/>
    </afr-tab-plane>
    <afr-tab-plane label="Виджеты" name="widgets">
      <afr-menu-left>
        <afr-sidebar-widgets-menu :menu="widgetsMenu"/>
      </afr-menu-left>
    </afr-tab-plane>
    <template v-if="subMenu">
      <afr-tab-plane label="Меню раздела" name="sub_menu">
        <afr-menu-left>
          <afr-menu-left-item
            v-for="menuItem in subMenu"
            :href="menuItem.href"
            :title="menuItem.title"
            :is-active="menuItem.is_active"
          >
            {{ menuItem.title }}
          </afr-menu-left-item>
        </afr-menu-left>
      </afr-tab-plane>
    </template>
  </afr-tabs>
</template>

<style scoped>

</style>
