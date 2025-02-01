<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Icon } from '@iconify/vue';

import AfrSidebarMenu from '@/App/Components/Header/Sidebar/AfrSidebarMenu.vue';
import AfrCloseBtn from '@/App/Components/Form/AfrCloseBtn.vue';
import AfrSidebarSubMenu from '@/App/Components/Header/Sidebar/AfrSidebarSubMenu.vue';

const props = defineProps({
  user: { type: Object, default: {} },
  menu: { type: Array, default: [] },
  subMenu: { type: Array, default: null },
});

const isSidebarVisible = ref(false);

const toggleSidebar = () => {
  isSidebarVisible.value = !isSidebarVisible.value;
};


watch(
  () => isSidebarVisible.value,
  (newVal) => {
    if(newVal){
      document.body.style.overflow = 'hidden';
      document.body.style.height = '100vh';
    }
    else{
      document.body.style.overflow = '';
      document.body.style.height = '';
    }
  }
);

const handleResize = () => {
  if (window.innerWidth > 1025) {
    isSidebarVisible.value = false;
  }
};

onMounted(() => {
  window.addEventListener('resize', handleResize);
  handleResize(); // Проверяем размер окна при монтировании
  document.body.style.overflow = '';
  document.body.style.height = '';
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
});
// Скрыть боковую панель при навигации
// router.on('before', () => {
//   isSidebarVisible.value = false
// })
</script>

<template>
<div class="afr-sidebar">
  <div>
    <div
      class="menu-mini"
      @click="toggleSidebar"
    >
      <Icon
        icon="mingcute:menu-fill"
        width="24px"
        height="24px"
      />
    </div>
  </div>

  <transition name="transition-bg-sidebar">
    <div v-if="isSidebarVisible" class="bg-sidebar" @click="toggleSidebar"></div>
  </transition>

  <transition name="transition-sidebar">
    <aside
      v-if="isSidebarVisible"
      class="sidebar-container"
      :class="{ 'transform -translate-x-full': !isSidebarVisible, 'translate-x-0': isSidebarVisible }"
    >
      <afr-close-btn class="absolute right-2 top-2 z-10" size="large" @click="toggleSidebar"/>

      <template v-if="subMenu">
        <afr-sidebar-sub-menu
          :menu="menu"
          :sub-menu="subMenu"
          :user="user"
        />
      </template>
      <template v-else>
        <afr-sidebar-menu :menu="menu" :user="user"/>
      </template>
    </aside>
  </transition>
</div>
</template>

<style scoped>
.afr-sidebar{
  @apply lg:hidden;
}

.menu-mini{
  @apply flex w-[56px] h-[56px] justify-center items-center cursor-pointer hover:bg-gray-50 transition duration-300 whitespace-nowrap;
}

.sidebar-container{
  @apply fixed top-0 left-0 h-screen w-[400px] max-w-full bg-sky-50 shadow-lg transform transition-transform duration-300;
}

.bg-sidebar{
  @apply fixed inset-0 bg-black opacity-50;
}

.sidebar-nav{

}

.sidebar-nav>ul{

}

.sidebar-nav>ul>li{
  @apply text-lg;
}

.sidebar-nav>ul>li>a{
  @apply inline-block px-4 py-2;
}


.transition-bg-sidebar-enter-active, .transition-bg-sidebar-leave-active{
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.transition-bg-sidebar-enter-from, .transition-bg-sidebar-leave-to{
  opacity: 0;
}

.transition-sidebar-enter-active, .transition-sidebar-leave-active{
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.transition-sidebar-enter-from, .transition-sidebar-leave-to{
  opacity: 0;
  transform: translateX(-100%);
}
</style>
