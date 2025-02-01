<script setup>
const props = defineProps({
  song: { type: Object, default: {} }
});
const emits = defineEmits(['select']);

const selectSong = () => {
  emits('select', props.song);
}
</script>

<template>
  <li class="text-nowrap" @click="selectSong">
    <div class="font-text-xs">
      {{ song.artist_name }}
    </div>
    <div class="font-bold text-sm">
      {{ song.title }}
    </div>
  </li>
</template>

<style scoped>

</style>
