<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrCard from '@/App/Components/Card/AfrCard.vue';
import { assertPlayerAdapter } from '@/App/Learning/playerAdapter.js';
import { answerComplete, emptyAnswer } from '@/App/Learning/playerUi.js';
import { createAudioPlayer } from '@/App/Learning/audioPlayer.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import PracticeReport from './PracticeReport.vue';
import FrenchText from './FrenchText.vue';
import AudioControl from './AudioControl.vue';
import PronunciationNotes from './PronunciationNotes.vue';
import IntroductionCards from './IntroductionCards.vue';
import TaskFeedback from './TaskFeedback.vue';
import SingleChoiceTask from './Tasks/SingleChoiceTask.vue';
import MatchingPairsTask from './Tasks/MatchingPairsTask.vue';
import FillBlankTask from './Tasks/FillBlankTask.vue';
import WordOrderTask from './Tasks/WordOrderTask.vue';
import ShortAnswerTask from './Tasks/ShortAnswerTask.vue';
import PracticeDate from './PracticeDate.vue';

const props = defineProps({ locked: Boolean, timezone: String, adapter: { type: Object, required: true }, audioUrls: { type: Object, default: () => ({}) } });
const emit = defineEmits(['exit', 'repeat', 'test-next', 'vocabulary']);
const adapter = assertPlayerAdapter(props.adapter);
const audio = createAudioPlayer({ resolveAsset: (id) => props.audioUrls[id] ?? view.value?.audioUrls?.[id] ?? null });
provide('practiceAudioPlayer', audio);
const view = ref(null);
const draft = ref(null);
const busy = ref(false);
const error = ref('');
const feedback = usePracticeFeedback({ error, source: 'practice-session' });
const retry = ref(null);
const retrying = ref(false);
let disposed = false;
const transcriptVisible = ref(false);
const referenceOpen = ref(false);
const hintOpen = ref(false);
const audioUnavailable = ref(false);
const heading = ref(null);
const isPreview = computed(() => adapter.mode === 'preview');
const task = computed(() => view.value?.task);
const correcting = ref(false);
const graded = computed(() => Boolean(view.value?.presentation?.first_result));
const result = computed(() => view.value?.presentation?.last_result?.solution ? view.value.presentation.last_result : view.value?.presentation?.first_result);
const introductory = computed(() => view.value?.started && !view.value.introduction_seen && view.value.introductions.length > 0);
const component = computed(() => ({ single_choice: SingleChoiceTask, matching_pairs: MatchingPairsTask, fill_blank: FillBlankTask, word_order: WordOrderTask, short_answer: ShortAnswerTask })[task.value?.type]);
const ready = computed(() => answerComplete(task.value, draft.value));
const listening = computed(() => task.value?.skill === 'listening');
const promptVisible = computed(() => !listening.value || transcriptVisible.value || graded.value);
const referenceIntroductions = computed(() => {
  const introductions = view.value?.introductions.length ? view.value.introductions : [{ id: 'reference', text: 'Выражения для этого вопроса' }];
  return introductions.map(intro => ({ ...intro, vocabulary_ids: [...new Set([...(intro.vocabulary_ids ?? []), ...(task.value?.vocabulary_ids ?? [])])] }));
});
const notesFor = (surface) => (task.value?.pronunciation_notes ?? []).filter(({ fr }) => surface.normalize('NFC').includes(fr.normalize('NFC')));
const visibleNotes = computed(() => notesFor([task.value?.instruction, promptVisible.value ? task.value?.prompt?.text : ''].filter(Boolean).join(' ')));
const missingMessage = computed(() => task.value?.type === 'matching_pairs' ? 'Составьте все пары, чтобы проверить ответ.'
  : (task.value?.type === 'word_order' ? 'Используйте все карточки, чтобы проверить фразу.'
    : (['short_answer', 'fill_blank'].includes(task.value?.type) && task.value?.input_mode !== 'choice' ? 'Введите ответ, чтобы проверить его.' : 'Выберите ответ, чтобы проверить его.')));

