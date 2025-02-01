<script setup>
import menu from '@/App/Store/menu.js';

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
          <Search/>
          <Authentication class="ms-5" :auth-user="authUser"/>
        </div>

        <afr-sidebar
          :user="authUser"
          :menu="menu"
          :sub-menu="subMenu"
        />
      </div>
    </div>
  </header>
</template>

<style scoped>
header{
  @apply fixed top-0 left-0 flex flex-row justify-between items-center w-full bg-blue-100 text-gray-700 shadow-lg;
  z-index: 2;
}

.header-content{
  @apply flex flex-row lg:justify-between px-4 justify-end;
}

.header-actions{
  @apply hidden lg:flex flex-row items-center;
}

.navigation{
  @apply hidden lg:flex flex-grow items-center;
}
</style>
