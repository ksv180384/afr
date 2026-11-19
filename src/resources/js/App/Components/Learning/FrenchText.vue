<script setup>
defineProps({ text: { type: String, required: true }, transcriptionRu: { type: String, default: '' }, translation: { type: [String, Array], default: null }, showTranscription: { type: Boolean, default: true }, reserveTranscription: Boolean, compact: Boolean });
</script>
<template>
  <span class="french-material" :class="{ compact }">
    <span lang="fr" class="french-line">{{ text }}</span>
    <template v-if="showTranscription || reserveTranscription">
      <span v-if="transcriptionRu" lang="ru" class="transcription-line" :class="{ concealed: !showTranscription }" :aria-hidden="!showTranscription || undefined">{{ transcriptionRu }}</span>
      <span v-else-if="text.trim()" class="transcription-missing" :class="{ concealed: !showTranscription }" :aria-hidden="!showTranscription || undefined">Произношение не подготовлено</span>
    </template>
    <span v-if="translation" lang="ru" class="translation-line">{{ Array.isArray(translation) ? translation.join(' / ') : translation }}</span>
  </span>
</template>
<style scoped>
.french-material { display: inline-flex; flex-direction: column; min-width: 0; max-width: 100%; overflow-wrap: anywhere; vertical-align: top; }
.french-line { font-size: 1.4rem; line-height: 1.5; white-space: pre-wrap; }
.compact .french-line { font-size: 1.1rem; }
.transcription-line { font-size: .95rem; color: #475569; line-height: 1.5; }
.transcription-missing { font-size: .875rem; color: #92400e; }
.translation-line { margin-top: .25rem; font-size: 1rem; }
.concealed { visibility: hidden; }
</style>
