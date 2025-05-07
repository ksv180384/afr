<script setup>


import AfrAdminMenu from '@/Admin/Components/Menu/AfrAdminMenu.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  title: { type: String, default: null },
});
</script>
<template>
  <div class="layout-admin">

    <div class="layout-admin-container">
      <div class="left-container">

        <div class="text-center py-4 bg-sky-600 text-blue-50 font-semibold shadow-lg">
          Панель управления
        </div>

        <afr-admin-menu class="sticky top-[10px] mt-4"/>

      </div>
      <div class="content-container">
        <div class="text-center py-4 bg-gray-700 text-blue-50 font-semibold shadow-lg">
          {{ title ?? '&nbsp;' }}
        </div>

        <slot/>

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

.layout-admin-container{
  @apply min-h-full;
}

.left-container{
  @apply inline-block bg-blue-100 w-[280px] min-h-screen;
  vertical-align: top;
}

.content-container{
  @apply inline-block min-h-screen;
  width: calc(100% - 280px);
}
</style>
