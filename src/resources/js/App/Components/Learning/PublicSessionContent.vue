<script setup>
import FrenchText from './FrenchText.vue';
import PronunciationNotes from './PronunciationNotes.vue';
defineProps({ session: { type: Object, required: true } });
</script>
<template>
  <article class="public-session-content">
    <h1>{{ session.title }}</h1><p>{{ session.public_page?.summary || session.description }}</p>
    <pronunciation-notes :notes="session.pronunciation_notes ?? []" />
    <section v-for="(block,index) in session.public_page?.explanation_blocks ?? []" :key="index">
      <h2>{{ block.heading }}</h2><p v-for="(paragraph,i) in block.paragraphs" :key="i">{{ paragraph }}</p>
      <pronunciation-notes :notes="block.pronunciation_notes ?? []" />
      <ul><li v-for="(example,i) in block.examples ?? []" :key="i"><french-text :text="example.fr" :transcription-ru="example.transcription_ru" :translation="example.ru" /></li></ul>
    </section>
    <p v-if="!session.public_page?.explanation_blocks?.length" role="status">Публичное объяснение пока не подготовлено.</p>
    <h2>Выражения занятия</h2>
    <ul><li v-for="word in session.vocabulary" :key="word.id"><french-text :text="word.fr" :transcription-ru="word.transcription_ru" :translation="word.ru" /></li></ul>
  </article>
</template>
<style scoped>
.public-session-content { max-width: 880px; margin: auto; background: white; padding: 1rem; }
h1 { font-size: 1.6rem; font-weight: bold; }
h2 { font-size: 1.25rem; font-weight: bold; margin: 1.5rem 0 .75rem; }
p { white-space: pre-line; margin: .75rem 0; line-height: 1.7; }
ul { display: grid; gap: 1rem; margin: 1rem 0; }
li { padding: .75rem; border: 1px solid #dbeafe; border-radius: .25rem; }
</style>
