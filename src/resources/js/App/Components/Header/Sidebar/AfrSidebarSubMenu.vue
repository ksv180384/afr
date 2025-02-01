<script setup>
import { ref } from 'vue';

import AfrTabs from '@/App/Components/Tabs/AfrTabs.vue';
import AfrTabPlane from '@/App/Components/Tabs/AfrTabPlane.vue';
import AfrSidebarMenu from '@/App/Components/Header/Sidebar/AfrSidebarMenu.vue';
import AfrMenuLeft from '@/App/Components/Menu/MenuLeft/AfrMenuLeft.vue';
import AfrMenuLeftItem from '@/App/Components/Menu/MenuLeft/AfrMenuLeftItem.vue';

const props = defineProps({
  user: { type: Object, default: {} },
  menu: { type: Array, default: [] },
  subMenu: { type: Array, default: [] },
});

const activeTab = ref('sub_menu');
</script>

<template>
  <afr-tabs v-model="activeTab" position="bottom">
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
<!--      <afr-left-menu :menu="subMenu"/>-->
    </afr-tab-plane>
    <afr-tab-plane label="Меню" name="menu">
      <afr-sidebar-menu :menu="menu" :user="user"/>
    </afr-tab-plane>
  </afr-tabs>
</template>

<style scoped>

</style>
