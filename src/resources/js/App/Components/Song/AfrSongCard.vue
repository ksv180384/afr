<script setup>
import {Link} from "@inertiajs/vue3";

const props = defineProps({
  song: { type: Object, required: true },
});
</script>

<template>
  <Link :href="route('lyrics.show', { id: song.id })" class="px-4 py-2 hover:bg-blue-100">
    <div class="font-semibold">
      {{ song.artist.name }}
    </div>
    <div class="text-lg">
      {{ song.title }}
    </div>
  </Link>
</template>

<style scoped>

</style>
