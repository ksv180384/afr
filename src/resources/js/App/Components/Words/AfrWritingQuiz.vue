<script setup>
import { computed, onMounted, ref, useId, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import FrenchAnswerInput from '@/App/Components/Learning/FrenchAnswerInput.vue';
import FrenchText from '@/App/Components/Learning/FrenchText.vue';
import PracticeErrorNotice from '@/App/Components/Learning/PracticeErrorNotice.vue';
import { normalizeSurface } from '@/App/Learning/answerChecker.js';
import api from '@/App/Services/Api';
import AfrWordProgress from './AfrWordProgress.vue';

const inputId = useId();
const words = ref([]);
const index = ref(0);
const current = computed(() => words.value[index.value]);
const answer = ref('');
const loading = ref(false);
const error = ref('');
let nextRound = null;

async function fetchWords() {
  const { words } = await api.word.getWordsLearningWrite();
  return words;
}

async function loadRound() {
  loading.value = true;
  error.value = '';
  try {
    const batch = (await nextRound) ?? await fetchWords();
    words.value = batch ?? [];
    index.value = 0;
    if (!words.value.length) error.value = 'Пока нет слов для тренировки.';
  } catch {
    words.value = [];
    error.value = 'Не удалось загрузить слова. Попробуйте ещё раз.';
  } finally {
    nextRound = null;
    loading.value = false;
  }
}

watch(answer, (value) => {
  if (!value || loading.value || !current.value) return;
  if (normalizeSurface(value).toLowerCase() !== normalizeSurface(current.value.word).toLowerCase()) return;

  answer.value = '';
  if (index.value + 1 === words.value.length) {
    loadRound();
  } else {
    index.value++;
    if (index.value === Math.floor(words.value.length / 2)) {
      nextRound = fetchWords().catch(() => null);
    }
  }
}, { flush: 'post' });

onMounted(loadRound);
</script>

<template>
  <div class="writing-quiz">
    <div v-if="error">
      <practice-error-notice :message="error" />
      <afr-button @click="loadRound">Попробовать снова</afr-button>
    </div>
    <template v-else-if="current">
      <afr-word-progress :position="index + 1" :total="words.length" />
      <div class="word-sample" aria-live="polite" aria-atomic="true">
        <french-text
          :text="current.word"
          :show-transcription="!!current.transcription"
          :transcription-ru="current.transcription"
          :translation="current.translation"
        />
      </div>
      <french-answer-input
        :id="inputId"
        v-model="answer"
        label="Напишите слово по образцу"
        :readonly="loading"
        spacious
      />
    </template>
    <p v-if="loading" class="loading-message" role="status">Подбираем слова…</p>
  </div>
</template>

<style scoped>
.writing-quiz {
  @apply grid min-w-0 gap-4;
}

.word-sample {
  @apply rounded-xl border border-sky-100 bg-sky-50 px-4 py-3;
}

.word-sample :deep(.french-line) {
  @apply text-2xl font-bold leading-tight text-sky-950;
}

.word-sample :deep(.translation-line) {
  @apply mt-1 text-slate-700;
}

.loading-message {
  @apply py-3 text-center text-sm text-slate-600;
}
</style>
