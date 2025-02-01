<script setup>
import { Link } from '@inertiajs/vue3';
import { getUrlParam } from '@/Helpers/helper.js';

import AfrMenuSimpleItem from '@/App/Components/Menu/MenuSimple/AfrMenuSimpleItem.vue';

const props = defineProps({
  menu: { type: Array, required: true },
});


</script>

<template>
<div class="afr-menu-simple">
  <template v-for="menuItem in menu" :key="menuItem.title">
    <afr-menu-simple-item>
      <Link
        :href="route(menuItem.name, menuItem.params)"
        :class="[
          getUrlParam('status') === menuItem.params?.status ||
          !getUrlParam('status') && !menuItem.params?.status && route().current('user.posts')
          ? 'active' : ''
        ]"
      >
        {{ menuItem.title }}
      </Link>
    </afr-menu-simple-item>
  </template>
</div>
</template>

<style scoped>
.afr-menu-simple{
  @apply py-4;
}

:deep(.afr-menu-simple-item a){
  @apply px-3 py-1 block text-sky-800;
}

:deep(.afr-menu-simple-item a.active){
  @apply bg-sky-100;
}
</style>
