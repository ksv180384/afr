<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Clock, Headset, Microphone, Plus, Refresh, Search, VideoPause, VideoPlay, Warning } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import api from '@/Admin/Services/Api';
import AdminInfo from './AdminInfo.vue';
import Issues from './Issues.vue';
import RowActions from './RowActions.vue';
import Shell from './Shell.vue';

const props = defineProps({ authUser: Object, rows: Array });
const voices = { lea: 'Lea', paul: 'Paul' };
const size = 30;
const page = ref(1);
const search = ref('');
const filter = ref('all');
const changes = ref({});
const status = ref({ pending: 0, failed: 0, jobs: [] });
const failure = ref(null);
const generating = ref(false);
const audio = ref(null);
const preview = ref(null);
const playing = ref(false);
const previewError = ref('');
let playAttempt = 0;
let timer;
let sequence = 0;

const files = row => changes.value[row.key] ?? row.files;
const incomplete = row => !files(row).lea || !files(row).paul;
const missing = computed(() => props.rows.filter(incomplete).length);
const ready = computed(() => props.rows.length - missing.value);
const formatCount = new Intl.NumberFormat('ru-RU').format;
const searchable = text => text.normalize('NFD').replace(/\p{M}/gu, '').replace(/’/g, "'").toLowerCase();
const filtered = computed(() => {
  const needle = searchable(search.value.trim());
  return props.rows.filter(row => {
    if (filter.value === 'missing' && !incomplete(row)) return false;
    if (filter.value === 'ready' && incomplete(row)) return false;
    return !needle || [row.text, ...row.ru].some(text => searchable(text).includes(needle));
  });
});
const visible = computed(() => filtered.value.slice((page.value - 1) * size, page.value * size));
const job = (row, voice) => status.value.jobs.find(item => item.key === row.key && item.voice === voice);
const queued = (row, voice) => status.value.jobs.some(item => item.key === row.key && (!voice || item.voice === voice) && !item.error);
const errorReport = error => ({ message: error.response?.data?.message ?? error.message });

function isPlaying(row, voice) {
  return playing.value && preview.value?.key === row.key && preview.value.voice === voice;
}
function voiceAction(row, voice) {
  if (!files(row)[voice]) return job(row, voice)?.error ? 'Повторить создание' : 'Создать запись';
  return isPlaying(row, voice) ? 'Приостановить' : 'Прослушать';
}
function voiceStatus(row, voice) {
  const task = job(row, voice);
  if (task?.error) return 'Ошибка записи';
  if (task) return task.running ? 'Создаётся…' : 'В очереди';
  if (!files(row)[voice]) return 'Нет записи';
  return isPlaying(row, voice) ? 'Играет' : 'Слушать';
}
function listen(row, voice) {
  const player = audio.value;
  const url = files(row)[voice];
  const attempt = ++playAttempt;
  if (preview.value?.url !== url || player.error) {
    preview.value = { key: row.key, text: row.text, voice, url };
    player.src = url;
  } else if (!player.paused && !player.ended) {
    player.pause();
    return;
  }
  previewError.value = '';
  player.play().catch(error => {
    if (attempt === playAttempt && error.name !== 'AbortError') playbackFailed();
  });
}
function playbackFailed() {
  if (previewError.value) return;
  playing.value = false;
  previewError.value = 'Не удалось воспроизвести запись';
  ElMessage.error(previewError.value);
}
function reloadFiles() {
  router.reload({ only: ['rows'], preserveScroll: true, onSuccess: () => { changes.value = {}; } });
}
async function refresh() {
  clearTimeout(timer);
  const revision = ++sequence;
  try {
    const next = await api.dailyPractice.action('pronunciation/status', { keys: visible.value.map(row => row.key) });
    if (revision !== sequence) return;
    if (status.value.pending && !next.pending) reloadFiles();
    status.value = next;
    changes.value = { ...changes.value, ...next.files };
    failure.value = null;
    if (next.pending) timer = setTimeout(refresh, 4000);
  } catch (error) {
    if (revision === sequence) failure.value = errorReport(error);
  }
}
async function generate(row, mode, voice) {
  if (generating.value) return;
  generating.value = true;
  try {
    const result = await api.dailyPractice.action('pronunciation/generate', row ? { key: row.key, mode, voice } : { mode });
    ElMessage.success(result.added ? 'Добавлено в очередь: ' + formatCount(result.added) : 'Новых заданий для очереди нет.');
    await refresh();
  } catch (error) {
    failure.value = errorReport(error);
  } finally { generating.value = false; }
}

