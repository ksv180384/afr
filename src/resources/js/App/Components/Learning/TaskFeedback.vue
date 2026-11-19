<script setup>
import { computed } from 'vue';
import FrenchText from './FrenchText.vue';
import PronunciationNotes from './PronunciationNotes.vue';
import AudioControl from './AudioControl.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { normalizeSurface } from '@/App/Learning/answerChecker.js';
const props = defineProps({ task: { type: Object, required: true }, result: { type: Object, required: true }, hideHeading: Boolean });
const solution = computed(() => props.result.solution ?? {});
const choice = computed(() => props.task.choices?.find(({ id }) => id === solution.value.choice_id));
const notes = computed(() => solution.value.pronunciation_notes ?? []);
const title = computed(() => props.result.technical_skip ? 'Шаг пропущен: звук недоступен'
  : (props.result.correct ? (props.result.first_independent_success ? 'Верно' : 'Верно с помощью') : 'Правильный ответ'));
const transcription = (text) => notes.value.find(({ fr }) => normalizeSurface(fr) === normalizeSurface(text))?.transcription_ru ?? '';
const item = (side, id) => props.task[side].find((entry) => entry.id === id);
</script>
<template>
  <section class="task-feedback" :class="{ success: result.correct, skipped: result.technical_skip }" aria-label="Обратная связь">
    <div v-if="!hideHeading" class="feedback-heading"><span class="feedback-icon"><daily-practice-icon :name="result.technical_skip ? 'dash' : result.correct ? 'check' : 'book'" /></span><h3 role="status" aria-live="polite">{{ title }}</h3></div>
    <p v-if="result.pairs?.length" class="feedback-message">Верно пар: {{ result.pairs.filter((pair) => pair.correct).length }} из {{ result.pairs.length }}.</p>
    <p v-if="result.feedback?.message" class="feedback-message">{{ result.feedback.message }}</p>
    <details v-if="!result.technical_skip" class="feedback-details" :open="!result.correct">
      <summary>Ответ и объяснение</summary>
    <pronunciation-notes :notes="result.feedback?.pronunciation_notes ?? []" />
    <div v-if="solution.completed_phrases?.length" class="solutions">
      <div v-for="(phrase, index) in solution.completed_phrases" :key="index" class="spoken-solution">
        <french-text :text="phrase.fr" :transcription-ru="phrase.transcription_ru" />
        <audio-control compact :audio="{ text: phrase.fr, lang: 'fr-FR', asset_id: null }" label="Прослушать ответ" />
      </div>
    </div>
    <div v-else-if="choice" class="solutions">
      <div v-if="choice.text_lang === 'fr'" class="spoken-solution">
        <french-text :text="choice.text" :transcription-ru="choice.transcription_ru" />
        <audio-control compact :audio="{ text: choice.text, lang: 'fr-FR', asset_id: null }" label="Прослушать ответ" />
      </div>
      <p v-else>{{ choice.text }}</p>
    </div>
    <ul v-else-if="solution.pairs" class="solved-pairs">
      <li v-for="pair in solution.pairs" :key="pair.left_id">
        <strong v-if="result.pairs.some((entry) => entry.left_id === pair.left_id)">{{ result.pairs.find((entry) => entry.left_id === pair.left_id).correct ? 'Верно' : 'Нужно исправить' }}</strong>
        <french-text v-if="item('left_items', pair.left_id).text_lang === 'fr'" :text="item('left_items', pair.left_id).text" :transcription-ru="item('left_items', pair.left_id).transcription_ru" compact />
        <span v-else>{{ item('left_items', pair.left_id).text }}</span>
        <span aria-hidden="true">→</span>
        <french-text v-if="item('right_items', pair.right_id).text_lang === 'fr'" :text="item('right_items', pair.right_id).text" :transcription-ru="item('right_items', pair.right_id).transcription_ru" compact />
        <span v-else>{{ item('right_items', pair.right_id).text }}</span>
      </li>
    </ul>
    <div v-else-if="solution.accepted_answers" class="solutions">
      <div v-for="answer in solution.accepted_answers" :key="answer">
        <div v-if="task.answer_lang === 'fr'" class="spoken-solution">
          <french-text :text="answer" :transcription-ru="transcription(answer)" />
          <audio-control compact :audio="{ text: answer, lang: 'fr-FR', asset_id: null }" label="Прослушать ответ" />
        </div>
        <p v-else>{{ answer }}</p>
      </div>
    </div>
    <p v-if="!result.technical_skip && (solution.completed_phrases?.length || choice?.text_lang === 'fr')" class="score-caption">По желанию послушайте ответ и повторите его вслух. Это не оценивается.</p>
    <p v-if="result.help_used && result.correct" class="score-caption">Ответ найден с помощью. Самостоятельный балл за шаг — 0.</p>
    </details>
    <p v-if="result.technical_skip" class="score-caption">Пропуск учитывается отдельно и не считается словарной ошибкой.</p>
  </section>
</template>
<style scoped>
.task-feedback { overflow-wrap: anywhere; color: #6d5022; }
.task-feedback.success { color: #226343; }
.task-feedback.skipped { color: #475569; }
.feedback-heading { display: flex; align-items: center; gap: .55rem; }
.feedback-icon { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: #f5e8cc; flex-shrink: 0; }
.success .feedback-icon { background: #d7efe2; }
.skipped .feedback-icon { background: #e2e8f0; }
.feedback-icon :deep(svg) { width: 18px; height: 18px; }
h3 { font-size: 1.05rem; font-weight: 700; }
.feedback-message { margin-top: .3rem; line-height: 1.5; font-size: .9rem; }
.feedback-details { color: #344e63; margin-top: .25rem; }
.feedback-details summary { cursor: pointer; font-size: .8rem; padding: .5rem 0; min-height: 36px; }
.solutions { margin-top: .75rem; display: grid; gap: .75rem; }
.spoken-solution { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
.solved-pairs { display: grid; gap: .75rem; margin-top: .75rem; }
.solved-pairs li { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
.score-caption { color: #475569; font-size: .95rem; margin-top: .75rem; }
</style>
