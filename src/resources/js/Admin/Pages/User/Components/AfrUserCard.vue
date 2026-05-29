<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  user: { type: Object, default: {} },
});
</script>

<template>
  <div class="flex flex-row min-w-0 items-start">
    <div class="rounded-full drop-shadow-lg p-1 bg-blue-200 shrink-0 w-[88px] h-[88px]">
      <img
        :src="user.avatar_link"
        class="w-[80px] h-[80px] object-cover bg-white rounded-full shrink-0"
        :alt="user.name"
      />
    </div>
    <div class="bg-white rounded-r-xl ps-14 -ms-10 py-2 pe-3 border border-blue-100 flex-1 min-w-0 min-h-[88px]">
      <div class="truncate">
        <Link
          :href="route('admin.user.show', user.id)"
          class="font-semibold text-sky-700 transition hover:text-sky-900 hover:underline"
        >
          {{ user.name }}
        </Link>
      </div>
      <div class="text-xs truncate">{{ user.rang.title }}</div>
    </div>
  </div>
</template>

<style scoped>

</style>
