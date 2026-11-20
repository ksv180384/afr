<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrCard from '@/App/Components/Card/AfrCard.vue';
import { assertPlayerAdapter } from '@/App/Learning/playerAdapter.js';
import { answerComplete, emptyAnswer, questionSupport } from '@/App/Learning/playerUi.js';
import { createAudioPlayer } from '@/App/Learning/audioPlayer.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import PracticeReport from './PracticeReport.vue';
import ReviewSuggestionCard from './ReviewSuggestionCard.vue';
import { reviewWordCount } from '@/App/Learning/shortReview.js';
import FrenchText from './FrenchText.vue';
import AudioControl from './AudioControl.vue';
import AudioPreferences from './AudioPreferences.vue';
import PronunciationNotes from './PronunciationNotes.vue';
import IntroductionCards from './IntroductionCards.vue';
import TaskFeedback from './TaskFeedback.vue';
import SingleChoiceTask from './Tasks/SingleChoiceTask.vue';
import MatchingPairsTask from './Tasks/MatchingPairsTask.vue';
import FillBlankTask from './Tasks/FillBlankTask.vue';
import WordOrderTask from './Tasks/WordOrderTask.vue';
import ShortAnswerTask from './Tasks/ShortAnswerTask.vue';
import PracticeDate from './PracticeDate.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import QuestionOverlay from './QuestionOverlay.vue';

const props = defineProps({ locked: Boolean, timezone: String, routeOwner: Number, routeStore: Object, navigationBusy: Boolean, adapter: { type: Object, required: true }, audioUrls: { type: Object, default: () => ({}) } });
const emit = defineEmits(['exit', 'repeat', 'review', 'next-lesson', 'test-next', 'vocabulary']);
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
const languageSupportVisible = ref(false);
const pronunciationHidden = ref(false);
const transcriptGranted = ref(false);
const referenceOpen = ref(false);
const feedbackOpen = ref(false);
const referenceTrigger = ref(null);
const resultTrigger = ref(null);
const hintOpen = ref(false);
const audioUnavailable = ref(false);
const heading = ref(null);
const questionPane = ref(null);
const activeLesson = computed(() => view.value?.started && !view.value.finished && view.value.total > 0 && !view.value.exhausted);
const isPreview = computed(() => adapter.mode === 'preview');
const routeResult = ref(null);
const nextLesson = computed(() => routeResult.value?.active_run ?? routeResult.value?.candidate);
const routeError = ref('');
const routeLoading = ref(false);
const lessonFinished = computed(() => view.value?.finished && ['daily','extra'].includes(view.value.kind));
const loadResultRoute = async () => {
  if (!props.routeStore || routeLoading.value) return;
  routeLoading.value = true;
  routeError.value = '';
  try {
    const result = await props.routeStore.refresh(props.routeOwner);
    if (disposed || !result) return;
    if (result.home.content_error && !result.home.active_run) throw new Error(result.home.content_error);
    routeResult.value = result.home;
  } catch {
    if (!disposed) routeError.value = 'Не удалось проверить, какой урок доступен дальше.';
  } finally {
    if (!disposed) routeLoading.value = false;
  }
};
watch(lessonFinished, finished => { if (finished) loadResultRoute(); });

const reviewFinished = computed(() => view.value?.finished && view.value.kind === 'review');
const anotherReview = ref(null);
const anotherReviewError = ref(false);
const anotherReviewLoading = ref(false);
const loadAnotherReview = async () => {
  if (!props.routeStore || anotherReviewLoading.value) return;
  anotherReviewLoading.value = true;
  anotherReviewError.value = false;
  try {
    const plan = await props.routeStore.loadReviewPlan(props.routeOwner);
    if (!disposed) anotherReview.value = plan;
  } catch {
    if (!disposed) anotherReviewError.value = true;
  } finally {
    if (!disposed) anotherReviewLoading.value = false;
  }
};
watch(reviewFinished, finished => { if (finished) loadAnotherReview(); });
const task = computed(() => view.value?.task);
const corrections = computed(() => {
  const current = view.value?.corrections;
  if (!current || current.round !== undefined) return current;
  // A retried request can return a receipt created before correction rounds were introduced.
  return {...current, round:1, fixed_last_round:0,
    can_finish:current.started && current.position === current.total && view.value.presentation?.last_result?.correct === true};
});
const correctionIntro = computed(() => corrections.value && !corrections.value.started);
const lessonSupportsCorrections = computed(() => !!adapter.reviewMistakes && ['daily', 'extra'].includes(view.value?.kind));
const reviewMistakes = computed(() => lessonSupportsCorrections.value && view.value.mistakes_count > 0 && !corrections.value);
const stepPosition = computed(() => corrections.value?.position ?? view.value?.position);
const stepTotal = computed(() => corrections.value?.total ?? view.value?.total);
const nextLabel = computed(() => {
  if (stepPosition.value < stepTotal.value || reviewMistakes.value) return 'Дальше';
  return corrections.value && !corrections.value.can_finish ? 'К итогу круга' : 'Завершить';
});
const correcting = ref(false);
const graded = computed(() => Boolean(view.value?.presentation?.first_result));
const completedSteps = computed(() => stepPosition.value - (graded.value ? 0 : 1));
const result = computed(() => view.value?.presentation?.last_result?.solution ? view.value.presentation.last_result : view.value?.presentation?.first_result);
const resultTitle = computed(() => result.value?.technical_skip ? 'Шаг пропущен' : result.value?.correct
  ? (corrections.value ? 'Исправлено' : result.value.first_independent_success ? 'Верно' : 'Верно с помощью') : 'Неверно');
