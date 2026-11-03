<script setup>
import FrenchText from './FrenchText.vue';
defineProps({ notes: { type: Array, default: () => [] } });
</script>
<template>
  <div v-if="notes.length" class="pronunciation-notes">
    <p class="notes-label">Произношение</p>
    <ul><li v-for="(note, index) in notes" :key="index"><french-text :text="note.fr" :transcription-ru="note.transcription_ru" compact /></li></ul>
  </div>
</template>
<style scoped>
.pronunciation-notes { margin-top: .75rem; }
.notes-label { font-size: .875rem; color: #475569; margin-bottom: .25rem; }
ul { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; }
</style>
