<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import Issues from './Issues.vue';
import Pager from './Pager.vue';
import { useOperation } from './useOperation';
import { sessionStatuses } from './editorUi';

const props = defineProps({ authUser: Object, versions: Array, paginate: Object, filters: Object, levels: Array });
const { busy, failure, run } = useOperation();
const filters = ref({ search: '', level: '', unit: '', status: '', version: undefined, from: '', to: '', ...props.filters, version: props.filters.version ? Number(props.filters.version) : undefined });
const file = ref(null);
const preview = ref(null);
const receipt = ref(null);
const selected = ref([]);
const bulkStatus = ref('published');
watch(() => props.versions, () => { selected.value = []; });
const canCommit = computed(() => preview.value?.valid && !receipt.value);
const selectFile = (upload) => { file.value = upload.raw; preview.value = null; receipt.value = null; failure.value = null; };
const inspect = async () => { const result = await run(() => api.dailyPractice.upload('imports/preview', file.value)); if (result) preview.value = result; };
const inspectPrepared = async () => {
  const result = await run(async () => {
    const content = await api.dailyPractice.template('pre-a1-u01-v2');
    selectFile({ raw: new File([JSON.stringify(content)], 'PRE_A1-U01.v2.json', { type: 'application/json' }) });
    return api.dailyPractice.upload('imports/preview', file.value);
  });
  if (result) preview.value = result;
};
const commit = async () => {
  if (!canCommit.value) return;
  const result = await run(() => api.dailyPractice.action('imports/' + preview.value.batch_id + '/commit', { token: preview.value.token, file_hash: preview.value.file_hash }), true);
  if (result) receipt.value = result;
};
const search = () => router.get('/admin/daily-practice', Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value != null)), { preserveState: true, replace: true });
const changeStatus = (ids, status) => run(() => api.dailyPractice.action('versions/status', { version_ids: ids, status }), true);
</script>
<template>
  <shell :auth-user="authUser">
    <el-card class="panel" shadow="never">
      <template #header>Импорт занятий</template>
      <p class="muted">Загрузите JSON-пакет. Проверка формата выполняется автоматически; импорт создаёт черновики.</p>
      <div class="actions">
        <el-upload :auto-upload="false" :show-file-list="false" accept=".json,application/json" :disabled="busy" :on-change="selectFile"><el-button :disabled="busy">Выбрать JSON</el-button></el-upload>
        <el-text v-if="file">{{ file.name }}</el-text>
        <el-button type="primary" :disabled="!file || busy" :loading="busy" @click="inspect">Проверить файл</el-button>
      </div>
      <p class="muted">Обновление первых пяти занятий: короткие вводные блоки, повторение по ситуациям и ответы без печати. Новая версия применяется к новым прохождениям.</p>
      <el-button :disabled="busy" :loading="busy" @click="inspectPrepared">Загрузить 5 обновлённых занятий</el-button>
      <div class="actions"><el-link href="/admin/daily-practice/templates/pre-a1-u01">Два образца JSON</el-link><el-link href="/admin/daily-practice/templates/task-types">Все типы вопросов</el-link><el-link href="/admin/daily-practice/schema">Схема v1</el-link></div>
      <template v-if="preview">
        <el-table :data="preview.plan.sessions" border stripe empty-text="В пакете нет занятий">
          <el-table-column prop="id" label="Занятие" min-width="200" />
          <el-table-column prop="version" label="Версия" width="90" />
          <el-table-column label="Действие" min-width="200"><template #default="{ row }">{{ row.action === 'skip' ? 'Уже импортировано — пропустить' : 'Создать черновик' }}</template></el-table-column>
        </el-table>
        <p class="muted">Редакций уровня/темы: {{ preview.plan.catalog_revisions }}. Программ: {{ preview.plan.curricula.length }}.</p>
        <issues :report="preview" />
        <el-button type="success" :disabled="!canCommit || busy" @click="commit">Импортировать занятия</el-button>
      </template>
      <el-button v-if="receipt?.version_ids?.length" type="primary" :disabled="busy" @click="changeStatus(receipt.version_ids, 'published')">Опубликовать импортированные занятия</el-button>
      <el-alert v-if="receipt" class="panel" type="success" :closable="false" show-icon :title="'Импорт завершён. Создано: ' + receipt.created_version_ids.length + ', пропущено: ' + receipt.skipped_version_ids.length" />
    </el-card>
    <issues :report="failure" />
    <el-card class="panel" shadow="never">
      <template #header>Занятия</template>
      <el-form :model="filters" class="filters" label-position="top" @submit.prevent="search">
        <el-form-item label="ID или название"><el-input v-model="filters.search" maxlength="200" clearable @keyup.enter="search" /></el-form-item>
        <el-form-item label="Уровень"><el-select v-model="filters.level" clearable placeholder="Все уровни"><el-option v-for="level in levels" :key="level.code" :value="level.code" :label="level.title" /></el-select></el-form-item>
        <el-form-item label="Тема"><el-input v-model="filters.unit" clearable placeholder="ID темы" /></el-form-item>
        <el-form-item label="Статус"><el-select v-model="filters.status" clearable placeholder="Все статусы"><el-option v-for="status in sessionStatuses" :key="status.value" :value="status.value" :label="status.label" /></el-select></el-form-item>
        <el-form-item label="Версия"><el-input-number v-model="filters.version" :min="1" :controls="false" /></el-form-item>
        <el-form-item label="Создано с"><el-date-picker v-model="filters.from" type="date" value-format="YYYY-MM-DD" placeholder="Дата" /></el-form-item>
        <el-form-item label="По дату"><el-date-picker v-model="filters.to" type="date" value-format="YYYY-MM-DD" placeholder="Дата" /></el-form-item>
        <el-button type="primary" native-type="submit">Найти</el-button><Link href="/admin/daily-practice"><el-button>Сбросить</el-button></Link>
      </el-form>
      <p class="muted">Чтобы опубликовать новую тему целиком, выделите галочками все её занятия, выберите «Опубликовано» и нажмите «Изменить статус выбранных». Предыдущие занятия из выбранной группы будут учтены автоматически.</p>
      <div class="actions">
        <el-text>Выбрано: {{ selected.length }}</el-text>
        <el-select v-model="bulkStatus" aria-label="Статус выбранных занятий" style="width: 190px" :disabled="busy"><el-option v-for="status in sessionStatuses" :key="status.value" :value="status.value" :label="status.label" /></el-select>
        <el-button type="primary" :disabled="!selected.length || busy" :loading="busy" @click="changeStatus(selected, bulkStatus)">Изменить статус выбранных</el-button>
      </div>
      <el-table :data="versions" row-key="id" border stripe empty-text="Занятия не найдены. Загрузите пакет или измените фильтры." @selection-change="rows => selected = rows.map(row => row.id)">
        <el-table-column type="selection" width="48" :selectable="() => !busy" />
        <el-table-column label="Занятие" min-width="260"><template #default="{ row }"><Link :href="'/admin/daily-practice/versions/' + row.id" class="el-link el-link--primary">{{ row.title }}</Link><div class="muted">{{ row.external_id }}</div><Link :href="'/admin/daily-practice/versions/' + row.id + '/preview'" class="el-link el-link--primary">Пройти тестовое занятие</Link></template></el-table-column>
        <el-table-column prop="level" label="Уровень" width="100" />
        <el-table-column prop="unit" label="Тема" min-width="155" />
        <el-table-column label="Версия" width="90"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="Статус занятия" min-width="195"><template #default="{ row }"><el-select :model-value="row.status" :aria-label="'Статус занятия ' + row.external_id" :disabled="busy" @change="value => changeStatus([row.id], value)"><el-option v-for="status in sessionStatuses" :key="status.value" :value="status.value" :label="status.label" /></el-select></template></el-table-column>
        <el-table-column prop="steps" label="Вопросов" width="100" />
        <el-table-column prop="created_at" label="Создано" min-width="200" />
      </el-table>
      <pager :page="paginate" />
    </el-card>
  </shell>
</template>