watch(graded, value => { feedbackOpen.value = value; });
watch(correcting, value => { if (value) feedbackOpen.value = false; });
const introductory = computed(() => !corrections.value && view.value?.started && !view.value.introduction_seen && view.value.introductions.length > 0);
const component = computed(() => ({ single_choice: SingleChoiceTask, matching_pairs: MatchingPairsTask, fill_blank: FillBlankTask, word_order: WordOrderTask, short_answer: ShortAnswerTask })[task.value?.type]);
const ready = computed(() => answerComplete(task.value, draft.value));
const listening = computed(() => task.value?.skill === 'listening');
const missingAudio = computed(() => listening.value && !task.value?.prompt?.audio);
const promptVisible = computed(() => !listening.value || transcriptVisible.value);
const listeningText = computed(() => task.value?.prompt?.audio?.text || task.value?.prompt?.text || '');
const listeningCue = computed(() => task.value?.prompt?.text_lang !== 'fr' && task.value?.prompt?.text && task.value.prompt.text !== listeningText.value
  ? task.value.prompt.text : 'Прослушайте фразу.');
const listeningTranscription = computed(() => task.value?.prompt?.audio?.transcription_ru || (task.value?.prompt?.text === listeningText.value ? task.value.prompt.transcription_ru : '') || '');
const referenceIntroductions = computed(() => {
  const introductions = view.value?.introductions.length ? view.value.introductions : [{ id: 'reference', text: 'Выражения для этого вопроса' }];
  return introductions.map(intro => ({ ...intro, vocabulary_ids: [...new Set([...(intro.vocabulary_ids ?? []), ...(task.value?.vocabulary_ids ?? [])])] }));
});
const notesFor = (surface) => (task.value?.pronunciation_notes ?? []).filter(({ fr }) => (surface ?? '').normalize('NFC').includes(fr.normalize('NFC')));
const visibleNotes = computed(() => notesFor([task.value?.instruction,
  promptVisible.value ? task.value?.prompt?.text : '',
  listening.value && promptVisible.value ? task.value?.prompt?.audio?.text : '',
].filter(Boolean).join(' ')));
// Keep the full listening prompt in the layout even before its transcript is revealed.
const reservedNotes = computed(() => listening.value ? notesFor([
  task.value?.instruction, task.value?.prompt?.text, task.value?.prompt?.audio?.text,
].filter(Boolean).join(' ')) : visibleNotes.value);
const reservePronunciationToggle = computed(() => listening.value && !!(reservedNotes.value.length || task.value?.prompt?.transcription_ru || task.value?.prompt?.audio?.transcription_ru));
const support = computed(() => questionSupport(view.value?.session?.level, task.value, {
  // The solution carries pronunciation after grading; keep the question's geometry unchanged.
  revealed: languageSupportVisible.value, hasNotes: !!visibleNotes.value.length,
  promptVisible: promptVisible.value && !(listening.value && transcriptVisible.value),
}));
const showPromptPronunciation = computed(() => !pronunciationHidden.value && (support.value.prompt || (listening.value && transcriptVisible.value)));
const promptHasPronunciation = computed(() => visibleNotes.value.length || (promptVisible.value && (task.value?.prompt?.transcription_ru || task.value?.prompt?.audio?.transcription_ru)));
const choicesHavePronunciation = computed(() => task.value?.text_before_transcription_ru || task.value?.text_after_transcription_ru ||
  ['choices', 'tokens', 'left_items', 'right_items'].some(key => task.value?.[key]?.some(item => item.transcription_ru)));
