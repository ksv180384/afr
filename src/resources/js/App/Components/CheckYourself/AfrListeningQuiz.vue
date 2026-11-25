<script setup>
import { computed, nextTick, onMounted, provide, ref } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AudioControl from '@/App/Components/Learning/AudioControl.vue';
import DailyPracticeIcon from '@/App/Components/Learning/DailyPracticeIcon.vue';
import PracticeErrorNotice from '@/App/Components/Learning/PracticeErrorNotice.vue';
import AfrWordProgress from '@/App/Components/Words/AfrWordProgress.vue';
import { createAudioPlayer } from '@/App/Learning/audioPlayer.js';
import api from '@/App/Services/Api';
import AfrWordItem from './AfrWordItem.vue';

provide('practiceAudioPlayer', createAudioPlayer());

const questions = ref([]);
const index = ref(0);
const current = computed(() => questions.value[index.value]);
const score = ref(0);
const missed = ref([]);
const history = ref([]);
const loading = ref(false);
const error = ref('');
const audioUnavailable = ref(false);
const prompt = ref(null);

async function loadRound() {
  loading.value = true;
  error.value = '';
  try {
    const { words } = await api.word.getWordsTestYourself();
    if (words?.length) {
      questions.value = words;
      index.value = 0;
      score.value = 0;
    } else {
      error.value = 'Пока нет слов для тренировки.';
    }
  } catch {
    error.value = 'Не удалось загрузить слова. Попробуйте ещё раз.';
  } finally {
    loading.value = false;
  }
}

async function choose(id) {
  const answer = current.value?.answer;
  if (!answer || loading.value || missed.value.includes(id)) return;

  if (id !== answer.id) {
    missed.value.push(id);
    score.value--;
    return;
  }

  score.value++;
  history.value.unshift({ answer, firstTry: missed.value.length === 0 });
  missed.value = [];
  audioUnavailable.value = false;
  index.value++;
  if (!current.value) await loadRound();
  await nextTick();
  prompt.value?.focus({ preventScroll: true });
}

onMounted(loadRound);
</script>

<template>
  <div class="quiz">
    <p v-if="loading" class="quiz-loading" role="status">Подбираем слова…</p>
    <div v-else-if="error">
      <practice-error-notice :message="error" />
      <afr-button @click="loadRound">Попробовать снова</afr-button>
    </div>
    <template v-else-if="current">
      <afr-word-progress :position="index + 1" :total="questions.length" :score="score" />

      <div class="quiz-prompt">
        <div class="min-w-0">
          <h3 ref="prompt" tabindex="-1">
            Выберите перевод<span class="sr-only">, слово {{ index + 1 }} из {{ questions.length }}</span>
          </h3>
          <p v-if="audioUnavailable" lang="fr">{{ current.answer.word }}</p>
          <p v-else>Послушайте слово на французском.</p>
        </div>
        <audio-control
          :key="current.answer.id"
          :audio="{ text: current.answer.word, lang: 'fr-FR' }"
          label="Прослушать слово"
          compact
          autoplay
          @available="audioUnavailable = false"
          @unavailable="audioUnavailable = true"
        />
      </div>

      <div class="grid gap-2">
        <afr-word-item
          v-for="option in current.answer_options"
          :key="option.id"
          :word="option"
          :incorrect="missed.includes(option.id)"
          @choose="choose"
        />
      </div>
      <p class="sr-only" role="status">
        {{ missed.length ? 'Неверно. Выберите другой перевод.' : '' }}
      </p>
    </template>

    <details v-if="history.length" class="quiz-history">
      <summary>
        <daily-practice-icon name="chevron" />
        Пройденные слова <span>{{ history.length }}</span>
      </summary>
      <ul>
        <li v-for="(entry, i) in history" :key="history.length - i">
          <daily-practice-icon
            :name="entry.firstTry ? 'check' : 'reset'"
            :class="entry.firstTry ? 'text-emerald-700' : 'text-amber-700'"
          />
          <span class="sr-only">{{ entry.firstTry ? 'С первой попытки.' : 'После ошибки.' }}</span>
          <span lang="fr" :title="entry.answer.transcription">{{ entry.answer.word }}</span>
          <span class="history-translation">{{ entry.answer.translation }}</span>
        </li>
      </ul>
    </details>
  </div>
</template>

<style scoped>
.quiz {
  @apply grid min-w-0 gap-4;
}

.quiz-loading {
  @apply py-5 text-center text-sm text-slate-600;
}

.quiz-prompt {
  @apply flex flex-wrap items-center justify-between gap-x-3 gap-y-2;
}

.quiz-prompt h3 {
  @apply text-xl font-bold leading-tight text-sky-950 outline-none;
}

.quiz-prompt p {
  @apply mt-1 text-sm leading-snug text-slate-600;
  overflow-wrap: anywhere;
}

.quiz-history {
  @apply min-w-0 border-t border-sky-100;
}

.quiz-history summary {
  @apply flex min-h-11 cursor-pointer list-none items-center gap-2 rounded text-sm font-semibold text-sky-800
    focus-visible:outline-sky-700;
}

.quiz-history summary::-webkit-details-marker { display: none; }
.quiz-history summary svg { @apply size-4; }
.quiz-history[open] summary svg { transform: rotate(90deg); }
.quiz-history summary span { @apply ml-auto font-normal tabular-nums text-slate-600; }

.quiz-history ul {
  @apply max-h-44 overflow-y-auto overscroll-contain;
}

.quiz-history li {
  @apply grid grid-cols-[1rem_minmax(0,1fr)_minmax(0,1fr)] items-baseline gap-x-2
    border-t border-slate-100 py-2 text-sm;
  overflow-wrap: anywhere;
}

.quiz-history li svg { @apply size-4 self-center; }
.quiz-history [lang="fr"] { @apply font-semibold text-sky-950; }
.history-translation { @apply text-slate-600; }
</style>
