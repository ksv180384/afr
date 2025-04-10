<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  user: { type: Object, default: {} },
});

</script>

<template>
<div class="flex flex-row">
  <div class="rounded-full drop-shadow-lg p-1 bg-blue-200">
    <img
      :src="user.avatar_link"
      class="w-[80px] h-[80px] object-cover bg-white rounded-full"
      :alt="user.name"
    />
  </div>
  <div class="bg-white rounded-r-xl ps-14 -ms-10 py-2 pe-4 border border-blue-100 flex-1">
    <div>
      {{ user.name }}
    </div>
    <div class="text-xs">{{ user.rang.title }}</div>
  </div>
</div>
</template>

<style scoped>

</style>