const perform = async (operation, after) => {
  if (busy.value || disposed) return;
  busy.value = true;
  error.value = '';
  retry.value = () => perform(operation, after);
  try {
    const next = await operation();
    if (disposed) return;
    if (next) view.value = next;
    if (after) await after();
    retry.value = null;
    retrying.value = false;
  } catch (failure) {
    const status=failure.response?.status;
    retrying.value = (!status && !failure.code) || failure.code === 'revision_conflict' || status === 409 || status === 429 || status >= 500;
    if (failure.code === 'revision_conflict') retry.value = () => perform(() => adapter.load(),focusQuestion);
    if (!retrying.value) retry.value = null;
    error.value = [401,419].includes(status) ? 'Войдите в свой аккаунт, чтобы продолжить занятие.' : (failure.response?.data?.message || failure.message || 'Не удалось открыть занятие.');
  } finally { busy.value = false; }
};
const focusQuestion = async () => { await nextTick(); heading.value?.focus(); };
watch(() => view.value?.presentation?.presentation_id, () => {
  audio.stop();
  correcting.value = false;
  draft.value = task.value ? (view.value.local_draft || view.value.presentation?.answer ? JSON.parse(JSON.stringify(view.value.local_draft ?? view.value.presentation.answer)) : emptyAnswer(task.value)) : null;
  transcriptVisible.value = false;
  referenceOpen.value = false;
  hintOpen.value = false;
  audioUnavailable.value = false;
  focusQuestion();
});
watch(() => view.value, () => {
  if (!view.value?.restore_assistance || !view.value.assistance) return;
  transcriptVisible.value = view.value.assistance.includes('audio_transcript');
  referenceOpen.value = view.value.assistance.includes('reference');
  hintOpen.value = view.value.assistance.includes('hint');
});
watch(draft, () => {
  if (adapter.storeDraft && task.value && view.value?.started && !graded.value) {
    adapter.storeDraft({presentation_id:view.value.presentation.presentation_id,answer:JSON.parse(JSON.stringify(draft.value))}).catch(() => {});
  }
}, {deep:true});
const start = () => perform(() => adapter.start(), focusQuestion);
const continueIntroduction = () => {
  const id = view.value.presentation.presentation_id;
  return perform(() => adapter.load({ introductionSeen: id }), focusQuestion);
};
const submit = () => {
  if (!ready.value || (graded.value && !correcting.value) || busy.value) return;
  const input = { client_event_id: crypto.randomUUID(), presentation_id: view.value.presentation.presentation_id, answer: JSON.parse(JSON.stringify(draft.value)) };
  audio.stop();
  return perform(() => adapter.answer(input), () => { correcting.value = false; });
};
const sendHelp = (fields, after) => {
  const input = { client_event_id: crypto.randomUUID(), presentation_id: view.value.presentation.presentation_id, ...fields };
  return perform(() => adapter.help(input), after);
};
const revealTranscript = () => graded.value ? (transcriptVisible.value = true)
  : sendHelp({ event_type: 'help', help_kind: 'audio_transcript' }, () => { transcriptVisible.value = true; });
const showReference = () => graded.value ? (referenceOpen.value = true)
  : sendHelp({ event_type: 'help', help_kind: 'reference' }, () => { referenceOpen.value = true; });