const hasPronunciation = computed(() => promptHasPronunciation.value || choicesHavePronunciation.value);
const pronunciationShown = computed(() => !pronunciationHidden.value && ((showPromptPronunciation.value && promptHasPronunciation.value) || (support.value.choices && choicesHavePronunciation.value)));
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
const focusQuestion = async () => { await nextTick(); heading.value?.focus({ preventScroll: true }); };
const restoredVersion = ref(0);
watch([() => view.value?.presentation?.presentation_id, () => corrections.value?.started, restoredVersion], () => {
  audio.stop();
  correcting.value = false;
  draft.value = task.value ? (view.value.local_draft || view.value.presentation?.answer ? JSON.parse(JSON.stringify(view.value.local_draft ?? view.value.presentation.answer)) : emptyAnswer(task.value)) : null;
  transcriptVisible.value = false;
  languageSupportVisible.value = false;
  pronunciationHidden.value = false;
  transcriptGranted.value = false;
  referenceOpen.value = false;
  feedbackOpen.value = graded.value;
  hintOpen.value = false;
  audioUnavailable.value = false;
  if (questionPane.value) questionPane.value.scrollTop = 0;
  focusQuestion();
});
watch(() => view.value, () => {
  if (!view.value?.restore_assistance || !view.value.assistance || corrections.value) return;
  languageSupportVisible.value = view.value.assistance.includes('language_support');
  // Restore permission to reopen assistance, not the transient transcript/overlay visibility.
  transcriptGranted.value = view.value.assistance.includes('audio_transcript') || (missingAudio.value && languageSupportVisible.value);
  hintOpen.value = view.value.assistance.includes('hint');
});
watch(draft, () => {
  if (adapter.storeDraft && task.value && view.value?.started && !graded.value && !correctionIntro.value) {
    adapter.storeDraft({presentation_id:view.value.presentation.presentation_id,answer:JSON.parse(JSON.stringify(draft.value))}).catch(() => {});
  }
}, {deep:true});
const start = () => perform(() => adapter.start(), focusQuestion);
const introPositionKey = () => {
  if (!view.value?.run_id || !view.value.presentation) return null;
  return `practice-intro:${adapter.mode}:${props.routeOwner ?? 'guest'}:${view.value.run_id}:${view.value.presentation.presentation_id}`;
};
const introPage = computed(() => {
  const key = introPositionKey();
  if (!key) return 0;
  try {
    const index = Number(window.localStorage.getItem(key));
    return Number.isInteger(index) && index > 0 ? index : 0;
  } catch {
    return 0;
  }
});
const rememberIntroPage = index => {
  const key = introPositionKey();
  if (!key) return;
  try { window.localStorage.setItem(key, String(index)); } catch {}
};
const continueIntroduction = () => {
  const id = view.value.presentation.presentation_id;
  const key = introPositionKey();
  return perform(() => adapter.load({ introductionSeen: id }), () => {
    if (key && view.value.introduction_seen) {
      try { window.localStorage.removeItem(key); } catch {}
    }
    return focusQuestion();
  });
};
const submit = () => {
  if (!ready.value || (graded.value && !correcting.value) || busy.value) return;
  const input = { client_event_id: crypto.randomUUID(), presentation_id: view.value.presentation.presentation_id, answer: JSON.parse(JSON.stringify(draft.value)) };
  audio.stop();
  return perform(() => adapter.answer(input), () => { correcting.value = false; feedbackOpen.value = true; });
};
const sendHelp = (fields, after) => {
  const input = { client_event_id: crypto.randomUUID(), presentation_id: view.value.presentation.presentation_id, ...fields };
  return perform(() => adapter.help(input), after ? () => {
    if (view.value.presentation?.presentation_id !== input.presentation_id) return;
    const confirmed = fields.event_type !== 'help' || isPreview.value ? view.value.presentation.last_result?.event_type === fields.event_type
      : view.value.assistance?.includes(fields.help_kind);
    if (confirmed) after();
  } : undefined);
};
const revealLanguageSupport = () => sendHelp({ event_type: 'help', help_kind: 'language_support' },
  () => { languageSupportVisible.value = true; pronunciationHidden.value = false; });
const togglePronunciation = () => {
  if (pronunciationShown.value) { pronunciationHidden.value = true; return; }
  if (!support.value.canReveal || languageSupportVisible.value || graded.value) {
    pronunciationHidden.value = false;
    if (graded.value) languageSupportVisible.value = true;
    return;
  }
  return revealLanguageSupport();
};
const toggleTranscript = () => {
  if (transcriptVisible.value) { transcriptVisible.value = false; return; }
  if (transcriptGranted.value || graded.value) { transcriptVisible.value = true; return; }
  return sendHelp({ event_type: 'help', help_kind: missingAudio.value ? 'language_support' : 'audio_transcript' }, () => {
    transcriptVisible.value = true;
    transcriptGranted.value = true;
    if (missingAudio.value) languageSupportVisible.value = true;
  });
};
const closeReference = async () => { referenceOpen.value = false; await nextTick(); referenceTrigger.value?.focus({ preventScroll: true }); };
const closeFeedback = async () => { feedbackOpen.value = false; await nextTick(); resultTrigger.value?.focus({ preventScroll: true }); };
const showReference = () => graded.value ? (referenceOpen.value = true)
  : sendHelp({ event_type: 'help', help_kind: 'reference' }, () => { referenceOpen.value = true; });
const showHint = () => graded.value ? (hintOpen.value = true)
  : sendHelp({ event_type: 'help', help_kind: 'hint' }, () => { hintOpen.value = true; });