watch([search, filter], () => { page.value = 1; });
watch(() => filtered.value.length, length => { page.value = Math.min(page.value, Math.max(1, Math.ceil(length / size))); });
watch(() => visible.value.map(row => row.key).join(','), () => {
  clearTimeout(timer);
  ++sequence;
  timer = setTimeout(refresh, 200);
});
onMounted(refresh);
onBeforeUnmount(() => { ++sequence; ++playAttempt; clearTimeout(timer); audio.value?.pause(); });
</script>

<template>
  <shell :auth-user="authUser" class="pronunciation-page" title="Озвучка словаря практики" compact-heading>
    <div class="recording-library">
      <div class="overview">
        <section class="overview-panel" aria-label="Готовность озвучки">
          <div class="panel-heading">
            <h2>Озвучено обоими голосами</h2>
            <div class="status-actions">
              <el-button circle text :icon="Refresh" aria-label="Обновить статус" title="Обновить статус" @click="reloadFiles(); refresh()" />
              <admin-info label="Как перенести записи на хостинг">
                Скопируйте папку <code>public/practice-audio</code> вместе с подпапками в публичный каталог сайта.
              </admin-info>
            </div>
          </div>
          <div class="coverage">
            <strong>{{ formatCount(ready) }}</strong><span>из {{ formatCount(rows.length) }}</span>
          </div>
          <el-progress :percentage="rows.length ? 100 * ready / rows.length : 0" :show-text="false" :stroke-width="5" aria-label="Доля полностью озвученных слов и фраз" />
          <div class="queue-state" role="status">
            <span><i :class="{ pending: status.pending }" />{{ status.pending ? 'В очереди: ' + formatCount(status.pending) : 'Очередь пуста' }}</span>
            <span v-if="status.failed" class="error-text">Ошибок: {{ formatCount(status.failed) }}</span>
          </div>
        </section>
        <section class="overview-panel monitor" aria-label="Плеер записей">
          <h2><el-icon><Headset /></el-icon> Прослушивание</h2>
          <div class="track" aria-live="polite" :class="{ 'error-text': previewError }" :title="preview?.text">
            <template v-if="previewError">{{ previewError }}</template>
            <template v-else-if="preview"><strong>{{ voices[preview.voice] }}</strong> · <span lang="fr">{{ preview.text }}</span></template>
            <template v-else>Выберите голос</template>
          </div>
          <audio ref="audio" controls preload="none" aria-label="Управление воспроизведением" @play="playing = true" @pause="playing = false" @ended="playing = false" @error="playbackFailed" />
        </section>
      </div>

      <el-alert v-if="status.message" type="warning" :closable="false" :title="status.message" />
      <issues :report="failure" />

      <el-card shadow="never" class="catalog">
        <div class="catalog-header">
          <h2>Слова и фразы</h2>
          <el-button type="primary" :icon="Microphone" :disabled="generating || !missing" @click="generate(null, 'missing')">Создать недостающие</el-button>
        </div>
        <div class="catalog-filters">
          <el-input v-model="search" clearable :prefix-icon="Search" aria-label="Поиск по слову, фразе или переводу" placeholder="Слово, фраза или перевод" />
          <el-radio-group v-model="filter" aria-label="Наличие озвучки">
            <el-radio-button value="all">Все</el-radio-button>
            <el-radio-button value="missing">Нужна озвучка</el-radio-button>
            <el-radio-button value="ready">Готовые</el-radio-button>
          </el-radio-group>
        </div>

        <ul v-if="visible.length" class="entries" aria-label="Словарные записи">
          <li v-for="row in visible" :key="row.key" class="entry">
            <div class="word">
              <strong lang="fr">{{ row.text }}</strong>
              <div>{{ row.ru.join(' · ') }}</div>
            </div>
            <div class="voices">
              <button v-for="(name, voice) in voices" :key="voice" type="button" class="voice"
                :class="{ recorded: files(row)[voice], current: isPlaying(row, voice) }"
                :disabled="!files(row)[voice] && (generating || queued(row, voice))"
                :title="voiceAction(row, voice) + ' · ' + name"
                :aria-label="voiceAction(row, voice) + ' · ' + name + ' · ' + row.text"
                @click="files(row)[voice] ? listen(row, voice) : generate(row, 'missing', voice)">
                <el-icon class="voice-icon">
                  <VideoPause v-if="isPlaying(row, voice)" /><VideoPlay v-else-if="files(row)[voice]" />
                  <Clock v-else-if="queued(row, voice)" /><Plus v-else />
                </el-icon>
                <span><b>{{ name }}</b><small>{{ voiceStatus(row, voice) }}</small></span>
              </button>
            </div>
            <div class="entry-actions">
              <row-actions :label="'Действия для «' + row.text + '»'" :disabled="generating">
                <el-dropdown-item v-for="(name, voice) in voices" :key="voice"
                  :icon="files(row)[voice] ? Refresh : Plus" :disabled="queued(row, voice)"
                  @click="generate(row, files(row)[voice] ? 'replace' : 'missing', voice)">
                  {{ files(row)[voice] ? 'Перезаписать' : 'Создать запись' }} · {{ name }}
                </el-dropdown-item>
                <el-dropdown-item divided :icon="Microphone" :disabled="queued(row)" @click="generate(row, incomplete(row) ? 'missing' : 'replace')">
                  {{ incomplete(row) ? 'Создать недостающие' : 'Пересоздать оба голоса' }}
                </el-dropdown-item>
              </row-actions>
            </div>
            <template v-for="(name, voice) in voices" :key="voice">
              <div v-if="job(row, voice)?.error" class="job-error"><el-icon><Warning /></el-icon> {{ name }} · {{ job(row, voice).error }}</div>
            </template>
          </li>
        </ul>
        <div v-else class="empty">
          <el-icon><Search /></el-icon>
          <strong>Слова и фразы не найдены</strong>
          <el-button v-if="search || filter !== 'all'" link type="primary" @click="search = ''; filter = 'all'">Сбросить фильтры</el-button>
        </div>
        <div v-if="filtered.length" class="catalog-footer">
          <span>{{ formatCount((page - 1) * size + 1) }}–{{ formatCount(Math.min(page * size, filtered.length)) }} из {{ formatCount(filtered.length) }}</span>
          <el-pagination v-if="filtered.length > size" v-model:current-page="page" :page-size="size" :total="filtered.length" :pager-count="5" layout="prev, pager, next" />
        </div>
      </el-card>
    </div>
  </shell>
