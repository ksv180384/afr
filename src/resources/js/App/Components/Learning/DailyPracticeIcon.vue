<script setup>
defineProps({ name: { type: String, required: true } });

const paths = {
  progress: ['M3 13h4v8H3zM10 8h4v13h-4zM17 3h4v18h-4z'],
  book: ['M12 5v16M12 5C9 2 5 2 2 3v16c3-1 7-1 10 2 3-3 7-3 10-2V3c-3-1-7-1-10 2Z', 'M6 7v7M18 7v7'],
  message: ['M21 11c0 5-4 8-9 8H8l-5 3 1-6a8 8 0 0 1-2-5c0-5 4-8 10-8s9 3 9 8Z'],
  clock: ['M12 7v5l4 3', 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z'],
  calendar: ['M4 5h16v16H4zM8 2v6M16 2v6M4 10h16', 'M8 14h2M14 14h2M8 17h2M14 17h2'],
  list: ['M3 4h2v2H3zM3 11h2v2H3zM3 18h2v2H3zM9 5h12M9 12h12M9 19h12'],
  chevron: ['m9 5 7 7-7 7'],
  play: ['m9 5 11 7-11 7Z'],
};
</script>

<template>
  <svg class="daily-practice-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path v-for="(path, index) in paths[name]" :key="index" :d="path" />
  </svg>
</template>

<style scoped>
.daily-practice-icon { width: 22px; height: 22px; flex-shrink: 0; }
</style>