const showHint = () => sendHelp({ event_type: 'help', help_kind: 'hint' }, () => { hintOpen.value = true; });
const unknown = () => { audio.stop(); return sendHelp({ event_type: 'unknown' }); };
const technicalSkip = () => { audio.stop(); return sendHelp({ event_type: 'technical_skip', reason: 'audio_unavailable' }); };
const next = () => {
  audio.stop();
  if (view.value.position === view.value.total) return perform(() => adapter.finish(), focusQuestion);
  const id = view.value.presentation.presentation_id;
  return perform(() => adapter.load({ advanceFrom: id }), focusQuestion);
};
const exit = async () => {
  if (busy.value) return;
  audio.stop();
  if (typeof adapter.saveDraft === 'function' && view.value?.presentation && !view.value.finished && (['account','guest'].includes(adapter.mode) || !graded.value)) {
    await perform(async () => { await adapter.saveDraft({ presentation_id: view.value.presentation.presentation_id, answer: JSON.parse(JSON.stringify(draft.value)) }); return null; },
      () => emit('exit'));
  } else emit('exit');
};
defineExpose({prepareAuthentication:async () => {
  if (busy.value) throw new Error('Дождитесь сохранения текущего действия.');
  if (adapter.saveDraft && view.value?.presentation && !view.value.finished) await adapter.saveDraft({presentation_id:view.value.presentation.presentation_id,answer:JSON.parse(JSON.stringify(draft.value))});
},reload:() => perform(() => adapter.load()),reconcile:() => perform(() => adapter.acceptServerState())});
onMounted(() => { perform(() => adapter.load()); });
onBeforeUnmount(() => { disposed=true; audio.stop(); });
</script>
<template>
  <section class="session-player" :aria-busy="busy">
    <p v-if="isPreview" class="preview-notice">Предпросмотр · Учебный прогресс и словарь не записываются. После обновления страницы просмотр начнётся заново.</p>
    <div v-if="error" :class="feedback.enabled ? 'player-retry' : 'player-error'">
      <p role="alert">{{ error }}</p>
    </div>
    <afr-button v-if="retrying && retry" :disabled="busy" @click="retry()">Повторить отправку</afr-button>
    <p v-if="!view && !error" role="status" class="loading-message">Загружаем занятие…</p>
    <template v-if="view">
      <header class="player-header">
        <div><p class="level-label">{{ view.session.level === 'PRE_A1' ? 'С нуля' : view.session.level }} · Около {{ view.session.duration_minutes }} мин</p>
          <h1>{{ view.session.title }}</h1>
        </div>
        <afr-button :disabled="busy" @click="exit">{{ isPreview ? 'Выйти из предпросмотра' : (view.finished ? 'К списку занятий' : 'Выйти из занятия') }}</afr-button>
      </header>
      <p v-if="view.import_meta" class="level-label">Начато до входа · <practice-date :value="view.import_meta.client_started_at || view.import_meta.original_local_date" :timezone="timezone || view.timezone" /></p>
      <p v-else-if="view.started_at" class="level-label">Начато <practice-date :value="view.started_at" :timezone="timezone || view.timezone" /></p>
      <pronunciation-notes v-if="!view.started" :notes="view.session.pronunciation_notes ?? []" />
      <afr-card v-if="view.exhausted || !view.total" class="player-card">
        <h2>{{ view.exhausted ? 'Доступные занятия закончились' : 'В этом занятии пока нет заданий' }}</h2><p>{{ view.exhausted ? 'Можно вернуться к выбору занятий. Новые материалы появятся позже.' : 'Выберите другой подготовленный материал.' }}</p>
        <afr-button @click="exit">К выбору занятий</afr-button>
      </afr-card>
      <afr-card v-else-if="view.finished" class="player-card result-card">
        <h2 ref="heading" tabindex="-1">{{ isPreview ? 'Предпросмотр завершён' : 'Занятие завершено' }}</h2>
        <p class="result-score">Самостоятельно: {{ view.summary.independent_correct_steps }} из {{ view.summary.total_steps }}</p>
        <p v-if="view.summary.technical_skips">Технических пропусков: {{ view.summary.technical_skips }}. Они не являются языковыми ошибками.</p>
        <p v-if="view.finished_at || view.activity_date">Завершено <practice-date :value="view.finished_at || view.activity_date" :timezone="timezone || view.timezone" /></p>
        <p v-if="view.summary.integrations_pending?.length">Результат занятия сохранён. Учебные цели и переход уровня подтверждаются в аккаунте после входа.</p>
        <p v-if="view.summary.objectives?.enrollment_id">Освоено целей закреплённой программы: {{ view.summary.objectives.mastered }} из {{ view.summary.objectives.required }}. Завершение занятия и освоение цели учитываются отдельно.</p>
        <p v-if="view.summary.checkpoint" role="status">{{ view.summary.checkpoint.qualified ? 'Контроль прошёл порог самостоятельных ответов.' : 'Контроль пока не подтверждает переход: недостаточно самостоятельных ответов, был технический пропуск или некорректная исходная дата.' }}</p>
        <p v-if="view.summary.promotion" role="status">Уровень повышен до {{ view.summary.promotion.new_level }}. {{ view.summary.promotion.reason.message }}</p>
        <p v-if="isPreview">Результат относится к этому просмотру. Личный прогресс не изменён.</p>
        <p v-if="view.summary.words_added !== undefined">Добавлено выражений: {{ view.summary.words_added }}. Уже были в словаре: {{ view.summary.words_existing }}.</p>
        <afr-button class="practice-primary" type="primary" @click="exit">К выбору занятий</afr-button>
        <afr-button v-if="!isPreview" @click="emit('vocabulary')">Мой словарь</afr-button>
        <afr-button v-if="view.has_next_test_block" :disabled="busy || locked" @click="emit('test-next', {continue_run_id:view.run_id})">Следующий блок слов</afr-button>
        <p v-if="view.self_check_words?.length">Для {{ view.self_check_words.length }} выражений доступна самопроверка в словаре без зачёта.</p>
        <afr-button v-if="['account','guest'].includes(adapter.mode) && ['daily','extra'].includes(view.kind)" :disabled="locked || busy" @click="emit('repeat', {session_id:view.session.id})">Пройти ещё раз</afr-button>
      </afr-card>
      <afr-card v-else-if="!view.started" class="player-card overview-card">
        <h2>Короткое занятие из {{ view.total }} шагов</h2>
        <p>{{ view.session.description }}</p>
        <p>Сначала познакомьтесь с выражениями, затем отвечайте в удобном темпе. После каждого ответа можно прочитать объяснение.</p>
        <afr-button class="practice-primary" type="primary" :loading="busy" @click="start">{{ view.resume ? 'Продолжить занятие' : 'Начать занятие' }}</afr-button>
      </afr-card>
      <template v-else>
        <div class="step-progress">
          <p>Шаг {{ view.position }} из {{ view.total }}</p>
          <progress :value="view.position - (graded ? 0 : 1)" :max="view.total" :aria-label="'Пройдено шагов: ' + (view.position - (graded ? 0 : 1)) + ' из ' + view.total" />
        </div>
        <afr-card v-if="introductory" class="player-card">
          <introduction-cards :introductions="view.introductions" :vocabulary="view.vocabulary" :busy="busy" @complete="continueIntroduction" />
        </afr-card>
        <form v-else @submit.prevent="submit">
          <afr-card class="player-card">
            <h2 ref="heading" tabindex="-1" class="question-heading">{{ task.instruction }}</h2>
            <audio-control v-if="task.prompt?.audio" :key="view.presentation.presentation_id" :audio="task.prompt.audio" @unavailable="audioUnavailable = true" />
            <div v-if="promptVisible" class="question-prompt">
              <french-text v-if="task.prompt?.text_lang === 'fr'" :text="task.prompt.text" :transcription-ru="task.prompt.transcription_ru" />
              <p v-else-if="task.prompt?.text">{{ task.prompt.text }}</p>
              <french-text v-if="listening && task.prompt?.audio && (transcriptVisible || graded)" :text="task.prompt.audio.text" :transcription-ru="task.prompt.audio.transcription_ru" />
            </div>
            <div v-if="listening && !transcriptVisible && !graded" class="assistance-link">
              <afr-button :disabled="busy || locked" @click="revealTranscript">Показать текст и произношение</afr-button>
            </div>
            <pronunciation-notes :notes="visibleNotes" />
            <div class="task-controls">
              <component :is="component" v-if="component && draft" :key="view.presentation.presentation_id" v-model="draft" :task="task" :uid="view.presentation.presentation_id" :disabled="busy || (graded && !correcting) || locked" />
              <p v-else role="alert">Этот тип задания пока недоступен.</p>
            </div>
            <div v-if="!graded || correcting" class="help-actions">
              <afr-button v-if="(task.hint || task.has_hint) && !hintOpen" :disabled="busy || locked" @click="showHint">Подсказка</afr-button>
              <afr-button v-if="!referenceOpen" :disabled="busy || locked" @click="showReference">Вернуться к справке</afr-button>
            </div>
            <div v-if="hintOpen && (!graded || correcting)" class="hint-panel"><p>{{ task.hint }}</p><pronunciation-notes :notes="notesFor(task.hint)" /></div>
            <introduction-cards v-if="referenceOpen && (!graded || correcting)" class="reference-panel" :introductions="referenceIntroductions" :vocabulary="view.vocabulary" reference @close="referenceOpen = false; focusQuestion()" />
            <task-feedback v-if="graded && !correcting && result" :task="task" :result="result" />
            <afr-button v-if="graded && !correcting && !view.presentation.first_result.correct && !view.presentation.first_result.technical_skip" :disabled="busy || locked" @click="correcting = true; draft = emptyAnswer(task)">Попробовать исправить</afr-button>
            <p v-if="correcting">Исправление помогает потренироваться и не меняет первый балл.</p>
          </afr-card>
          <div class="player-actions">
            <div v-if="!graded || correcting" class="secondary-actions">
              <afr-button :disabled="busy || locked" @click="unknown">Не знаю — показать решение</afr-button>
              <afr-button v-if="audioUnavailable && task.prompt?.audio" :disabled="busy || locked" @click="technicalSkip">Пропустить: звук недоступен</afr-button>
            </div>
            <div class="primary-action">
              <p v-if="(!graded || correcting) && !ready" id="answer-needed" class="answer-needed">{{ missingMessage }}</p>
              <afr-button v-if="!graded || correcting" class="practice-primary" type="primary" native-type="submit" :disabled="!ready || busy || locked || !component" :loading="busy" :aria-describedby="!ready ? 'answer-needed' : undefined">Проверить</afr-button>
              <afr-button v-else class="practice-primary" type="primary" :loading="busy" :disabled="locked" @click="next">{{ view.position === view.total ? 'Завершить занятие' : 'Дальше' }}</afr-button>
            </div>
          </div>
        </form>
      </template>
    </template>
    <practice-report v-if="adapter.mode==='account' && view?.session_version_id" :key="view.session_version_id+':'+view.task?.id" :version-id="view.session_version_id" :task-id="view.task?.id" :run-id="adapter.mode==='account'?view.run_id:null" :answer="draft" />
  </section>
