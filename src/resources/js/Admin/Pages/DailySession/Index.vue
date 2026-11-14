<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Delete, VideoPlay } from '@element-plus/icons-vue';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import RowActions from './RowActions.vue';
import LessonFilters from './LessonFilters.vue';
import AdminInfo from './AdminInfo.vue';
import Issues from './Issues.vue';
import Pager from './Pager.vue';
import { useOperation } from './useOperation';
import { deleteContent } from './deleteContent';
import { sessionStatuses } from './editorUi';

const props = defineProps({ authUser: Object, versions: Array, paginate: Object, filters: Object, levels: Array });
const { busy, failure, run } = useOperation();
const selected = ref([]);
const bulkStatus = ref('published');
watch(() => props.versions, () => { selected.value = []; });
const dateFormat = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Europe/Moscow',
});
const formatCreated = (_row, _column, value) => value ? dateFormat.format(new Date(value)) : '—';
const remove = row => run(() => deleteContent('sessions', row.session_id, row.title), true);
const changeStatus = (ids, status) => run(() => api.dailyPractice.action('versions/status', { version_ids: ids, status }), true);
</script>
<template>
  <shell :auth-user="authUser" fixed-height>
    <issues :report="failure" />
    <el-card class="lessons-card" shadow="never">
      <lesson-filters :filters="filters" :levels="levels" />
      <div class="actions lesson-actions">
        <el-text>Выбрано: {{ selected.length }}</el-text>
        <admin-info label="Как опубликовать тему">
          <p>Чтобы опубликовать новую тему целиком, выделите галочками все её занятия, выберите «Опубликовано» и нажмите «Изменить статус выбранных». Предыдущие занятия из выбранной группы будут учтены автоматически.</p>
        </admin-info>
        <el-select v-model="bulkStatus" aria-label="Статус выбранных занятий" style="width: 190px" :disabled="busy"><el-option v-for="status in sessionStatuses" :key="status.value" :value="status.value" :label="status.label" /></el-select>
        <el-button type="primary" :disabled="!selected.length || busy" :loading="busy" @click="changeStatus(selected, bulkStatus)">Изменить статус выбранных</el-button>
      </div>
      <div class="lesson-table">
        <el-table height="100%" scrollbar-always-on :data="versions" row-key="id" border stripe empty-text="Занятия не найдены. Загрузите пакет или измените фильтры." @selection-change="rows => selected = rows.map(row => row.id)">
          <el-table-column type="selection" width="48" :selectable="() => !busy" />
          <el-table-column prop="external_id" label="ID" width="145" />
          <el-table-column label="Занятие" min-width="220">
            <template #default="{ row }">
              <Link :href="'/admin/daily-practice/versions/' + row.id" class="el-link el-link--primary">{{ row.title }}</Link>
            </template>
          </el-table-column>
          <el-table-column prop="level" label="Уровень" width="100" />
          <el-table-column prop="unit" label="Тема" min-width="155" />
          <el-table-column label="Статус занятия" min-width="195"><template #default="{ row }"><el-select :model-value="row.status" :aria-label="'Статус занятия ' + row.external_id" :disabled="busy" @change="value => changeStatus([row.id], value)"><el-option v-for="status in sessionStatuses" :key="status.value" :value="status.value" :label="status.label" /></el-select></template></el-table-column>
          <el-table-column prop="steps" label="Вопросов" width="100" />
          <el-table-column prop="created_at" label="Создано" width="150" :formatter="formatCreated" />
          <el-table-column fixed="right" width="58" align="center">
            <template #default="{ row }">
              <row-actions :label="'Действия для ' + row.external_id" :disabled="busy">
                <el-dropdown-item :icon="VideoPlay" @click="router.visit('/admin/daily-practice/versions/' + row.id + '/preview')">
                  Пройти тестовое занятие
                </el-dropdown-item>
                <el-dropdown-item :icon="Delete" class="delete-action" divided @click="remove(row)">Удалить</el-dropdown-item>
              </row-actions>
            </template>
          </el-table-column>
        </el-table>
      </div>
      <pager :page="paginate" :only="['versions', 'paginate']" />
    </el-card>
  </shell>
</template>

<style scoped>
.lessons-card { flex: 1; min-height: 0; }
.lessons-card :deep(.el-card__body) {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: .75rem;
}
.lesson-actions.actions { flex-shrink: 0; margin: .6rem 0; }
.lesson-table { flex: 1; min-height: 0; min-width: 0; }
.lessons-card :deep(.el-pagination) { flex-shrink: 0; margin: .6rem 0 0; }
.delete-action { color: var(--el-color-danger); }
</style>
