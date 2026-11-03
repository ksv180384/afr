<script setup>
import { ref } from 'vue';
import SessionPlayer from '@/App/Components/Learning/SessionPlayer.vue';
import { createEditorPreviewAdapter } from './editorPreview';
const props = defineProps({ session: Object, scenario: String, audioUrls: Object });
const exited = ref(false);
const savedDraft = ref(null);
const adapter = createEditorPreviewAdapter({ session: JSON.parse(JSON.stringify(props.session)), scenario: props.scenario,
  onDraft: (draft) => { savedDraft.value = draft; } });
</script>
<template>
  <session-player v-if="!exited" :adapter="adapter" :audio-urls="audioUrls" @exit="exited = true" />
  <p v-else>Тестовое занятие закрыто. <span v-if="savedDraft">Черновик сохранён только на время проверки.</span> Нажмите «Начать заново», чтобы повторить проверку.</p>
</template>
