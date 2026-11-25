<script setup>
import menu from '@/App/Store/menu.js';
import widgetsMenu from '@/App/Store/widgetsMenu.js';

import Navigation from '@/App/Components/Header/Navigation/Navigation.vue';
import Search from '@/App/Components/Header/Search/Search.vue';
import Authentication  from '@/App/Components/Header/Authentification/Authentication.vue';
import AfrSidebar  from '@/App/Components/Header/Sidebar/AfrSidebar.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  subMenu: { type: Array, default: null },
});
</script>

<template>
  <header>
    <div class="container-max mx-auto">
      <div class="header-content">
        <div class="navigation">
          <Navigation :menu="menu"/>
        </div>

        <div class="header-actions">
          <Search class="header-search" />
          <Authentication :auth-user="authUser" />
          <afr-sidebar
            :user="authUser"
            :menu="menu"
            :widgets-menu="widgetsMenu"
            :sub-menu="subMenu"
          />
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
header{
  @apply fixed top-0 left-0 flex flex-row justify-between items-center w-full bg-blue-100 text-gray-700 shadow-lg;
  z-index: 2;
}

header:has(.menu-mini[aria-expanded="true"]) { z-index: 20; }

.header-content{
  @apply flex flex-row lg:justify-between px-4 justify-end;
}

.header-actions{
  @apply flex items-center gap-2 lg:gap-5;
}

.header-search {
  @apply hidden lg:block;
}

.navigation{
  @apply hidden lg:flex flex-grow items-center;
}
</style>
