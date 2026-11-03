<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import FrenchText from './FrenchText.vue';
import AudioControl from './AudioControl.vue';
import PronunciationNotes from './PronunciationNotes.vue';
import { introductionPages } from '@/App/Learning/playerUi.js';
const props = defineProps({ introductions: { type: Array, required: true }, vocabulary: { type: Array, required: true }, reference: Boolean, busy: Boolean });
const emit = defineEmits(['complete', 'close']);
const index = ref(0);
const heading = ref(null);
const focusHeading = async () => { await nextTick(); heading.value?.focus(); };
onMounted(focusHeading);
watch(index, focusHeading);
const pages = computed(() => introductionPages(props.introductions, props.vocabulary));
const page = computed(() => pages.value[index.value]);
watch(() => props.introductions, () => { index.value = 0; });
</script>
<template>
  <section class="introduction-card" aria-label="Вводная карточка">
    <h2 ref="heading" tabindex="-1">{{ reference ? 'Справка к вопросу' : 'Познакомимся с выражениями' }}</h2>
    <template v-if="page">
      <p v-if="page.showText" class="introduction-text">{{ page.text }}</p>
      <audio-control v-if="page.showText && page.audio" :audio="page.audio" />
      <pronunciation-notes v-if="page.showText" :notes="page.pronunciation_notes ?? []" />
      <ul class="introduction-words">
        <li v-for="word in page.words" :key="word.id">
          <french-text :text="word.fr" :transcription-ru="word.transcription_ru" :translation="word.ru" />
          <audio-control :audio="word.audio ?? { text: word.fr, lang: 'fr-FR', asset_id: null }" label="Прослушать" />
        </li>
      </ul>
      <p v-if="pages.length > 1" class="page-count">Карточка {{ index + 1 }} из {{ pages.length }}</p>
    </template>
    <div class="introduction-actions">
      <afr-button v-if="index > 0" :disabled="busy" @click="index--">Назад</afr-button>
      <afr-button v-if="index < pages.length - 1" :disabled="busy" @click="index++">Следующая карточка</afr-button>
      <afr-button v-else-if="!reference" class="practice-primary" type="primary" :loading="busy" @click="emit('complete')">К вопросу</afr-button>
      <afr-button v-if="reference" @click="emit('close')">Вернуться к вопросу</afr-button>
    </div>
    <p v-if="!reference" class="introduction-caption">Это знакомство с материалом перед вопросом — самостоятельный балл сохраняется.</p>
  </section>
</template>
<style scoped>
h2 { font-size: 1.25rem; font-weight: bold; }
.introduction-text { margin-top: .75rem; line-height: 1.65; white-space: pre-line; }
.introduction-words { display: grid; gap: 1rem; margin-top: 1rem; }
.introduction-words li { padding: .85rem; border: 1px solid #cbd5e1; background: white; border-radius: .25rem; display: grid; gap: .65rem; }
.introduction-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }
.introduction-caption, .page-count { margin-top: .75rem; font-size: .875rem; color: #475569; }
</style>
