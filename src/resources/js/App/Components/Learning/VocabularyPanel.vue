<script setup>
import { randomUuid } from '@/App/Learning/randomUuid.js';
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import useClickOutside from '@/Composables/useClickOutside.js';
import { rememberTemporaryGuest, registerGuestAuthentication } from '@/App/Learning/guestSyncState.js';
import { Link, router } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import PracticeReport from './PracticeReport.vue';
import PracticeDate from './PracticeDate.vue';
import FrenchText from './FrenchText.vue';
import AudioControl from './AudioControl.vue';
import AudioPreferences from './AudioPreferences.vue';
import { lookupRecording } from '@/App/Learning/recordingLookup.js';
import { createAudioPlayer } from '@/App/Learning/audioPlayer.js';
import { reviewDurationLabel, reviewWordCount } from '@/App/Learning/shortReview.js';
import ReviewSuggestionSetting from './ReviewSuggestionSetting.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { addGuestRun, recordGuestEvent } from '@/App/Learning/guestProgress.js';
import { filterGuestWords, guestWordData, guestTestPlan, guestTestSource } from '@/App/Learning/guestVocabulary.js';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { startAccountRun, deliverAccountQueue } from '@/App/Learning/accountAdapter.js';
import { refreshGuestDictionary } from '@/App/Learning/guestDictionaryUpdates.js';
import { accountTransport, guestContentTransport, vocabularyTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { browserTimezone } from '@/App/Learning/practiceTime.js';

const props = defineProps({authUser:Object,guestRepository:Object,routeStore:Object,addedInRun:String});
const emit = defineEmits(['open-guest-run','back']);
const words = ref([]);
const pagination = ref({current_page:1,last_page:1,total:0});
const sessions = ref([]);
const defaultFilters = {filter:'all',source_session:'',q:''};
const filters = ref({...defaultFilters});
const addedInRun = ref(props.addedInRun);
const requestFilters = () => Object.fromEntries(Object.entries({...filters.value,added_in_run:addedInRun.value}).filter(([,value])=>value));
const appliedFilters = ref(requestFilters());
const shortPlan = ref(null);
const shortError = ref('');
const settingsOpen = ref(false);
const activePopover = ref(null);
const headerTools = ref(null);
const popoverPanel = ref(null);
let popoverTrigger;
const closePopover = (restoreFocus = false) => {
  activePopover.value = null;
  if (restoreFocus) popoverTrigger?.focus();
};
const togglePopover = async (name, event) => {
  if (activePopover.value === name) return closePopover(true);
  popoverTrigger = event.currentTarget;
  activePopover.value = name;
  if (name === 'settings') settingsOpen.value = true;
  await nextTick();
  popoverPanel.value?.focus();
};
useClickOutside(headerTools, () => closePopover());
const busy = ref(false);
const searching = ref(false);
const markingWordId = ref(null);
const markingField = ref(null);
const isMarking = (word, field) => markingWordId.value === word.id && markingField.value === field;
const error = ref('');
const saving = ref({kind:'loading',message:''});
const pendingStart = ref(false);
const feedback = usePracticeFeedback({
  source: 'practice-vocabulary', error,
  status: saving,
});
const startingTest = ref(null);
const revealed = ref({});
const expandedWords = ref({});
const clock = ref(Date.now());
const due = word => Date.parse(word.next_review_at) <= clock.value;
let clockTimer;
const filterBar = ref(null);
const filterDropdown = ref(null);
const filterWidth = ref(0);
const inlineStatus = computed(() => filterWidth.value >= 500);
const inlineSource = computed(() => filterWidth.value >= 680);
const canResetFilters = computed(() => Object.entries(defaultFilters).some(([key, value]) =>
  filters.value[key] !== value || (appliedFilters.value[key] ?? value) !== value));
const overflowActive = computed(() => (!inlineStatus.value && filters.value.filter !== 'all')
  || (!inlineSource.value && !!filters.value.source_session));
let filterObserver;
const closeFilters = () => {
  if (filterDropdown.value) filterDropdown.value.open = false;
};
useClickOutside(filterDropdown, event => {
  // The searchable select renders its list in body, outside the filter panel.
  if (!event.composedPath().some(element => element.id === 'vocabulary-source-options')) closeFilters();
});
onMounted(() => {
  filterWidth.value = filterBar.value.clientWidth;
  filterObserver = new ResizeObserver(([entry]) => {
    filterWidth.value = entry.contentRect.width;
  });
  filterObserver.observe(filterBar.value);
  clockTimer = setInterval(() => { clock.value = Date.now(); }, 60000);
});
onBeforeUnmount(() => { filterObserver?.disconnect(); clearInterval(clockTimer); });
let repository;
let unsubscribe;
let unregisterAuthentication;
let queueSyncing = false;
let disposed = false;
let openRecoveredStart = false;
const homeStore = props.routeStore ?? usePracticeHome();
const accountRepository = homeStore.accountRepository;
const owner = props.authUser?.id;
const invalidateHome = () => {
  homeStore.invalidate(owner ?? null);
};
const timezone=ref(browserTimezone());
const audio = createAudioPlayer({ lookupRecording });
provide('practiceAudioPlayer',audio);
const onStatus = value => { if (value.kind !== 'sending' && (saving.value.kind !== value.kind || saving.value.message !== value.message)) saving.value = value; };
const saved = () => onStatus(repository?.temporary ? {kind:'temporary',message:'Временный режим: данные исчезнут при закрытии страницы.'}
  : {kind:'idle',message:''});
const perform = async (action) => {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { await action(); }
  catch (failure) {
    error.value = failure.response?.data?.message ?? failure.message;
    if (!owner && !failure.code) onStatus({kind:'storage',message:'Не удалось сохранить или прочитать данные браузера. Не закрывайте страницу.'});
  }
  finally { busy.value = false; }
};
const load = async (page = pagination.value.current_page) => {
  const current = appliedFilters.value;
  if (owner) {
    const result = await vocabularyTransport.list({...current,page});
    words.value = result.words;
    pagination.value = result.pagination;
    sessions.value = result.source_session_options;
  } else {
    const state = await repository.read();
    const all = filterGuestWords(state, current);
    const titles = new Map(Object.values(state.runs).map(run => [run.session_version_id, run.snapshot.title]));
    const sources = Object.values(state.vocabulary).flatMap(word => word.sources ?? [])
      .sort((a, b) => b.version - a.version);
    const sessionOptions = new Map();
    for (const source of sources) {
      if (!sessionOptions.has(source.session_id)) {
        sessionOptions.set(source.session_id, {id:source.session_id,
          title:titles.get(source.session_version_id) || source.session_title || 'Изученное занятие'});
      }
    }
    sessions.value = [...sessionOptions.values()].sort((a, b) => a.title.localeCompare(b.title, 'ru'));
    pagination.value = {current_page:page,last_page:Math.max(1,Math.ceil(all.length/30)),total:all.length};
    words.value = all.slice((page-1)*30,page*30).map(word => {
      const data = guestWordData(word);
      data.sources = data.sources.map(source => ({...source, session_title: titles.get(source.session_version_id)}));
      return data;
    });
  }
  clock.value = Date.now();
  if (owner) pendingStart.value = (await accountRepository.pending(owner,null)).length>0;
};
const loadShortPlan = async () => {
  try {
    shortPlan.value = await homeStore.loadReviewPlan(owner ?? null, appliedFilters.value);
    shortError.value = '';
  } catch (failure) {
    if (!disposed) {
      shortPlan.value = null;
      shortError.value = failure.response?.data?.message ?? failure.message;
    }
  }
};
const refresh = () => perform(async () => {
  searching.value = true;
  try {
    closeFilters();
    appliedFilters.value = requestFilters();
    await Promise.all([load(1), loadShortPlan()]);
  } finally {
    searching.value = false;
  }
});
const resetFilters = () => {
  filters.value = {...defaultFilters};
  return refresh();
};
const openEntireDictionary = () => {
  addedInRun.value = null;
  return resetFilters();
};
const topPagination = ref(null);
const turnPage = async (page, fromBottom = false) => {
  await perform(() => load(page));
  if (fromBottom && pagination.value.current_page === page) {
    await nextTick();
    topPagination.value?.querySelector('[aria-current="page"]')?.focus();
  }
};
const mark = (word, field = 'needs_review') => perform(async () => {
  markingWordId.value = word.id;
  markingField.value = field;
  try {
    const target = !word[field];
    if (owner) {
      if (field === 'is_difficult') await vocabularyTransport.markDifficult(word.id, target);
      else await vocabularyTransport.mark(word.id, target);
    }
    else await repository.mutate((state) => {
      if(state.bound_user_id !== null) throw Object.assign(new Error('Продолжите работу со словарём в аккаунте.'),{code:'bound_owner'});
      const current = state.vocabulary[word.lexeme_key];
      if (!current) throw new Error('Выражение отсутствует в этом браузере.');
      current[field] = target;
      recordGuestEvent(state,{action:'vocabulary_mark',lexeme_key:word.lexeme_key,[field]:target,client_event_id:randomUuid(),client_occurred_at:new Date().toISOString()});
    });
    invalidateHome();
    await Promise.all([load(1), loadShortPlan()]);
    onStatus({kind:'saved',message:field === 'is_difficult'
      ? (target ? 'Слово добавлено в сложные.' : 'Отметка сложного слова снята.')
      : (target ? 'Выражение отмечено для повторения.' : 'Отметка повторения снята.')});
  } finally {
    markingWordId.value = null;
    markingField.value = null;
  }
});
const openRun = async id => {
  if (!owner && props.guestRepository) emit('open-guest-run', id);
  else await new Promise(resolve => router.visit('/daily/' + (owner ? 'run/' : 'guest/') + id, {onFinish: () => resolve()}));
};
const startTestRun = async ids => {
  if (owner) {
    const cachedProfile = homeStore.state.owner === owner && homeStore.state.ready ? homeStore.state.home?.profile : null;
    const profile = cachedProfile ?? await accountTransport.profile();
    if (profile.owner_user_id !== owner) throw new Error('Войдите в исходный аккаунт.');
    const input = {client_event_id:randomUuid(),expected_revision:profile.revision,kind:'vocabulary_test',word_ids:ids,timezone:profile.persisted ? profile.timezone : browserTimezone()};
    try {
      openRecoveredStart = true;
      const state = await startAccountRun({ownerUserId:owner,input,profile,repository:accountRepository,transport:accountTransport,onStatus,onMutation:invalidateHome});
      openRecoveredStart = false;
      if (!disposed) await openRun(state.run_id);
    } finally { pendingStart.value = (await accountRepository.pending(owner,null)).length>0; }
  } else {
    const runId = randomUuid(); const tabId = randomUuid(); const seed = randomUuid(); const eventId = randomUuid();
    await repository.mutate((state) => {
      const source = guestTestSource(state,ids,seed,randomUuid);
      addGuestRun(state,{source,runId,presentationIds:source.snapshot.tasks.map(randomUuid),eventId,tabId,now:new Date().toISOString(),kind:'vocabulary_test'});
    });
    invalidateHome();
    if (!disposed) await openRun(runId);
  }
};
const startTest = mode => perform(async () => {
  if (pendingStart.value) return;
  startingTest.value = mode;
  try {
    const testFilters = { ...appliedFilters.value };
    if (mode === 'difficult') testFilters.filter = 'difficult';
    const plan = owner
      ? await vocabularyTransport.plan(testFilters)
      : guestTestPlan(await repository.read(), testFilters);
    if (disposed) return;
    if (plan.total) {
      await startTestRun(plan.word_ids);
    } else {
      let message = 'В этой выборке пока нет слов для теста.';
      if (plan.self_check_words.length) message = 'Для этих слов доступна только самопроверка. В словаре нажмите «Показать перевод».';
      else if (testFilters.filter === 'difficult') message = 'В этой выборке нет сложных слов. Отметьте звёздочкой слова, которые хотите потренировать.';
      saving.value = {kind:'info', message};
    }
  } finally {
    startingTest.value = null;
  }
});
const startShortReview = () => perform(async () => {
  const plan = shortPlan.value;
  if (!plan?.total || plan.active_run_id || pendingStart.value) return;
  try {
    openRecoveredStart = true;
    const path = await homeStore.startReview(owner ?? null, {filters:appliedFilters.value, wordIds:plan.word_ids, onStatus});
    openRecoveredStart = false;
    if (!disposed) await openRun(path.split('/').pop());
  } catch (failure) {
    await loadShortPlan();
    throw failure;
  } finally {
    if (owner) pendingStart.value = (await accountRepository.pending(owner, null)).length > 0;
  }
});
const prepareAuthentication = async () => {
  if (busy.value || !repository) throw new Error('Дождитесь сохранения текущего действия.');
  await repository.mutate((state)=>{state.return_path='/daily/vocabulary';});
  if(repository.temporary) rememberTemporaryGuest(await repository.read());
};
const authenticate = () => { prepareAuthentication().then(() => router.visit('/login?practice=1')).catch((failure)=>{error.value=failure.message;}); };
const recover = async () => {
  if (!owner || disposed || queueSyncing || busy.value) return;
  queueSyncing=true;
  try {
    const state=await deliverAccountQueue({ownerUserId:owner,runId:null,repository:accountRepository,transport:accountTransport,onStatus,onMutation:invalidateHome});
    pendingStart.value=(await accountRepository.pending(owner,null)).length>0;
    if (state && !disposed) {
      if (openRecoveredStart) { openRecoveredStart = false; await openRun(state.run_id); }
      else await loadShortPlan();
    }
  } catch (failure) {
    error.value = failure.response?.data?.message ?? failure.message;
  } finally {
    try { pendingStart.value=(await accountRepository.pending(owner,null)).length>0; } catch {}
    queueSyncing=false;
  }
};
onMounted(async () => {
  await perform(async () => {
    if (owner) {
      const profile=await accountTransport.profile();
      if (profile.owner_user_id !== owner) throw new Error('Войдите в свой аккаунт.');
      if (profile.persisted) timezone.value=profile.timezone;
    }
    if (!owner) {
      repository = props.guestRepository ?? homeStore.guestRepository;
      if(!props.guestRepository) await repository.archiveBound();
      unsubscribe=repository.subscribe('vocabulary-'+randomUuid(),(notice)=>{if(notice.bound || notice.rotated) router.visit('/daily/vocabulary');});
      await refreshGuestDictionary(repository, guestContentTransport, await repository.initialize());
      unregisterAuthentication = registerGuestAuthentication(prepareAuthentication);
    }
    await Promise.all([load(), loadShortPlan()]);
    saved();
  });
  if (owner && pendingStart.value) await recover();
});
onMounted(() => window.addEventListener('online',recover));
onBeforeUnmount(() => { disposed=true; window.removeEventListener('online',recover); unregisterAuthentication?.(); unsubscribe?.(); audio.stop(); });
</script>
<template>
  <section class="vocabulary-panel" @keydown.esc="activePopover && closePopover(true)">
    <header class="vocabulary-header">
      <h2>Личный словарь</h2>
      <div ref="headerTools" class="header-tools">
        <button type="button" class="utility-button" title="Настройки повторения" aria-label="Настройки повторения"
          aria-haspopup="dialog" :aria-expanded="activePopover === 'settings'" aria-controls="vocabulary-popover"
          @click="togglePopover('settings', $event)"><daily-practice-icon name="settings" /></button>
        <audio-preferences popover compact @click="closePopover()" />
        <button type="button" class="utility-button" title="Как работает повторение" aria-label="Как работает повторение"
          aria-haspopup="dialog" :aria-expanded="activePopover === 'help'" aria-controls="vocabulary-popover"
          @click="togglePopover('help', $event)"><daily-practice-icon name="help" /></button>
        <div v-if="activePopover" id="vocabulary-popover" ref="popoverPanel" class="header-popover" role="dialog"
          tabindex="-1" :aria-label="activePopover === 'help' ? 'Как работает повторение' : 'Настройки повторения'">
          <div class="popover-heading">
            <h3>{{ activePopover === 'help' ? 'Как работает повторение' : 'Настройки повторения' }}</h3>
            <button type="button" class="utility-button" aria-label="Закрыть" @click="closePopover(true)"><daily-practice-icon name="close" /></button>
          </div>
          <template v-if="activePopover === 'help'">
            <p>Первое повторение — через сутки. Ошибка или ответ «Не знаю» сбрасывает серию и возвращает слово в «Пора повторить».</p>
            <p>Правильные ответы с первой попытки без подсказок увеличивают интервалы до 1, 3, 7, 14 и 30 дней. Засчитывается один успех за прохождение и не чаще раза в 24 часа.</p>
            <p>После трёх успехов отметка повторения снимается. Кнопка с книгой позволяет управлять ею вручную.</p>
            <p>Звёздочкой отмечайте сложные слова. Она сохраняется до снятия вручную. «Тренировать сложные» собирает их в блоки до 10 вопросов и чередует доступные задания на значение и контекст.</p>
            <p v-if="!authUser" class="guest-note">Слова сохраняются в этом браузере и переносятся в аккаунт после входа. <a href="/login?practice=1" @click.prevent="authenticate">Войти в аккаунт</a></p>
          </template>
          <review-suggestion-setting v-else-if="settingsOpen" :auth-user="authUser" :guest-repository="repository" />
        </div>
      </div>
    </header>
    <div v-if="appliedFilters.added_in_run" class="vocabulary-scope">
      <strong>Новые выражения из занятия</strong>
      <div class="vocabulary-scope-actions">
        <afr-button v-if="guestRepository" class="vocabulary-result-link" :disabled="busy" @click="emit('open-guest-run', appliedFilters.added_in_run)">
          К результату занятия
        </afr-button>
        <Link v-else class="vocabulary-result-link" :href="`/daily/${owner ? 'run' : 'guest'}/${encodeURIComponent(appliedFilters.added_in_run)}`">
          К результату занятия
        </Link>
        <afr-button v-if="guestRepository" link :disabled="busy" @click="openEntireDictionary">Весь словарь</afr-button>
        <Link v-else href="/daily/vocabulary">Весь словарь</Link>
      </div>
    </div>
    <div class="vocabulary-toolbar">
      <div class="training-actions">
        <afr-button :disabled="busy || pendingStart || !pagination.total" :loading="startingTest === 'words'" @click="startTest('words')">Тест по словам</afr-button>
        <afr-button :disabled="busy || pendingStart" :loading="startingTest === 'difficult'" @click="startTest('difficult')">Тренировать сложные</afr-button>
      </div>
      <afr-button v-if="guestRepository" text @click="emit('back')">К занятиям</afr-button>
    </div>
    <p v-if="saving.message && !feedback.enabled" role="status" class="notice">{{ saving.message }}</p>
    <p v-if="error && !feedback.enabled" role="alert" class="error">{{ error }}</p>
    <afr-button v-if="pendingStart" :disabled="busy" @click="recover">Повторить отправку</afr-button>
    <section v-if="!shortPlan?.active_run_id && (shortPlan?.total || shortError)" class="short-review" aria-label="Короткое повторение">
      <template v-if="shortPlan?.total">
        <div>
          <p>{{ reviewWordCount(shortPlan.total) }} · {{ reviewDurationLabel(shortPlan.estimated_seconds) }}</p>
          <p v-if="shortPlan.includes_not_due" class="review-note">Включая слова для досрочного повторения</p>
        </div>
        <afr-button type="primary" :disabled="busy || pendingStart" @click="startShortReview">Повторить изученное</afr-button>
      </template>
      <template v-else-if="shortError">
        <p role="alert">{{ shortError }}</p><afr-button :disabled="busy" @click="perform(loadShortPlan)">Обновить предложение</afr-button>
      </template>
    </section>
    <form ref="filterBar" class="filters" aria-label="Фильтр словаря" @submit.prevent="refresh">
      <afr-input id="vocabulary-search" v-model="filters.q" aria-label="Поиск по слову или переводу" maxlength="100" placeholder="Слово или перевод" />
      <afr-select v-if="inlineStatus" id="vocabulary-filter" v-model="filters.filter" class="inline-filter" aria-label="Показать" native>
        <afr-option value="all" label="Все выражения" />
        <afr-option value="errors" label="Ошибки и отметки" />
        <afr-option value="due" label="Пора повторить" />
        <afr-option value="difficult" label="Сложные для меня" />
      </afr-select>
      <afr-select v-if="inlineSource" id="vocabulary-source" v-model="filters.source_session" class="inline-filter" aria-label="Из занятия" searchable search-placeholder="Поиск занятия…">
        <afr-option value="" label="Все занятия" />
        <afr-option v-for="session in sessions" :key="session.id" :value="session.id" :label="session.title" />
      </afr-select>
      <details
        v-if="!inlineStatus || !inlineSource"
        ref="filterDropdown"
        class="filter-dropdown"
        @keydown.esc.stop.prevent="closeFilters(); filterDropdown?.querySelector('summary')?.focus()"
      >
        <summary :class="{ 'has-filters': overflowActive }" title="Дополнительные фильтры" aria-label="Дополнительные фильтры">
          <daily-practice-icon name="list" />
        </summary>
        <div class="filter-popover">
          <div v-if="!inlineStatus" class="practice-field">
            <label for="vocabulary-filter">Показать</label>
            <afr-select id="vocabulary-filter" v-model="filters.filter" native>
              <afr-option value="all" label="Все выражения" />
              <afr-option value="errors" label="Ошибки и отметки" />
              <afr-option value="due" label="Пора повторить" />
              <afr-option value="difficult" label="Сложные для меня" />
            </afr-select>
          </div>
          <div v-if="!inlineSource" class="practice-field">
            <label for="vocabulary-source">Из занятия</label>
            <afr-select id="vocabulary-source" v-model="filters.source_session" aria-label="Из занятия" searchable search-placeholder="Поиск занятия…">
              <afr-option value="" label="Все занятия" />
              <afr-option v-for="session in sessions" :key="session.id" :value="session.id" :label="session.title" />
            </afr-select>
          </div>
          <afr-button native-type="submit" type="primary" :loading="searching" :disabled="busy && !searching">Применить</afr-button>
        </div>
      </details>
      <afr-button
        class="filter-submit"
        native-type="submit"
        :loading="searching"
        :disabled="busy && !searching"
        aria-label="Найти"
        title="Найти"
      >
        <daily-practice-icon v-show="!searching" name="search" />
      </afr-button>
      <button type="button" class="utility-button filter-reset" :disabled="busy || !canResetFilters"
        aria-label="Сбросить фильтры" title="Сбросить фильтры" @click="resetFilters"><daily-practice-icon name="reset" /></button>
    </form>
    <template v-if="!busy && !words.length">
      <p v-if="appliedFilters.filter === 'difficult'">Сложных слов в этой выборке пока нет. В списке «Все выражения» отметьте слова звёздочкой.</p>
      <p v-else-if="appliedFilters.added_in_run">В этой выборке нет новых выражений. Измените фильтры или откройте весь словарь.</p>
      <p v-else>В этом списке пока нет выражений. Завершите занятие или измените фильтры.</p>
    </template>
    <div v-if="pagination.last_page > 1" ref="topPagination" class="vocabulary-pagination">
      <Pagination :current-page="pagination.current_page" :last-page="pagination.last_page"
        :disabled="busy" aria-label="Страницы словаря над списком" @change="turnPage" />
    </div>
    <ol class="word-list">
      <li v-for="word in words" :key="word.id" :class="{ 'word-due': due(word) }">
        <div class="word-main">
          <french-text
            :text="word.snapshot.fr"
            :transcription-ru="word.snapshot.transcription_ru"
            :translation="word.self_check && !revealed[word.id] ? null : word.snapshot.ru"
            compact
          />
          <button v-if="word.self_check && !revealed[word.id]" type="button" class="reveal-translation" @click="revealed[word.id] = true">
            Показать перевод
          </button>
          <span v-if="due(word)" class="due-label">Пора повторить</span>
        </div>
        <div class="word-actions">
          <audio-control :audio="{text:word.snapshot.fr,lang:'fr-FR',asset_id:null}" :label="'Прослушать «' + word.snapshot.fr + '»'" compact />
          <button type="button" class="mark-button difficult-button" :class="{ marked: word.is_difficult }"
            :disabled="busy" :aria-pressed="!!word.is_difficult"
            :aria-busy="isMarking(word, 'is_difficult')"
            :title="word.is_difficult ? 'Снять отметку сложного слова' : 'Отметить как сложное слово'"
            :aria-label="(word.is_difficult ? 'Снять отметку сложного слова' : 'Отметить как сложное слово') + ' «' + word.snapshot.fr + '»'"
            @click="mark(word, 'is_difficult')">
            <Icon v-if="isMarking(word, 'is_difficult')" icon="svg-spinners:6-dots-rotate" aria-hidden="true" />
            <daily-practice-icon v-else name="star" />
          </button>
          <button
            type="button"
            class="mark-button"
            :class="{ marked: word.needs_review }"
            :disabled="busy"
            :aria-busy="isMarking(word, 'needs_review')"
            :aria-pressed="word.needs_review"
            :aria-label="(word.needs_review ? 'Снять отметку повторения' : 'Отметить для повторения') + ' «' + word.snapshot.fr + '»'"
            :title="word.needs_review ? 'Снять отметку повторения' : 'Отметить для повторения'"
            @click="mark(word)"
          >
            <Icon v-if="isMarking(word, 'needs_review')" icon="svg-spinners:6-dots-rotate" aria-hidden="true" />
            <daily-practice-icon v-else :name="word.needs_review ? 'check' : 'book'" />
          </button>
          <button
            type="button"
            class="mark-button details-button"
            :aria-expanded="!!expandedWords[word.id]"
            :aria-controls="'word-details-' + word.id"
            :aria-label="'Подробнее о «' + word.snapshot.fr + '»'"
            title="Примеры и статистика"
            @click="expandedWords[word.id] = !expandedWords[word.id]"
          >
            <daily-practice-icon name="chevron" />
          </button>
        </div>
        <div v-show="expandedWords[word.id]" :id="'word-details-' + word.id" class="word-details">
          <div class="word-stats">
            <span>Ошибок в ответах за всё время · {{ word.errors }}</span>
            <span v-if="word.next_review_at">
              {{ due(word) ? 'Срок наступил' : 'Следующее повторение' }} · <practice-date :value="word.next_review_at" :timezone="timezone" :relative="false" />
            </span>
          </div>
          <p v-if="word.self_check" class="review-note">Самопроверка без автоматического зачёта.</p>
          <h3 v-if="word.sources?.length">Занятия, где встречалось слово</h3>
          <div v-for="source in word.sources" :key="source.session_version_id" class="source">
            <div class="source-heading">
              <p>{{ source.session_title || 'Занятие' }}</p>
              <practice-report v-if="owner && (source.lesson_id || source.session_version_id)"
                :lesson-id="source.lesson_id" :version-id="source.session_version_id" compact />
            </div>
            <template v-if="source.snapshot.example">
              <french-text :text="source.snapshot.example.fr" :transcription-ru="source.snapshot.example.transcription_ru" :translation="source.snapshot.example.ru" compact />
              <audio-control v-if="expandedWords[word.id]" :audio="{text: source.snapshot.example.fr, lang: 'fr-FR'}" label="Прослушать пример" compact />
            </template>
          </div>
        </div>
      </li>
    </ol>
    <div v-if="pagination.last_page > 1" class="vocabulary-pagination">
      <Pagination :current-page="pagination.current_page" :last-page="pagination.last_page"
        :disabled="busy" aria-label="Страницы словаря под списком" @change="turnPage($event, true)" />
    </div>
  </section>
</template>
<style scoped>
.vocabulary-panel {
  --practice-target: 34px;
  --vocabulary-control-size: 34px;
}
.vocabulary-panel :deep(button),
.vocabulary-panel :deep(select),
.vocabulary-panel :deep(input) {
  min-block-size: var(--vocabulary-control-size);
  font-size: .8125rem;
  line-height: 1.3;
}
.vocabulary-panel :deep(.practice-audio.compact button) {
  width: var(--vocabulary-control-size);
  height: var(--vocabulary-control-size);
}
.vocabulary-header,
.short-review,
.word-actions,
.word-stats {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .5rem 1rem;
}
.vocabulary-header,
.short-review {
  justify-content: space-between;
}
h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 750;
}
.vocabulary-toolbar, .training-actions, .header-tools { display: flex; align-items: center; gap: .5rem; }
.vocabulary-toolbar { justify-content: space-between; flex-wrap: wrap; margin: .4rem 0; }
.vocabulary-scope { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.25rem .75rem; margin:.65rem 0; padding:.35rem .75rem; border-radius:.6rem; background:var(--practice-accent); font-size:.875rem; }
.vocabulary-scope-actions { display:flex; align-items:center; flex-wrap:wrap; gap:.25rem .75rem; }
.vocabulary-scope a { display:inline-flex; align-items:center; min-height:44px; color:var(--practice-blue); text-decoration:underline; text-underline-offset:.2em; }
.vocabulary-scope .vocabulary-result-link { min-height:44px; padding:.45rem .75rem; border:1px solid var(--practice-border); border-radius:var(--practice-control-radius); background:var(--practice-surface); color:var(--practice-blue); font-weight:650; text-decoration:none; }
.vocabulary-scope .vocabulary-result-link:not(:disabled):hover { border-color:var(--practice-blue); }
.vocabulary-scope .vocabulary-result-link:disabled { opacity:.65; }
.training-actions { flex-wrap: wrap; }
.header-tools { position: relative; gap: .15rem; flex-shrink: 0; }
.utility-button { display: grid; place-items: center; width: var(--vocabulary-control-size); height: var(--vocabulary-control-size); padding: 0; border-radius: .5rem; color: #51718e; }
.utility-button:hover, .utility-button[aria-expanded="true"] { background: var(--practice-accent); color: var(--practice-blue); }
.utility-button :deep(svg) { width: 18px; height: 18px; }
.header-tools :deep(.audio-preferences) { margin: 0; }
.header-tools :deep(.audio-preferences.popover) { position: static; }
.header-popover { position: absolute; right: 0; top: calc(100% + .4rem); z-index: 15; width: min(390px, calc(100vw - 2.5rem)); max-height: min(520px, 70svh); overflow-y: auto; padding: 1rem; border: 1px solid var(--practice-border); border-radius: .85rem; background: var(--practice-surface); box-shadow: 0 12px 35px #18344c24; }
.popover-heading { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .4rem; }
.popover-heading h3 { font-size: .9375rem; font-weight: 650; }
.header-popover p { margin: .65rem 0; color: var(--practice-muted); font-size: .8125rem; line-height: 1.6; }
.header-popover :deep(.review-settings) { margin: 0; padding: 0; border: 0; background: transparent; font-size: .8125rem; }
.header-popover :deep(.review-settings h2) { display: none; }
p {
  margin: .35rem 0;
  line-height: 1.5;
}
.guest-note,
.review-note,
.word-stats {
  color: var(--practice-muted);
  font-size: .8125rem;
}
.short-review {
  margin-top: .5rem;
  padding: .5rem .75rem;
  border-radius: var(--practice-radius);
  background: var(--practice-accent);
  font-size: .875rem;
}
.short-review p {
  margin: 0;
}
.short-review .review-note {
  margin-top: .2rem;
}
summary {
  cursor: pointer;
  min-height: var(--vocabulary-control-size);
  padding: .45rem 0;
  color: var(--practice-blue);
  font-size: .8125rem;
}
.filters {
  position: relative;
  display: flex;
  align-items: center;
  gap: .375rem;
  margin: .35rem 0 .75rem;
}
.filters :deep(.afr-search-control > input[role="combobox"]) {
  font-family: inherit;
  font-size: .8125rem;
  line-height: 1.3;
}
:global(#vocabulary-source-options > li) {
  font-family: inherit;
  font-size: .8125rem;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.filter-reset { flex: 0 0 var(--vocabulary-control-size); }
.filter-reset:disabled { opacity: .4; cursor: default; }
.inline-filter {
  flex: 0 0 170px;
  min-width: 0;
}
.filter-dropdown {
  flex: 0 0 var(--vocabulary-control-size);
}
.filter-dropdown > summary,
.filter-submit {
  display: grid;
  place-items: center;
  width: var(--vocabulary-control-size);
  height: var(--vocabulary-control-size);
  padding: 0;
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-control-radius);
  background: var(--practice-surface);
}
.filter-dropdown > summary {
  list-style: none;
}
.filter-dropdown > summary::-webkit-details-marker {
  display: none;
}
.filter-dropdown[open] > summary,
.filter-dropdown .has-filters {
  border-color: var(--practice-blue);
  background: var(--practice-accent);
}
.filters .filter-submit {
  flex: 0 0 var(--vocabulary-control-size);
  border-color: var(--practice-blue);
  background: var(--practice-blue);
  color: #fff;
}
.filters .filter-submit:enabled:hover {
  background: var(--practice-blue-hover);
}
.filters .filter-submit.is-loading {
  opacity: 1;
}
.filter-submit :deep(svg),
.filter-dropdown > summary :deep(svg) {
  width: 18px;
  height: 18px;
  margin: 0;
}
.filter-popover {
  position: absolute;
  top: calc(100% + .5rem);
  right: 0;
  z-index: 5;
  display: grid;
  gap: .75rem;
  width: min(280px, 100%);
  padding: .875rem;
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-radius);
  background: var(--practice-surface);
  box-shadow: 0 8px 24px #18344c24;
}
.practice-field {
  display: grid;
  gap: .25rem;
  min-width: 0;
}
.practice-field label {
  font-size: .8125rem;
  color: var(--practice-muted);
}
.filters :deep(.afr-input-component) {
  flex: 1 1 140px;
  min-width: 0;
}
.notice,
.error {
  margin: .75rem 0;
  padding: .75rem;
  border-radius: var(--practice-radius);
  background: var(--practice-accent);
}
.error {
  background: #fef2f2;
}
.vocabulary-pagination {
  display: flex;
  justify-content: flex-end;
  margin: .5rem 0;
}
.word-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.word-list > li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0 .75rem;
  padding: .75rem 0;
  border-bottom: 1px solid var(--practice-border);
}
.word-list > .word-due {
  padding-inline: .75rem;
  border-inline-start: 3px solid #cb893b;
  background: #fff8ed;
}
.due-label {
  display: inline-block;
  margin-top: .35rem;
  color: #894516;
  font-size: .75rem;
  font-weight: 650;
}
.difficult-button.marked :deep(svg) { fill: currentColor; }
.word-main {
  min-width: 0;
}
.word-main :deep(.french-material) {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: .1rem 1rem;
}
.word-main :deep(.french-line) {
  font-size: 1rem;
  font-weight: 650;
  line-height: 1.4;
}
.word-main :deep(.transcription-line),
.word-main :deep(.transcription-missing) {
  grid-column: 1;
  font-size: .8125rem;
  line-height: 1.4;
}
.word-main :deep(.translation-line) {
  grid-column: 2;
  grid-row: 1 / span 2;
  margin: 0;
  font-size: .9375rem;
  line-height: 1.4;
}
.word-actions {
  align-self: start;
  flex-wrap: nowrap;
  gap: .25rem;
  flex-shrink: 0;
}
.mark-button {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: var(--vocabulary-control-size);
  height: var(--vocabulary-control-size);
  padding: 0;
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-control-radius);
  background: #fff;
  color: var(--practice-blue);
}
.mark-button:hover {
  background: var(--practice-accent);
}
.mark-button.marked {
  border-color: #e9cda7;
  background: #fff5e8;
  color: #9a5219;
}
.mark-button:disabled {
  cursor: wait;
  opacity: .6;
}
.mark-button :deep(svg) {
  width: 19px;
  height: 19px;
}
.reveal-translation {
  color: var(--practice-blue);
  font-size: .8125rem;
  text-decoration: underline;
}
.word-details {
  grid-column: 1 / -1;
  margin-top: .65rem;
  padding: .75rem;
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-control-radius);
  background: var(--practice-bg);
}
.details-button[aria-expanded="true"] {
  background: var(--practice-accent);
  border-color: var(--practice-blue);
}
.details-button[aria-expanded="true"] :deep(svg) {
  transform: rotate(90deg);
}
.word-stats {
  margin-bottom: .5rem;
}
.word-details h3 {
  margin-top: .75rem;
  font-size: .875rem;
  font-weight: 650;
}
.source {
  margin: .5rem 0;
  padding: .35rem .65rem;
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-control-radius);
  background: var(--practice-surface);
}
.source-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
}
.source-heading p {
  font-size: .8125rem;
  font-weight: 650;
}
.source :deep(.french-material) {
  margin: .25rem 1rem .25rem 0;
}
a {
  color: var(--practice-blue);
  text-decoration: underline;
}
:deep(button) {
  max-width: 100%;
  white-space: normal;
}
:deep(:focus-visible) {
  outline: 3px solid #2563eb;
  outline-offset: 3px;
}
@media (max-width: 600px) {
  .vocabulary-header { flex-wrap: nowrap; gap: .35rem; }
  .vocabulary-header h2 { font-size: 1.05rem; }
  .vocabulary-toolbar { gap: .25rem .75rem; }
  .training-actions { flex-wrap: nowrap; }
  .training-actions :deep(button) { padding-inline: .6rem; }
  .word-list > li { grid-template-columns: minmax(0, 1fr); gap: .4rem; }
  .word-main :deep(.french-material) {
    display: flex;
    gap: .15rem;
  }
  .word-actions {
    flex-wrap: nowrap;
    justify-content: flex-end;
    justify-self: end;
    max-width: none;
  }
  .short-review {
    align-items: flex-start;
  }
}
@media (pointer: coarse) {
  .vocabulary-panel {
    --practice-target: 40px;
    --vocabulary-control-size: 40px;
  }
}
</style>