const unknown = () => {
  audio.stop();
  return sendHelp({ event_type: 'unknown' }, () => { correcting.value = false; feedbackOpen.value = true; });
};
const technicalSkip = () => { audio.stop(); return sendHelp({ event_type: 'technical_skip', reason: 'audio_unavailable' }); };
const retryAnswer = () => {
  correcting.value = true;
  draft.value = emptyAnswer(task.value);
  focusQuestion();
};
const advance = () => {
  const id = view.value.presentation.presentation_id;
  return perform(() => adapter.load({ advanceFrom: id }), focusQuestion);
};
const next = () => {
  audio.stop();
  if (stepPosition.value < stepTotal.value || (corrections.value && !corrections.value.can_finish)) return advance();
  if (reviewMistakes.value) return perform(() => adapter.reviewMistakes(), focusQuestion);
  return perform(() => adapter.finish(), focusQuestion);
};
const exit = async () => {
  if (busy.value || props.navigationBusy) return;
  audio.stop();
  if (typeof adapter.saveDraft === 'function' && view.value?.presentation && !view.value.finished && (['account','guest'].includes(adapter.mode) || !graded.value)) {
    await perform(async () => { await adapter.saveDraft({ presentation_id: view.value.presentation.presentation_id, answer: JSON.parse(JSON.stringify(draft.value)) }); return null; },
      () => emit('exit'));
  } else emit('exit');
};
defineExpose({prepareAuthentication:async () => {
  if (busy.value) throw new Error('Дождитесь сохранения текущего действия.');
  if (adapter.saveDraft && view.value?.presentation && !view.value.finished) await adapter.saveDraft({presentation_id:view.value.presentation.presentation_id,answer:JSON.parse(JSON.stringify(draft.value))});
},reload:() => perform(() => adapter.load()),reconcile:() => perform(() => adapter.acceptServerState(), () => { restoredVersion.value++; })});
const loadForPlayback = async () => {
  const loaded = await adapter.load();
  if (!loaded.resume || !['account','guest'].includes(adapter.mode) || props.locked || disposed) return loaded;
  return {...await adapter.start(), restore_assistance: true};
};
onMounted(() => { perform(loadForPlayback); });
onBeforeUnmount(() => { disposed=true; audio.stop(); });
</script>
<template>
  <section class="session-player" :class="{ 'lesson-active': activeLesson }" :aria-busy="busy">
    <p v-if="isPreview" class="preview-notice">Предпросмотр · Учебный прогресс и словарь не записываются. После обновления страницы просмотр начнётся заново.</p>
    <div v-if="error" :class="feedback.enabled ? 'player-retry' : 'player-error'">
      <p role="alert">{{ error }}</p>
    </div>
    <afr-button v-if="retrying && retry" :disabled="busy" @click="retry()">Повторить отправку</afr-button>
    <afr-button v-if="!view && error" :disabled="busy" @click="exit">К моему пути</afr-button>
    <p v-if="!view && !error" role="status" class="loading-message">Загружаем занятие…</p>
    <template v-if="view">
      <header class="player-header">
        <div class="lesson-title">
          <h1 :title="view.session.title">{{ view.session.title }}</h1>
          <p v-if="!activeLesson" class="level-label">{{ view.session.level === 'PRE_A1' ? 'С нуля' : view.session.level }} · Около {{ view.session.duration_minutes }} мин</p>
        </div>
        <div class="header-tools">
          <audio-preferences v-if="activeLesson" class="player-audio-settings" popover />
          <afr-button v-if="!view.finished || isPreview" class="exit-lesson" :disabled="busy || navigationBusy" :aria-label="isPreview ? 'Выйти из предпросмотра' : 'Выйти с сохранением'" :title="isPreview ? 'Выйти из предпросмотра' : 'Выйти с сохранением'" @click="exit"><daily-practice-icon name="close" /></afr-button>
        </div>
      </header>
      <p v-if="!activeLesson && view.import_meta" class="level-label">Начато до входа · <practice-date :value="view.import_meta.client_started_at || view.import_meta.original_local_date" :timezone="timezone || view.timezone" /></p>
      <p v-else-if="!activeLesson && view.started_at" class="level-label">Начато <practice-date :value="view.started_at" :timezone="timezone || view.timezone" /></p>
      <pronunciation-notes v-if="!view.started" :notes="view.session.pronunciation_notes ?? []" />
      <afr-card v-if="view.exhausted || !view.total" class="player-card">
        <h2>{{ view.exhausted ? 'Доступные занятия закончились' : 'В этом занятии пока нет заданий' }}</h2><p>{{ view.exhausted ? 'Можно вернуться к выбору занятий. Новые материалы появятся позже.' : 'Выберите другой подготовленный материал.' }}</p>
        <afr-button @click="exit">К выбору занятий</afr-button>
      </afr-card>
      <afr-card v-else-if="view.finished" class="player-card result-card">
        <h2 ref="heading" tabindex="-1">{{ isPreview ? 'Предпросмотр завершён' : reviewFinished ? 'Повторили ' + reviewWordCount(view.total) : 'Занятие завершено' }}</h2>
        <p class="result-score">Самостоятельно: {{ view.summary.independent_correct_steps }} из {{ view.summary.total_steps }}</p>
        <p v-if="corrections">Исправлено заданий · {{ view.mistakes_count }}</p>
        <p v-if="view.summary.technical_skips">Технических пропусков: {{ view.summary.technical_skips }}. Они не являются языковыми ошибками.</p>
        <p v-if="view.finished_at || view.activity_date">Завершено <practice-date :value="view.finished_at || view.activity_date" :timezone="timezone || view.timezone" /></p>
        <p v-if="view.summary.integrations_pending?.length">Результат занятия сохранён. Учебные цели и переход уровня подтверждаются в аккаунте после входа.</p>
        <p v-if="view.summary.objectives?.enrollment_id">Освоено целей закреплённой программы: {{ view.summary.objectives.mastered }} из {{ view.summary.objectives.required }}. Завершение занятия и освоение цели учитываются отдельно.</p>
        <p v-if="view.summary.checkpoint" role="status">{{ view.summary.checkpoint.qualified ? 'Контроль прошёл порог самостоятельных ответов.' : 'Контроль пока не подтверждает переход: недостаточно самостоятельных ответов, был технический пропуск или некорректная исходная дата.' }}</p>
        <p v-if="view.summary.promotion" role="status">Уровень повышен до {{ view.summary.promotion.new_level }}. {{ view.summary.promotion.reason.message }}</p>
        <p v-if="isPreview">Результат относится к этому просмотру. Личный прогресс не изменён.</p>
        <p v-if="view.summary.words_added !== undefined">Добавлено выражений: {{ view.summary.words_added }}. Уже были в словаре: {{ view.summary.words_existing }}.</p>
        <div v-if="lessonFinished && routeStore" class="result-next">
          <p v-if="routeLoading" role="status">Проверяем следующий урок…</p>
          <template v-else-if="routeError">
            <p role="status">{{ routeError }}</p>
            <afr-button :disabled="navigationBusy" @click="loadResultRoute">Проверить снова</afr-button>
          </template>
          <button v-else-if="nextLesson" type="button" class="result-next-action"
            :disabled="locked || busy || navigationBusy" @click="emit('next-lesson')">
            <span class="result-next-copy">
              <span>{{ routeResult.active_run ? 'Продолжить начатое' : 'Следующее занятие' }}</span>
              <strong>{{ nextLesson.title }}</strong>
            </span>
            <span class="result-next-arrow"><daily-practice-icon name="chevron" /></span>
          </button>
          <p v-else-if="routeResult">На вашем уровне пока нет новых доступных уроков. Можно повторить пройденное.</p>
        </div>
        <review-suggestion-card v-if="lessonFinished && routeStore" :suggestion="routeResult?.review_suggestion"
          :owner="routeOwner" :store="routeStore" :busy="locked || busy || navigationBusy"
          @start="emit('review', 'suggested')" @dismissed="loadResultRoute" />
        <p v-if="view.self_check_words?.length">Для {{ view.self_check_words.length }} выражений доступна самопроверка в словаре без зачёта.</p>
        <div v-if="view.has_next_test_block || (reviewFinished && (anotherReview?.total || anotherReviewError))" class="result-extra">
          <afr-button v-if="view.has_next_test_block" type="primary" :disabled="busy || locked || navigationBusy"
            @click="emit('test-next', {continue_run_id:view.run_id})">Следующий блок слов</afr-button>
          <afr-button v-if="reviewFinished && anotherReview?.total" :disabled="locked || busy || navigationBusy || anotherReviewLoading"
            @click="emit('review', 'manual')">Ещё {{ reviewWordCount(anotherReview.total) }}</afr-button>
          <afr-button v-if="reviewFinished && anotherReviewError" :disabled="navigationBusy"
            @click="loadAnotherReview">Проверить, что ещё можно повторить</afr-button>
        </div>
        <div class="result-actions" :class="{ 'result-lesson-actions': lessonFinished }" role="group" aria-label="Действия после занятия">
          <afr-button :text="!isPreview && !reviewFinished" :type="isPreview || reviewFinished ? 'primary' : 'default'" :disabled="busy || navigationBusy" @click="exit">
            <daily-practice-icon v-if="!isPreview" name="list" />
            <span>{{ isPreview ? 'К выбору занятий' : 'К моему пути' }}</span>
          </afr-button>
          <afr-button v-if="!isPreview" text @click="emit('vocabulary')">
            <daily-practice-icon name="book" />
            <span>Мой словарь</span>
          </afr-button>
          <afr-button v-if="lessonFinished" text :disabled="locked || busy || navigationBusy" @click="emit('repeat', {session_id:view.session.id})">
            <daily-practice-icon name="reset" />
            <span>Пройти ещё раз</span>
          </afr-button>
        </div>
      </afr-card>
      <afr-card v-else-if="!view.started" class="player-card overview-card">
        <h2>Короткое занятие из {{ view.total }} шагов</h2>
        <p>{{ view.session.description }}</p>
        <p>Сначала познакомьтесь с выражениями, затем отвечайте в удобном темпе. После каждого ответа можно прочитать объяснение.</p>
        <afr-button class="practice-primary" type="primary" :loading="busy" @click="start">{{ view.resume ? 'Продолжить занятие' : 'Начать занятие' }}</afr-button>
      </afr-card>
      <section v-else-if="correctionIntro" class="correction-intro question-enter" aria-labelledby="correction-title">
        <div class="correction-emblem"><daily-practice-icon name="reset" /></div>
        <p class="correction-status"><daily-practice-icon name="check" />{{ corrections.round === 1 ? 'Основная часть пройдена' : `Круг ${corrections.round - 1} завершён` }}</p>
        <h2 id="correction-title" ref="heading" tabindex="-1">{{ corrections.round === 1 ? 'Работа над ошибками' : 'Продолжим работу над ошибками' }}</h2>
        <p class="correction-instructions">{{ corrections.round === 1
          ? 'Решите ещё раз задания, в которых ошиблись. В конце круга вернёмся к тем, которые ещё нужно исправить.'
          : 'Вы завершили круг. Теперь повторим задания, в которых ещё остались ошибки.' }}</p>
        <div class="correction-totals">
          <div v-if="corrections.round > 1" class="correction-count"><span>Исправлено за круг</span><strong>{{ corrections.fixed_last_round }}</strong></div>
          <div class="correction-count"><span>{{ corrections.round === 1 ? 'Заданий на повторение' : 'Осталось заданий' }}</span><strong>{{ corrections.total }}</strong></div>
        </div>
        <afr-button class="practice-primary" type="primary" :loading="busy" :disabled="locked" @click="advance">
          {{ corrections.round === 1 ? 'Начать работу над ошибками' : 'Начать следующий круг' }} <daily-practice-icon name="chevron" />
        </afr-button>
      </section>
      <template v-else>
        <div class="step-progress">
          <p>{{ corrections ? `Круг ${corrections.round} · Задание ` : 'Шаг ' }}{{ stepPosition }} из {{ stepTotal }}</p>
          <div class="step-progress-track" role="progressbar" aria-label="Пройдено заданий"
            aria-valuemin="0" :aria-valuenow="completedSteps" :aria-valuemax="stepTotal">
            <div class="step-progress-fill" :style="{ transform: `scaleX(${completedSteps / stepTotal})` }" />
          </div>
        </div>
        <afr-card v-if="introductory" :key="view.presentation.presentation_id" class="player-card introduction-card question-enter">
          <introduction-cards :introductions="view.introductions" :vocabulary="view.vocabulary"
            :busy="busy" :initial-page="introPage" @page-change="rememberIntroPage" @complete="continueIntroduction" />
        </afr-card>
        <form v-else class="exercise-form" @submit.prevent="submit">
          <div class="question-stage">
          <div ref="questionPane" class="question-pane" :inert="referenceOpen || (feedbackOpen && graded && !correcting && !!result)">
          <div :key="view.presentation.presentation_id" class="exercise-card question-enter">
            <h2 ref="heading" tabindex="-1" class="question-heading">{{ task.instruction }}</h2>
            <div class="prompt-row" :class="{ 'listening-prompt': listening }">
            <div v-if="listening" class="question-prompt listening-copy">
              <p class="listening-layer listening-cue" :class="{ concealed: transcriptVisible }" :aria-hidden="transcriptVisible || undefined">{{ listeningCue }}</p>
              <french-text class="listening-layer" :class="{ concealed: !transcriptVisible }" :aria-hidden="!transcriptVisible || undefined"
                :text="listeningText" :transcription-ru="listeningTranscription" :show-transcription="showPromptPronunciation" reserve-transcription />
            </div>
            <div v-else class="question-prompt">
              <french-text v-if="task.prompt?.text_lang === 'fr'" :text="task.prompt.text"
                :show-transcription="showPromptPronunciation" :reserve-transcription="listening"
                :transcription-ru="task.prompt.transcription_ru || (task.prompt.audio?.text === task.prompt.text ? task.prompt.audio?.transcription_ru : '')" />
              <p v-else-if="task.prompt?.text">{{ task.prompt.text }}</p>
            </div>
            <audio-control v-if="task.prompt?.audio" :key="view.presentation.presentation_id" :audio="task.prompt.audio" compact @available="audioUnavailable = false" @unavailable="audioUnavailable = true" />
            </div>
            <div v-if="listening && (task.prompt?.audio?.text || task.prompt?.text)" class="assistance-link">
              <button type="button" class="help-link" :disabled="busy || locked" :aria-expanded="transcriptVisible" @click="toggleTranscript">{{ transcriptVisible ? 'Скрыть текст' : 'Показать текст' }}</button>
            </div>
            <div v-if="reservedNotes.length" :class="{ concealed: !showPromptPronunciation || (listening && !promptVisible) }" :aria-hidden="!showPromptPronunciation || (listening && !promptVisible) || undefined">
              <pronunciation-notes :notes="reservedNotes" />
            </div>
            <div class="task-controls">
              <component :is="component" v-if="component && draft" :key="view.presentation.presentation_id" v-model="draft" :task="task" :uid="view.presentation.presentation_id"
                v-bind="task.type === 'short_answer' ? {} : {showTranscription:!pronunciationHidden && support.choices}" :disabled="busy || (graded && !correcting) || locked" />
              <p v-else role="alert">Этот тип задания пока недоступен.</p>
            </div>
            <div class="help-actions">
              <button v-if="hasPronunciation || reservePronunciationToggle" type="button" class="pronunciation-toggle" :class="{ concealed: !hasPronunciation }" :aria-hidden="!hasPronunciation || undefined" role="switch" :aria-checked="!!pronunciationShown" :disabled="busy || locked || !hasPronunciation" @click="togglePronunciation"><span class="switch-track" aria-hidden="true"><span /></span>Произношение</button>
              <button v-if="task.hint || task.has_hint" type="button" class="help-link" :disabled="busy || locked" :aria-expanded="hintOpen" @click="hintOpen ? hintOpen = false : showHint()">{{ hintOpen ? 'Скрыть подсказку' : 'Подсказка' }}</button>
              <button ref="referenceTrigger" type="button" class="help-link" :disabled="busy || locked" aria-haspopup="dialog" :aria-expanded="referenceOpen" @click="showReference">Справка и перевод</button>
            </div>
            <div v-if="hintOpen" class="hint-panel"><p>{{ task.hint }}</p><pronunciation-notes :notes="notesFor(task.hint)" /></div>
          </div>
          </div>
          <question-overlay v-if="referenceOpen" title="Справка к вопросу" full @close="closeReference">
            <introduction-cards :introductions="referenceIntroductions" :vocabulary="view.vocabulary" reference hide-heading @close="closeReference" />
          </question-overlay>
          <question-overlay v-else-if="feedbackOpen && graded && !correcting && result" :key="view.presentation.presentation_id" :title="resultTitle" :tone="result.technical_skip ? 'skipped' : result.correct ? 'success' : 'review'" close-label="Скрыть ответ" @close="closeFeedback">
            <task-feedback :task="task" :result="result" hide-heading />
          </question-overlay>
          </div>
          <div class="answer-dock">
            <p v-if="!graded || correcting" id="answer-needed" class="sr-only">{{ ready ? 'Ответ готов к проверке' : missingMessage }}</p>
            <p v-if="correcting" class="sr-only">Исправление помогает потренироваться и не меняет первый балл.</p>
            <div class="player-actions">
            <div class="secondary-actions">
              <afr-button v-if="!graded || correcting" text :disabled="busy || locked" @click="unknown">Не знаю</afr-button>
              <afr-button v-else-if="!lessonSupportsCorrections && view.kind !== 'review' && !view.presentation.first_result.correct && !view.presentation.first_result.technical_skip" text :disabled="busy || locked" @click="retryAnswer">Исправить</afr-button>
              <button v-if="graded && !correcting && result" ref="resultTrigger" type="button" class="help-link" aria-haspopup="dialog" :aria-expanded="feedbackOpen" :aria-label="feedbackOpen ? 'Скрыть ответ' : 'Показать ответ'" @click="feedbackOpen ? closeFeedback() : feedbackOpen = true">Ответ</button>
              <afr-button v-if="!corrections && !graded && (missingAudio || (audioUnavailable && task.prompt?.audio))" text :disabled="busy || locked" title="Пропустить: звук недоступен" aria-label="Пропустить: звук недоступен" @click="technicalSkip">Без звука</afr-button>
            </div>
            <div class="primary-action">
              <afr-button v-if="!graded || correcting" class="practice-primary" type="primary" native-type="submit" :disabled="!ready || busy || locked || !component" :loading="busy" :aria-describedby="!ready ? 'answer-needed' : undefined">Проверить</afr-button>
              <afr-button v-else class="practice-primary" type="primary" :loading="busy" :disabled="locked" @click="next">{{ nextLabel }}<daily-practice-icon name="chevron" /></afr-button>
            </div>
            </div>
          </div>
        </form>
      </template>
    </template>
    <practice-report v-if="adapter.mode==='account' && view?.session_version_id" :key="view.session_version_id+':'+view.task?.id" :version-id="view.session_version_id" :task-id="view.task?.id" :run-id="adapter.mode==='account'?view.run_id:null" :answer="draft" />
  </section>
