<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import AfrAdminMenu from '@/Admin/Components/Menu/AfrAdminMenu.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  title: { type: String, default: null },
  fixedHeight: { type: Boolean, default: false },
});

const isMobileMenuOpen = ref(false);

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false;
};

const handleMenuClick = (event) => {
  if (event.target.closest('a')) {
    closeMobileMenu();
  }
};

watch(isMobileMenuOpen, (isOpen) => {
  document.body.style.overflow = isOpen ? 'hidden' : '';
});

onBeforeUnmount(() => {
  document.body.style.overflow = '';
});
</script>

<template>
  <div
    class="layout-admin"
    :class="{
      'layout-admin--fixed': fixedHeight,
      'layout-admin--menu-open': isMobileMenuOpen,
    }"
  >
    <div class="layout-admin-container">
      <div
        v-if="isMobileMenuOpen"
        class="mobile-menu-backdrop"
        @click="closeMobileMenu"
      />

      <div class="left-container" @click="handleMenuClick">
        <div class="panel-title">
          Панель управления
        </div>

        <afr-admin-menu class="sticky top-[10px] mt-4" />
      </div>

      <div class="content-container">
        <div class="admin-topbar">
          <button
            type="button"
            class="mobile-menu-button"
            aria-label="Открыть меню"
            :aria-expanded="isMobileMenuOpen"
            @click="toggleMobileMenu"
          >
            <span />
            <span />
            <span />
          </button>

          <div class="admin-topbar-title">
            {{ title ?? '&nbsp;' }}
          </div>
        </div>

        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped>
body{
  @apply h-full;
}

.layout-admin{
  @apply max-w-[1200px] min-h-screen mx-auto bg-blue-50;
}

.layout-admin--fixed{
  @apply h-screen min-h-0 overflow-hidden;
}

.layout-admin-container{
  @apply flex flex-row min-h-full;
}

.layout-admin--fixed .layout-admin-container{
  @apply h-full min-h-0 overflow-hidden;
}

.left-container{
  @apply bg-blue-100 w-[280px];
  vertical-align: top;
}

.layout-admin--fixed .left-container{
  @apply h-full overflow-hidden;
}

.content-container{
  @apply inline-block min-h-screen;
  width: calc(100% - 280px);
}

.layout-admin--fixed .content-container{
  @apply h-full min-h-0 overflow-hidden flex flex-col;
}

.panel-title{
  @apply text-center py-4 bg-sky-600 text-blue-50 font-semibold shadow-lg;
}

.admin-topbar{
  @apply relative flex items-center justify-center py-4 bg-gray-700 text-blue-50 font-semibold shadow-lg;
}

.admin-topbar-title{
  @apply px-12 text-center;
}

.mobile-menu-button{
  @apply hidden absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 items-center justify-center rounded border border-slate-500 bg-slate-700 transition hover:bg-slate-600;
}

.mobile-menu-button span{
  @apply absolute block h-0.5 w-5 rounded bg-blue-50 transition;
}

.mobile-menu-button span:nth-child(1){
  transform: translateY(-6px);
}

.mobile-menu-button span:nth-child(3){
  transform: translateY(6px);
}

.layout-admin--menu-open .mobile-menu-button span:nth-child(1){
  transform: rotate(45deg);
}

.layout-admin--menu-open .mobile-menu-button span:nth-child(2){
  opacity: 0;
}

.layout-admin--menu-open .mobile-menu-button span:nth-child(3){
  transform: rotate(-45deg);
}

.mobile-menu-backdrop{
  @apply fixed inset-0 z-40 bg-gray-900/40;
}

@media (max-width: 767px){
  .layout-admin{
    @apply max-w-none w-full;
  }

  .layout-admin-container{
    @apply block;
  }

  .left-container{
    @apply fixed left-0 top-0 z-50 h-screen overflow-y-auto shadow-xl;
    width: min(280px, 86vw);
    transform: translateX(-100%);
    transition: transform 0.25s ease;
  }

  .layout-admin--menu-open .left-container{
    transform: translateX(0);
  }

  .content-container{
    @apply block w-full min-h-screen;
  }

  .layout-admin--fixed .content-container{
    @apply h-screen;
  }

  .mobile-menu-button{
    @apply flex;
  }

  .admin-topbar{
    @apply sticky top-0 z-30;
  }
}
</style>
