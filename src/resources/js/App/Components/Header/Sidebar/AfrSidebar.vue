<script setup>
import { nextTick, ref, watch, onMounted, onBeforeUnmount } from 'vue';

// import AfrSidebarMenu from '@/App/Components/Header/Sidebar/AfrSidebarMenu.vue';
import AfrSidebarSubMenu from '@/App/Components/Header/Sidebar/AfrSidebarSubMenu.vue';
import ProfileControl from '@/App/Components/Header/Authentification/ProfileControl.vue';

const props = defineProps({
  user: { type: Object, default: {} },
  menu: { type: Array, default: [] },
  subMenu: { type: Array, default: null },
  widgetsMenu: { type: Array, default: null },
});

const isSidebarVisible = ref(false);
const menuButton = ref(null);
const closeButton = ref(null);
const onSidebarKey = event => {
  if (event.key === 'Escape') {
    event.preventDefault();
    isSidebarVisible.value = false;
  }
  if (event.key !== 'Tab') return;
  const items = [...event.currentTarget.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled), [tabindex="0"]')]
    .filter(item => item.getClientRects().length);
  const first = items[0];
  const last = items.at(-1);
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last?.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first?.focus();
  }
};

const toggleSidebar = () => {
  isSidebarVisible.value = !isSidebarVisible.value;
};


watch(
  () => isSidebarVisible.value,
  async (newVal) => {
    if(newVal){
      document.body.style.overflow = 'hidden';
      document.body.style.height = '100vh';
      await nextTick();
      if (isSidebarVisible.value) closeButton.value?.focus();
    }
    else{
      document.body.style.overflow = '';
      document.body.style.height = '';
      menuButton.value?.focus({preventScroll:true});
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
  if (isSidebarVisible.value) {
    document.body.style.overflow = '';
    document.body.style.height = '';
  }
});
// Скрыть боковую панель при навигации
// router.on('before', () => {
//   isSidebarVisible.value = false
// })
</script>

<template>
<div class="afr-sidebar">
  <div class="flex items-center gap-2">
    <template v-if="user">
      <profile-control :user="user"/>
    </template>
    <button ref="menuButton" type="button" class="menu-mini" aria-label="Меню сайта"
      :aria-expanded="isSidebarVisible" aria-controls="site-mobile-menu" @click="toggleSidebar">
      <span class="menu-bars" aria-hidden="true"></span>
    </button>
  </div>

  <transition name="transition-bg-sidebar">
    <div v-if="isSidebarVisible" class="bg-sidebar" @click="toggleSidebar"></div>
  </transition>

  <transition name="transition-sidebar">
    <aside
      v-if="isSidebarVisible"
      id="site-mobile-menu" class="sidebar-container" role="dialog" aria-modal="true" aria-label="Меню сайта"
      @keydown="onSidebarKey"
      :class="{ 'transform -translate-x-full': !isSidebarVisible, 'translate-x-0': isSidebarVisible }"
    >
      <button ref="closeButton" type="button" class="menu-close" aria-label="Закрыть меню" @click="toggleSidebar">×</button>

<!--      <template v-if="subMenu">-->
        <afr-sidebar-sub-menu
          :menu="menu"
          :widgetsMenu="widgetsMenu"
          :sub-menu="subMenu"
          :user="user"
        />
<!--      </template>-->
<!--      <template v-else>-->
<!--        <afr-sidebar-menu :menu="menu" :user="user"/>-->
<!--      </template>-->
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

.menu-bars { width:24px; height:2px; background:currentColor; box-shadow:0 -7px currentColor, 0 7px currentColor; }
.menu-close { position:absolute; top:.5rem; right:.5rem; z-index:1; width:44px; height:44px; border-radius:.5rem; background:#fff; font-size:1.65rem; }
.menu-mini:focus-visible, .menu-close:focus-visible { outline:3px solid #195bb2; outline-offset:-3px; }

.sidebar-container{
  @apply fixed top-0 h-full left-0 w-[400px] max-w-full bg-sky-50 shadow-lg transform transition-transform duration-300;
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
@media (prefers-reduced-motion: reduce) {
  .menu-mini, .sidebar-container, .transition-bg-sidebar-enter-active, .transition-bg-sidebar-leave-active,
  .transition-sidebar-enter-active, .transition-sidebar-leave-active { transition: none; }
}
</style>
