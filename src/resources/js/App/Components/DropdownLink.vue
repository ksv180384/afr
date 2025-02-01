<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  href: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <Link :href="href" class="link-item">
    <slot />
  </Link>
</template>

<style scoped>
.link-item{
  @apply cursor-pointer transition duration-150 hover:bg-blue-50 focus:bg-blue-50 block w-full
  px-4 py-2 text-start text-sm leading-5 text-gray-700 ease-in-out focus:outline-none;
}
</style>
