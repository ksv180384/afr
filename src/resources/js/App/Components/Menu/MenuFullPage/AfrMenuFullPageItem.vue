<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  href: { type: String, default: '' },
  title: { type: String, default: '' },
});
</script>

<template>
<li>
  <Link :href="href" :title="title">
    <slot/>
  </Link>
</li>
</template>

<style scoped>
li{
  @apply text-center;
}

li>a{
  @apply py-2 inline-block border-b border-solid border-sky-200 transition-transform duration-700;
}

li>a:hover{
  @apply border-dashed border-sky-900 text-sky-800;
}
</style>