</template>
<style scoped>
.session-player { max-width: 820px; margin: 0 auto; padding: var(--player-padding, 1rem); color: var(--practice-ink, #1e293b); overflow-wrap: anywhere; }
.correction-intro {
  display: grid;
  justify-items: center;
  align-content: center;
  gap: 1rem;
  margin: auto 0;
  padding: clamp(1.5rem, 5vw, 3.5rem);
  border: 1px solid #c8dcec;
  border-radius: 1.5rem;
  background: radial-gradient(ellipse at 50% 0, #deefff, transparent 65%), #f9fcff;
  box-shadow: 0 16px 40px #2851770e;
  text-align: center;
}
.correction-intro h2 { margin: 0; color: #143653; font-size: clamp(1.45rem, 5vw, 2.25rem); line-height: 1.2; }
.correction-emblem { display: grid; place-items: center; width: 5rem; height: 5rem; border-radius: 1.5rem; background: #d8eaff; color: #155ca7; }
.correction-emblem :deep(svg) { width: 2.6rem; height: 2.6rem; }
.correction-status { display: flex; align-items: center; gap: .4rem; color: #266247; font-size: .875rem; font-weight: 650; }
.correction-status :deep(svg) { width: 1rem; height: 1rem; }
.correction-instructions { max-width: 40ch; color: #49657e; line-height: 1.65; }
.correction-totals { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; }
.correction-count { display: flex; align-items: center; gap: .65rem; padding: .5rem .85rem; border: 1px solid #d4e4f2; border-radius: .75rem; color: #294c6f; background: white; font-size: .875rem; }
.correction-count strong { color: #165ca7; font-size: 1.5rem; line-height: 1; }
.correction-intro .practice-primary { width: auto; margin-top: .3rem; }
.preview-notice { padding: .75rem 1rem; background: #dbeafe; border: 1px solid #93c5fd; border-radius: .25rem; font-size: .875rem; margin-bottom: 1.25rem; }
.player-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem 1rem; margin-bottom: .5rem; flex-shrink: 0; }
.lesson-title { min-width: 0; }
h1 { font-size: clamp(1.05rem, .9rem + .5vw, 1.3rem); line-height: 1.4; font-weight: 700; }
.lesson-active h1 { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.header-tools { position: relative; display: flex; align-items: center; gap: .6rem; flex-shrink: 0; }
.header-tools :deep(.audio-preferences.popover) { position: static; }
.exit-lesson { width: 40px; height: 44px; padding: 0; background: transparent; border-color: transparent; }
.exit-lesson :deep(svg) { margin: 0; width: 20px; }
.player-audio-settings { margin: 0; }
h2 { font-size: 1.25rem; font-weight: bold; line-height: 1.5; }
.level-label { color: #475569; font-size: .95rem; margin-bottom: .3rem; }
.step-progress { display: flex; align-items: center; gap: .85rem; margin: 0 0 .8rem; color: #64748b; font-size: .8rem; flex-shrink: 0; }
.step-progress p { flex-shrink: 0; font-variant-numeric: tabular-nums; }
.step-progress-track { order: -1; flex: 1; min-width: 0; height: .5rem; border-radius: 1rem; background: #cbdfee; overflow: hidden; }
.step-progress-fill { height: 100%; background: #19679b; transform-origin: left center; }
.player-card :deep(.body) { padding: var(--practice-pad, 1.25rem); border: 1px solid var(--practice-border, #bdd3e7); border-radius: var(--practice-radius, .85rem); background: white; }
.player-header :deep(button) { justify-self: start; }
.question-heading { margin-bottom: .75rem; scroll-margin-top: 1rem; }
.question-prompt p { white-space: pre-line; }
.prompt-row { display: flex; align-items: flex-start; gap: .75rem; }
.question-prompt { display: grid; gap: .75rem; min-width: 0; }
.listening-copy { gap: 0; }
.listening-layer { grid-area: 1 / 1; }
.listening-cue { align-self: start; padding-top: .35rem; color: #64748b; line-height: 1.5; }
.prompt-row > .practice-audio { margin-left: .25rem; }
.listening-prompt > .practice-audio { order: -1; margin-left: 0; }
.concealed { visibility: hidden; }
.assistance-link { margin-top: .25rem; }
.task-controls { margin-top: 1.25rem; }
.help-actions, .secondary-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.secondary-actions { flex-wrap: nowrap; align-items: center; }
.secondary-actions :deep(button) { font-size: .8rem; padding-inline: .35rem; }
.help-actions { margin-top: .75rem; align-items: center; gap: .4rem 1rem; }
.hint-panel { border-top: 1px solid #cbd5e1; padding-top: 1rem; margin-top: 1rem; }
.lesson-active { height: calc(100svh - 1.5rem); min-height: 540px; display: flex; flex-direction: column; }
.exercise-form { display: grid; grid-template-rows: minmax(0, 1fr) auto; flex: 1; min-height: 0; gap: .75rem; }
.question-stage { position: relative; display: grid; min-height: 0; }
.question-pane {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden auto;
  scrollbar-gutter: stable;
  overflow-anchor: none;
  border: 1px solid var(--practice-border, #bdd3e7);
  border-radius: var(--practice-radius, .85rem);
  background: white;
}
.exercise-card { flex: 1 0 auto; padding: clamp(1rem, 2.8vw, 1.75rem); }
.introduction-card { min-height: 0; overflow-y: auto; flex: 1; }
.answer-dock { min-height: 68px; padding: .55rem .8rem; background: #fff; border: 1px solid #d5e1ec; border-radius: .85rem; }
.player-actions { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
.primary-action { margin-left: auto; flex-shrink: 0; }
.primary-action :deep(button) { min-width: 166px; min-height: 48px; font-weight: 650; justify-content: center; gap: .5rem; }
.primary-action :deep(svg) { width: 18px; margin: 0; }
.session-player .help-link, .session-player .pronunciation-toggle { display: inline-flex; align-items: center; gap: .5rem; min-height: 36px; padding: .25rem .15rem; background: transparent; border: 0; color: #51718e; font-size: .8rem; line-height: 1.3; }
.help-link:hover { color: #165ca7; text-decoration: underline; }
.help-link:disabled, .pronunciation-toggle:disabled { opacity: .5; }
.switch-track { display: flex; align-items: center; width: 28px; height: 17px; padding: 2px; border-radius: 12px; background: #cbd6e1; flex-shrink: 0; }
.switch-track span { width: 13px; height: 13px; background: #fff; border-radius: 50%; box-shadow: 0 1px 2px #18344c30; }
.pronunciation-toggle[aria-checked="true"] .switch-track { background: #3979b8; }
.pronunciation-toggle[aria-checked="true"] .switch-track span { margin-left: auto; }
.session-player :deep(button) { max-width: 100%; white-space: normal; }
.session-player :deep(:focus-visible) { outline: 3px solid #1d4ed8; outline-offset: 3px; }
.player-error { background: #fef2f2; border: 1px solid #b91c1c; padding: 1rem; margin-bottom: 1rem; border-radius: .25rem; }
.loading-message { padding: 2rem; }
.overview-card p, .result-card p { margin: .75rem 0; line-height: 1.6; }
.overview-card :deep(button) { margin-top: 1rem; }
.result-score { font-size: 1.5rem; font-weight: bold; }
.result-next { margin-top: 1rem; }
.result-next > p { margin: 0; color: var(--practice-muted, #475569); font-size: .875rem; }
.result-next > .result-next-action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  width: 100%;
  padding: .7rem .85rem;
  border: 1px solid #cddff2;
  border-radius: var(--practice-radius, .85rem);
  background: #f0f6ff;
  color: #19446d;
  text-align: start;
}
.result-next-action:enabled:hover { background: #e5f0ff; border-color: #a9c8ea; }
.result-next-action:disabled { opacity: .6; cursor: not-allowed; }
.result-next-copy { display: grid; gap: .1rem; min-width: 0; }
.result-next-copy > span { color: #486a8a; font-size: .75rem; }
.result-next-copy > strong { font-size: 1rem; font-weight: 700; line-height: 1.35; }
.result-next-arrow {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2.125rem;
  height: 2.125rem;
  border-radius: .55rem;
  background: var(--practice-blue, #165ca7);
  color: white;
}
.result-next-arrow :deep(svg), .result-actions :deep(svg) { width: 1rem; height: 1rem; margin: 0; }
.result-extra { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.result-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .25rem .5rem;
  margin-top: .75rem;
  padding-top: .4rem;
  border-top: 1px solid #e1e9f1;
}
.result-actions > button { gap: .4rem; min-height: 44px; font-size: .8125rem; line-height: 1.25; }
@media (prefers-reduced-motion: no-preference) {
  .question-enter { animation: question-enter 180ms ease-out; transform-origin: center top; }
  .step-progress-fill { transition: transform 280ms ease-out; }
}
@keyframes question-enter {
  from { opacity: .55; transform: scale(.99); }
  to { opacity: 1; transform: none; }
}
@media (max-width: 600px) {
  .player-header { gap: .5rem; }
  .header-tools { gap: .25rem; }
  .exercise-form { gap: .5rem; }
  .answer-dock { padding: .55rem .65rem; }
  .primary-action :deep(button) { min-width: 130px; }
  .question-heading { font-size: 1.15rem; line-height: 1.4; }
  .secondary-actions { font-size: .85rem; }
  .result-lesson-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .2rem; }
  .result-lesson-actions > button { flex-direction: column; justify-content: flex-start; padding: .45rem .15rem; }
}
@media (prefers-reduced-motion: reduce) { .session-player :deep(*) { transition: none !important; } }
</style>
