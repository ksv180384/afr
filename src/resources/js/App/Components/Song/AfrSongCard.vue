<script setup>
const props = defineProps({
  song: { type: Object, required: true },
});
</script>

<template>
  <div class="px-4 py-2">
    <div class="font-semibold">
      {{ song.artist_name ?? song.artist?.name }}
    </div>
    <div class="text-lg">
      {{ song.title }}
    </div>
  </div>
</template>

<style scoped>

</style>