</template>

<style scoped>
.recording-library { container-type: inline-size; display: grid; gap: 14px; color: var(--el-text-color-primary); }
.overview { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.overview-panel, .catalog { min-width: 0; border: 1px solid var(--el-border-color-light); border-radius: 8px; background: var(--el-bg-color); }
.overview-panel { display: grid; align-content: center; gap: 8px; padding: 14px 18px; }
.panel-heading, .catalog-header, .catalog-filters, .status-actions, .catalog-footer, .queue-state { display: flex; align-items: center; gap: 10px; }
.panel-heading, .catalog-header, .catalog-footer { justify-content: space-between; }
.overview-panel h2 { display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 600; line-height: 20px; }
.panel-heading :deep(.el-button) { width: 24px; height: 24px; }
.coverage { display: flex; align-items: baseline; gap: 6px; font-size: 13px; color: var(--el-text-color-regular); }
.coverage strong { color: var(--el-text-color-primary); font-size: 24px; line-height: 1; font-weight: 650; }
.queue-state, .catalog-footer { font-size: 12px; color: var(--el-text-color-regular); }
.queue-state { flex-wrap: wrap; }
.queue-state span { display: inline-flex; gap: 6px; align-items: center; }
.queue-state i { width: 7px; height: 7px; border-radius: 50%; background: var(--el-border-color-darker); }
.queue-state i.pending { background: var(--el-color-primary); }
.monitor { background: var(--el-fill-color-extra-light); }
.monitor h2 .el-icon { color: var(--el-color-primary); }
.track { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--el-text-color-regular); font-size: 13px; line-height: 20px; }
.monitor audio { display: block; width: 100%; min-width: 0; height: 36px; }
.catalog :deep(.el-card__body) { padding: 0; }
.catalog-header { padding: 16px 18px 10px; flex-wrap: wrap; }
.catalog-header h2 { font-size: 18px; font-weight: 600; }
.status-actions { gap: 6px; }
.status-actions .el-button { margin: 0; }
.catalog-filters { padding: 4px 18px 16px; flex-wrap: wrap; }
.catalog-filters .el-input { flex: 1 1 190px; }
.entries { padding: 0; list-style: none; margin: 0; }
.entry { display: grid; grid-template-columns: minmax(0, 1fr) 226px 32px; align-items: center; gap: 8px 12px; padding: 11px 18px; border-top: 1px solid var(--el-border-color-lighter); }
.entry:hover, .entry:focus-within { background: var(--el-fill-color-extra-light); }
.word { min-width: 0; overflow-wrap: anywhere; line-height: 1.4; }
.word strong { font-size: 15px; font-weight: 600; }
.word div { color: var(--el-text-color-regular); font-size: 13px; margin-top: 2px; }
.voices { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
.voice { display: flex; align-items: center; gap: 7px; min-height: 42px; padding: 4px 8px; border: 1px dashed var(--el-border-color); border-radius: 6px; background: var(--el-bg-color); text-align: left; color: var(--el-text-color-regular); }
.voice-icon { flex: none; font-size: 18px; }
.voice b, .voice small { display: block; }
.voice b { font-size: 13px; font-weight: 600; line-height: 16px; }
.voice small { font-size: 11px; line-height: 15px; }
.voice.recorded { border-style: solid; color: var(--el-color-primary-dark-2); border-color: var(--el-color-primary-light-7); background: var(--el-color-primary-light-9); }
.voice:enabled:hover, .voice.current { border-color: var(--el-color-primary); color: var(--el-color-primary-dark-2); }
.voice:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
.voice:disabled { cursor: wait; color: var(--el-text-color-secondary); }
.job-error, .error-text { color: var(--el-color-danger-dark-2); }
.job-error { grid-column: 1 / -1; font-size: 12px; overflow-wrap: anywhere; }
.empty { display: grid; justify-items: center; gap: 8px; padding: 36px 16px; border-top: 1px solid var(--el-border-color-lighter); color: var(--el-text-color-regular); font-size: 14px; }
.empty > .el-icon { font-size: 22px; color: var(--el-text-color-secondary); }
.catalog-footer { flex-wrap: wrap; padding: 10px 18px; border-top: 1px solid var(--el-border-color-light); }
.catalog-footer :deep(.el-pagination) { margin: 0; }
@media (max-width: 600px) {
  .pronunciation-page :deep(nav[aria-label="Управление практикой"]) { gap: 0 12px; }
  .pronunciation-page :deep(.practice-tab) { font-size: 13px; padding-block: 6px; }
}
@container (max-width: 570px) {
  .overview { grid-template-columns: minmax(0, 1fr); gap: 8px; }
  .overview-panel { padding: 10px 12px; }
  .overview-panel:not(.monitor) { grid-template-columns: 1fr auto; }
  .panel-heading, .overview-panel :deep(.el-progress) { grid-column: 1 / -1; }
  .queue-state { grid-row: 2; grid-column: 2; }
  .catalog-header h2 { font-size: 16px; }
  .catalog-header > .el-button { padding-inline: 9px; font-size: 13px; }
  .monitor { grid-template-columns: minmax(0, 1fr) minmax(154px, 54%); column-gap: 8px; row-gap: 2px; }
  .monitor audio { grid-row: 1 / 3; grid-column: 2; }
  .catalog-header, .catalog-filters { padding-inline: 12px; }
  .catalog-filters .el-input { flex-basis: 100%; }
  .entry { grid-template-columns: minmax(0, 1fr) 32px; padding: 10px 12px; }
  .entry-actions { grid-row: 1; grid-column: 2; }
  .voices { grid-row: 2; grid-column: 1 / -1; }
  .voice { min-height: 44px; }
  .catalog-footer { padding-inline: 12px; }
}
</style>