</template>
<style scoped>
.session-player { max-width: 880px; margin: 0 auto; padding: 1.5rem; color: #1e293b; overflow-wrap: anywhere; }
.preview-notice { padding: .75rem 1rem; background: #dbeafe; border: 1px solid #93c5fd; border-radius: .25rem; font-size: .875rem; margin-bottom: 1.25rem; }
.player-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
h1 { font-size: 1.65rem; line-height: 1.35; font-weight: bold; }
h2 { font-size: 1.25rem; font-weight: bold; line-height: 1.5; }
.level-label { color: #475569; font-size: .95rem; margin-bottom: .3rem; }
.step-progress { margin: 1rem 0 1.25rem; color: #475569; font-size: .95rem; }
progress { width: 100%; height: .4rem; margin-top: .65rem; accent-color: #19679b; border: 0; }
progress::-webkit-progress-bar { background: #cbdfee; border-radius: .25rem; }
progress::-webkit-progress-value { background: #19679b; border-radius: .25rem; }
.player-card :deep(.body) { padding: 1.5rem; border: 1px solid #bdd3e7; border-radius: .25rem; background: white; }
.player-header :deep(button) { flex-shrink: 0; }
.question-heading { margin-bottom: 1rem; scroll-margin-top: 80px; }
.question-prompt p { white-space: pre-line; }
.question-prompt { margin-top: .8rem; display: grid; gap: .75rem; }
.assistance-link { margin-top: .75rem; }
.task-controls { margin-top: 1.25rem; }
.help-actions, .secondary-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.help-actions { margin-top: 1rem; }
.reference-panel, .hint-panel { border-top: 1px solid #cbd5e1; padding-top: 1rem; margin-top: 1rem; }
.player-actions { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-top: 1.25rem; }
.primary-action { margin-left: auto; display: flex; flex-direction: column; align-items: flex-end; gap: .5rem; }
.answer-needed { font-size: .9rem; color: #475569; max-width: 28rem; }
.session-player :deep(button) { max-width: 100%; white-space: normal; }
.session-player :deep(:focus-visible) { outline: 3px solid #1d4ed8; outline-offset: 3px; }
.player-error { background: #fef2f2; border: 1px solid #b91c1c; padding: 1rem; margin-bottom: 1rem; border-radius: .25rem; }
.loading-message { padding: 2rem; }
.overview-card p, .result-card p { margin: .75rem 0; line-height: 1.6; }
.overview-card :deep(button), .result-card :deep(button) { margin-top: 1rem; }
.result-score { font-size: 1.5rem; font-weight: bold; }
@media (max-width: 600px) {
  .session-player { padding: 1rem .75rem; }
  .player-header { flex-direction: column; }
  h1 { font-size: 1.4rem; }
  .player-card :deep(.body) { padding: 1rem .75rem; }
  .player-actions { flex-direction: column; }
  .primary-action { margin-left: 0; width: 100%; align-items: stretch; }
}
@media (prefers-reduced-motion: reduce) { .session-player :deep(*) { transition: none !important; } }
</style>
