<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import api from '@/Admin/Services/Api';
import Issues from './Issues.vue';
import { prepareImport, importPrepared } from './bulkImport';
const props = defineProps({ disabled: Boolean });
const emit = defineEmits(['busy', 'finished']);
const filesInput = ref(null), folderInput = ref(null), rows = ref([]);
const preparing = ref(false), running = ref(false), paused = ref(false), notice = ref(''), waitSeconds = ref(0);
let alive = true, retryAt = 0;
const imported = computed(() => rows.value.filter(r => r.status === 'done'));
const failures = computed(() => rows.value.filter(r => ['error', 'invalid'].includes(r.status)));
const pending = computed(() => rows.value.filter(r => r.status === 'pending'));
const skipped = computed(() => rows.value.filter(r => r.status === 'skipped').length);
const added = computed(() => imported.value.reduce((n, r) => n + r.receipt.created_version_ids.length, 0));
const updated = computed(() => imported.value.reduce((n, r) => n + (r.receipt.updated_version_ids?.length ?? 0), 0));
const total = computed(() => rows.value.length - skipped.value);
const percent = computed(() => total.value ? Math.round((imported.value.length + failures.value.length) * 100 / total.value) : 0);
const labels = { pending: 'Ожидает', uploading: 'Импорт', done: 'Готово', error: 'Ошибка импорта', invalid: 'Ошибка файла', skipped: 'Пропущен' };
const select = async event => {
  if (props.disabled || running.value || preparing.value) return;
  preparing.value = true; emit('busy', true); notice.value = ''; rows.value = [];
  try { rows.value = await prepareImport(Array.from(event.target.files ?? [])); }
  catch (error) { notice.value = error.message; }
  finally { event.target.value = ''; preparing.value = false; emit('busy', false); }
};
const wait = async () => {
  while (alive && !paused.value && Date.now() < retryAt) {
    waitSeconds.value = Math.ceil((retryAt - Date.now()) / 1000);
    await new Promise(resolve => setTimeout(resolve, 250));
  }
  waitSeconds.value = 0;
};
const start = async (retry = false) => {
  if (running.value || props.disabled) return;
  if (retry) for (const row of rows.value) if (row.status === 'error') { row.status = 'pending'; row.report = null; }
  paused.value = false; running.value = true; notice.value = ''; emit('busy', true);
  try {
    await wait();
    await importPrepared(rows.value, {
      send: file => api.dailyPractice.upload('imports/bulk', file),
      paused: () => paused.value || !alive,
      onRateLimit: async error => {
        const value = error.response?.headers?.['retry-after'];
        const seconds = Number(value);
        retryAt = Date.now() + (Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : Math.max(1000, Date.parse(value) - Date.now()) || 60000) + 1000;
        await wait();
      },
      onFatal: error => { paused.value = true; notice.value = [401, 403, 419].includes(error.response?.status) ? 'Сессия администратора недоступна. Войдите снова, затем повторите импорт.' : 'Импорт остановлен из-за ошибки связи или сервера. Уже загруженные занятия сохранены. Повторите ошибки после устранения причины.'; },
    });
  } finally { running.value = false; emit('busy', false); if (alive) emit('finished'); }
};
const leave = event => { if (running.value) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => window.addEventListener('beforeunload', leave));
onBeforeUnmount(() => { alive = false; paused.value = true; window.removeEventListener('beforeunload', leave); });
</script>
<template>
  <el-card class="panel" shadow="never">
    <template #header>Массовый импорт занятий</template>
    <p>Выберите папку <code>packages</code> целиком или несколько JSON-файлов. Новые занятия добавятся черновиками, существующие обновятся по ID с сохранением статуса.</p>
    <p class="muted"><code>*.v1.json</code> — занятия. <code>*.authoring-delta.json</code> — заметки подготовки, <code>*.validation.json</code> — отчёты проверки. Сопроводительные файлы пропускаются автоматически.</p>
    <p class="muted">Среди пакетов с одинаковыми ID занятий выбирается максимальный номер в имени файла: <code>v10</code> новее <code>v2</code>. Старые версии, идентичные копии и фрагменты, покрытые полным пакетом той же или более новой версии, пропускаются. <code>IMPORT_MANIFEST.json</code> не используется.</p>
    <input ref="folderInput" type="file" webkitdirectory multiple hidden @change="select" />
    <input ref="filesInput" type="file" accept=".json,application/json" multiple hidden @change="select" />
    <div class="actions"><el-button :disabled="disabled || running || preparing" :loading="preparing" @click="folderInput.click()">Выбрать папку</el-button><el-button :disabled="disabled || running || preparing" @click="filesInput.click()">Выбрать несколько JSON</el-button></div>
    <p class="muted">Файлы отправляются последовательно с учётом зависимостей. Во время импорта держите страницу открытой. После закрытия страницы можно выбрать папку заново: повторный импорт обновит занятия без дубликатов. Импорт не публикует новые занятия автоматически.</p>
    <el-alert v-if="notice" :title="notice" type="warning" :closable="false" />
    <template v-if="rows.length">
      <p>Пакетов: {{ total }} · Готово: {{ imported.length }} · Ошибок: {{ failures.length }} · Пропущено файлов: {{ skipped }}</p>
      <p>Занятий добавлено: {{ added }} · Обновлено: {{ updated }}</p>
      <el-progress :percentage="percent" :status="!running && !pending.length && !failures.length ? 'success' : undefined" />
      <p v-if="waitSeconds">Хостинг ограничил частоту запросов. Продолжение через {{ waitSeconds }} с. Новые запросы пока не отправляются.</p>
      <div class="actions"><el-button type="primary" :disabled="disabled || running || !pending.length" @click="start()">{{ imported.length || paused ? 'Продолжить импорт' : 'Импортировать всё' }}</el-button><el-button v-if="running" :disabled="paused" @click="paused = true">{{ paused ? 'Остановка после текущего файла…' : 'Приостановить' }}</el-button><el-button :disabled="disabled || running || !rows.some(r => r.status === 'error')" @click="start(true)">Повторить ошибки</el-button></div>
      <el-table :data="rows" row-key="name" border stripe max-height="520">
        <el-table-column type="expand"><template #default="{ row }"><issues v-if="row.report" :report="row.report" /><p v-else>{{ row.message }}</p></template></el-table-column>
        <el-table-column prop="name" label="Файл" min-width="250" />
        <el-table-column label="Занятий" width="100"><template #default="{ row }">{{ row.sessions.length }}</template></el-table-column>
        <el-table-column label="Состояние" width="160"><template #default="{ row }"><el-tag :type="row.status === 'done' ? 'success' : ['error','invalid'].includes(row.status) ? 'danger' : 'info'">{{ labels[row.status] }}</el-tag></template></el-table-column>
        <el-table-column prop="message" label="Результат" min-width="300" />
      </el-table>
    </template>
  </el-card>
</template>
