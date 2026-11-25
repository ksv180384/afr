<script setup>
import { Link } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

defineProps({
  href: {
    type: String,
    required: true,
  },
  icon: String,
  tone: String,
  fullReload: Boolean,
});
</script>

<template>
  <component :is="fullReload ? 'a' : Link" :href="href" class="link-item" :class="tone">
    <span v-if="icon" class="link-icon" aria-hidden="true">
      <Icon :icon="icon" width="18" height="18" />
    </span>
    <span><slot /></span>
  </component>
</template>

<style scoped>
.link-item {
  @apply flex min-h-11 w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-start
    text-sm font-medium leading-5 text-slate-700 hover:bg-blue-50 hover:text-blue-800
    focus-visible:bg-blue-50 focus-visible:outline focus-visible:outline-2
    focus-visible:-outline-offset-2 focus-visible:outline-blue-600 motion-safe:transition-colors;
}

.link-icon {
  @apply flex h-[18px] w-[18px] shrink-0 items-center justify-center opacity-80;
}

.link-item.primary {
  @apply font-semibold text-blue-700;
}

.link-item.danger {
  @apply text-red-700 hover:bg-red-50 hover:text-red-800 focus-visible:bg-red-50 focus-visible:text-red-800;
}
</style>
