<script setup>
import { computed, ref } from 'vue';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import BulkImport from './BulkImport.vue';
import Issues from './Issues.vue';
import { useOperation } from './useOperation';

defineProps({ authUser: Object });
const tab = ref('single');
const { busy: operationBusy, failure, run } = useOperation();
const bulkBusy = ref(false);
const busy = computed(() => operationBusy.value || bulkBusy.value);
const file = ref(null);
const preview = ref(null);
const receipt = ref(null);
const canCommit = computed(() => preview.value?.valid && !receipt.value);
const selectFile = (upload) => { file.value = upload.raw; preview.value = null; receipt.value = null; failure.value = null; };
const inspect = async () => { const result = await run(() => api.dailyPractice.upload('imports/preview', file.value)); if (result) preview.value = result; };
const commit = async () => {
  if (!canCommit.value) return;
  const result = await run(() => api.dailyPractice.action('imports/' + preview.value.batch_id + '/commit', { token: preview.value.token, file_hash: preview.value.file_hash }));
  if (result) receipt.value = result;
};

const changeStatus = (ids, status) => run(() => api.dailyPractice.action('versions/status', { version_ids: ids, status }));
</script>

<template>
  <shell :auth-user="authUser" title="Импорт" compact-heading>
    <el-tabs v-model="tab">
      <el-tab-pane label="Импорт занятий" name="single" :disabled="bulkBusy">
        <el-card class="panel" shadow="never">
          <p class="muted">Загрузите JSON-пакет. Проверка формата выполняется автоматически; новые занятия создаются черновиками, существующие заменяются с сохранением статуса.</p>
          <div class="actions">
            <el-upload :auto-upload="false" :show-file-list="false" accept=".json,application/json" :disabled="busy" :on-change="selectFile"><el-button :disabled="busy">Выбрать JSON</el-button></el-upload>
            <el-text v-if="file">{{ file.name }}</el-text>
            <el-button type="primary" :disabled="!file || busy" :loading="busy" @click="inspect">Проверить файл</el-button>
          </div>
          <div class="actions"><el-link href="/admin/daily-practice/templates/pre-a1-u01">Два образца JSON</el-link><el-link href="/admin/daily-practice/templates/task-types">Все типы вопросов</el-link><el-link href="/admin/daily-practice/schema">Схема v1</el-link></div>
          <template v-if="preview">
            <el-table :data="preview.plan.sessions" border stripe empty-text="В пакете нет занятий">
              <el-table-column prop="id" label="Занятие" min-width="200" />
              <el-table-column label="Действие" min-width="200"><template #default="{ row }">{{ row.action === 'replace' ? 'Заменить занятие' : 'Создать черновик' }}</template></el-table-column>
            </el-table>
            <p class="muted">Редакций уровня/темы: {{ preview.plan.catalog_revisions }}. Программ: {{ preview.plan.curricula.length }}.</p>
            <issues :report="preview" />
            <el-button type="success" :disabled="!canCommit || busy" @click="commit">Импортировать занятия</el-button>
          </template>
          <el-button v-if="receipt?.version_ids?.length" type="primary" :disabled="busy" @click="changeStatus(receipt.version_ids, 'published')">Опубликовать импортированные занятия</el-button>
          <el-alert v-if="receipt" class="panel" type="success" :closable="false" show-icon :title="'Импорт завершён. Создано: ' + receipt.created_version_ids.length + ', обновлено: ' + (receipt.updated_version_ids?.length ?? 0)" />
        </el-card>

        <issues :report="failure" />
      </el-tab-pane>
      <el-tab-pane label="Массовый импорт занятий" name="bulk" :disabled="operationBusy">
        <bulk-import :disabled="operationBusy" @busy="bulkBusy = $event" />
      </el-tab-pane>
    </el-tabs>
  </shell>
</template>
