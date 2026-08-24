<script setup>
import { computed } from 'vue';

const props = defineProps({
  src: { type: String, default: null },
  name: { type: String, default: '' },
  loading: { type: String, default: 'lazy' },
});

const initials = computed(() => {
  const parts = props.name.trim().split(/\s+/u).filter(Boolean);

  if (!parts.length) return '♪';

  return parts
    .slice(0, 2)
    .map((part) => Array.from(part)[0])
    .join('')
    .toLocaleUpperCase('ru-RU');
});
</script>

<template>
  <span class="artist-avatar" :aria-label="src ? null : 'Изображение исполнителя не добавлено'">
    <img
      v-if="src"
      :src="src"
      :alt="name"
      :loading="loading"
      decoding="async"
      width="312"
      height="312"
    >
    <span v-else aria-hidden="true">{{ initials }}</span>
  </span>
</template>

<style scoped>
.artist-avatar {
  @apply inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-sky-100 to-blue-200 font-bold text-sky-800;
}

.artist-avatar img {
  @apply h-full w-full object-cover;
}
</style>
