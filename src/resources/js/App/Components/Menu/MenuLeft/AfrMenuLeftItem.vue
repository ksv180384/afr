<script setup>

import { Link } from '@inertiajs/vue3';

defineProps({
  href: { type: String, default: '' },
  title: { type: String, default: '' },
  isActive: { type: Boolean, default: false },
});
</script>

<template>
<li :class="{ 'active': isActive }">
  <Link :href="href" :title="title">
    <slot/>
  </Link>
</li>
</template>

<style scoped>
li{
  @apply border-sky-500 leading-5 border-b relative text-sky-900 transition-transform duration-1000;
}

li:hover{
  @apply text-sky-100;
}

li:before{
  @apply absolute inset-0 bg-sky-700 transform scale-x-0 origin-left transition-transform duration-100;
  content: '';
}

li:hover:before{
  @apply scale-x-100;
}

li.active{
  @apply bg-sky-700 text-sky-100;
}

li>a{
  @apply relative inline-block px-2 py-3 w-full;
}
</style>
