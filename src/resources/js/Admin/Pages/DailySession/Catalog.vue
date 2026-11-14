<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Delete, Download, Hide, Upload, View, Folder } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import Issues from './Issues.vue';
import Pager from './Pager.vue';
import RowActions from './RowActions.vue';
import { useOperation } from './useOperation';
import { deleteContent } from './deleteContent';
import { statusLabel } from './editorUi';
import PronunciationNotes from '@/App/Components/Learning/PronunciationNotes.vue';

const tab = ref('levels');
const props = defineProps({ authUser: Object, levels: Array, units: Array, revisions: Object, curricula: Object, coverage: Object, filters: Object });
const topicsPerPage = 20;
const topicPage = ref(1);
const filteredLevels = computed(() => props.levels.filter(level => !props.filters.level || level.code === props.filters.level));
const filteredTopics = computed(() => props.units.filter(unit => !props.filters.level || unit.level === props.filters.level));
const visibleTopics = computed(() => {
  const offset = (topicPage.value - 1) * topicsPerPage;
  return filteredTopics.value.slice(offset, offset + topicsPerPage);
});
watch(() => props.filters.level, () => { topicPage.value = 1; });
watch(() => filteredTopics.value.length, total => {
  topicPage.value = Math.min(topicPage.value, Math.max(1, Math.ceil(total / topicsPerPage)));
});
const revisionTable = ref(null);
const backgroundInput = ref(null);
let backgroundTopic;

const chooseBackground = (topic) => {
  backgroundTopic = topic;
  backgroundInput.value.click();
};

const remove = (kind, id, title) => run(() => deleteContent(kind, id, title), true);
const diffs = ref({});
const slugs = ref({});
const confirmations = ref({});
const { busy, failure, run } = useOperation();
const action = (path, id, name) => run(() => api.dailyPractice.action(path + '/' + id + '/' + name), true);
const uploadBackground = (event) => {
  const input = event.target;
  const file = input.files[0];
  input.value = '';
  if (file && backgroundTopic) {
    const unit = backgroundTopic;
    run(() => api.dailyPractice.upload('units/' + unit.id + '/background', file), true);
  }
};
const publicationStatus = (row) => {
  if (!row.published_at) return { type: 'info', label: 'Не опубликовано' };
  if (row.archived_at) return { type: 'warning', label: 'Снято с публикации' };
  if (props.levels.some(level => level.code === row.level && level.archived_at)) {
    return { type: 'warning', label: 'Уровень снят с публикации' };
  }
  return { type: 'success', label: 'Опубликовано' };
};
const setVisibility = (kind, row) => run(async () => {
  const published = Boolean(row.archived_at);
  if (!published) {
    try {
      const consequence = kind === 'levels'
        ? 'Темы и занятия этого уровня будут скрыты из публичной практики.'
        : 'Занятия этой темы будут скрыты из публичной практики.';
      await ElMessageBox.confirm(
        'Снять с публикации «' + row.title + '»? ' + consequence,
        'Снятие с публикации',
        { confirmButtonText: 'Снять с публикации', cancelButtonText: 'Отмена', type: 'warning' },
      );
    } catch {
      return null;
    }
  }
  return api.dailyPractice.action('catalog/' + kind + '/' + row.id + '/visibility', { published });
}, true);
const renameUnit = (unit) => run(() => api.dailyPractice.action('units/' + unit.id + '/rename', { slug: slugs.value[unit.id], expected_slug: unit.slug, confirmed: confirmations.value[unit.id] }), true);
const exportJson = (kind, id) => {
  window.location.assign('/admin/daily-practice/' + kind + '/' + id + '/export');
};
const showDiff = async (row) => {
  const result = await run(() => api.dailyPractice.diff(row.id));
  if (result) {
    diffs.value[row.id] = result;
    revisionTable.value?.toggleRowExpansion(row, true);
  }
};
</script>
<template>
  <shell :auth-user="authUser" title="Уровни, темы и программы" compact-heading>
    <el-form class="filters catalog-filter">
      <el-form-item label="Уровень"><el-select :model-value="filters.level || ''" clearable placeholder="Все уровни" @change="value => router.get('/admin/daily-practice/catalog', value ? { level: value } : {}, { preserveState: true, preserveScroll: true })"><el-option v-for="level in levels" :key="level.code" :value="level.code" :label="level.code + ' · ' + level.title" /></el-select></el-form-item>
    </el-form>
    <issues :report="failure" />
    <input ref="backgroundInput" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="uploadBackground" />
    <el-tabs v-model="tab">
      <el-tab-pane lazy label="Уровни" name="levels">
        <el-table :data="filteredLevels" row-key="id" border stripe>
          <el-table-column prop="code" label="Уровень" width="100" />
          <el-table-column prop="title" label="Название" min-width="200" />
          <el-table-column prop="summary" label="Описание" min-width="300" />
          <el-table-column label="Статус" width="180">
            <template #default="{ row }">
              <el-tag :type="publicationStatus(row).type">{{ publicationStatus(row).label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column fixed="right" width="58" align="center">
            <template #default="{ row }">
              <row-actions :label="'Действия для ' + row.title" :disabled="busy">
                <el-dropdown-item v-if="row.published_at" @click="setVisibility('levels', row)" :icon="row.archived_at ? View : Hide">
                  {{ row.archived_at ? 'Вернуть в публикацию' : 'Снять с публикации' }}
                </el-dropdown-item>
                <el-dropdown-item @click="remove('levels', row.code, row.title)" :icon="Delete" divided class="delete-action">
                  Удалить
                </el-dropdown-item>
              </row-actions>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
      <el-tab-pane lazy label="Темы" name="topics">
        <el-table :data="visibleTopics" row-key="id" border stripe>
          <el-table-column prop="external_id" label="ID" />
          <el-table-column prop="title" label="Название" />
          <el-table-column prop="level" label="Уровень" width="100" />
          <el-table-column label="Статус" width="220">
            <template #default="{ row }">
              <el-tag :type="publicationStatus(row).type">{{ publicationStatus(row).label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Фон" width="110">
            <template #default="{ row }">
              <img v-if="row.background_url" :src="row.background_url" alt="" class="topic-background" loading="lazy" />
              <span v-else class="muted">—</span>
            </template>
          </el-table-column>
          <el-table-column fixed="right" width="58" align="center">
            <template #default="{ row }">
              <row-actions :label="'Действия для ' + row.title" :disabled="busy">
                <el-dropdown-item @click="chooseBackground(row)" :icon="Upload">
                  {{ row.background_url ? 'Заменить фон' : 'Загрузить фон' }}
                </el-dropdown-item>
                <el-dropdown-item v-if="row.published_at" @click="setVisibility('units', row)" :icon="row.archived_at ? View : Hide">
                  {{ row.archived_at ? 'Вернуть в публикацию' : 'Снять с публикации' }}
                </el-dropdown-item>
                <el-dropdown-item @click="remove('units', row.id, row.title)" :icon="Delete" divided class="delete-action">
                  Удалить тему
                </el-dropdown-item>
              </row-actions>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination
          v-if="filteredTopics.length > topicsPerPage"
          v-model:current-page="topicPage"
          background
          layout="total, prev, pager, next"
          :total="filteredTopics.length"
          :page-size="topicsPerPage"
          :pager-count="5"
        />
      </el-tab-pane>
      <el-tab-pane lazy label="Редакции" name="revisions">
        <p class="muted">Редакции импортируются в JSON. Опубликуйте уровень, затем тему. Отдельные чек-листы не требуются.</p>
        <el-table ref="revisionTable" :data="revisions.data" border stripe row-key="id" empty-text="Редакций пока нет. Загрузите JSON с описаниями уровней и тем.">
          <el-table-column type="expand"><template #default="{ row }">
            <div class="revision-detail">
              <p>{{ row.content.summary }}</p><ul><li v-for="outcome in row.content.learning_outcomes ?? []" :key="outcome">{{ outcome }}</li></ul><pronunciation-notes :notes="row.content.pronunciation_notes ?? []" />
              <div v-if="diffs[row.id]"><el-text>Основа: {{ diffs[row.id].base_revision }} · Сейчас: {{ diffs[row.id].current_revision }}</el-text><h3>До</h3><pre>{{ JSON.stringify(diffs[row.id].before, null, 2) }}</pre><h3>После</h3><pre>{{ JSON.stringify(diffs[row.id].after, null, 2) }}</pre></div>
              <el-form v-if="row.unit?.published_at" label-position="top" @submit.prevent="renameUnit(row.unit)">
                <el-form-item label="Новый slug темы"><el-input v-model="slugs[row.unit.id]" maxlength="120" /></el-form-item>
                <el-form-item><el-checkbox v-model="confirmations[row.unit.id]">Подтверждаю перенаправление со старого адреса темы и её занятий</el-checkbox></el-form-item>
                <el-button native-type="submit" :disabled="busy || !confirmations[row.unit.id]">Изменить адрес</el-button>
              </el-form>
            </div>
          </template></el-table-column>
          <el-table-column prop="id" label="#" width="70" /><el-table-column label="ID" min-width="170"><template #default="{ row }">{{ row.content.code || row.content.id }}</template></el-table-column>
          <el-table-column label="Название" min-width="240"><template #default="{ row }">{{ row.content.title }}</template></el-table-column>
          <el-table-column label="Статус" width="170"><template #default="{ row }"><el-tag :type="row.status === 'published' ? 'success' : 'info'">{{ row.status === 'pending' ? 'Черновик' : statusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column fixed="right" width="58" align="center">
            <template #default="{ row }">
              <row-actions :label="'Действия для ' + row.content.title" :disabled="busy">
                <el-dropdown-item @click="showDiff(row)" :icon="View">Посмотреть изменения</el-dropdown-item>
                <el-dropdown-item @click="exportJson('catalog', row.id)" :icon="Download">Экспорт JSON</el-dropdown-item>
                <el-dropdown-item @click="action('catalog', row.id, 'publish-direct')" :disabled="row.status === 'published'" :icon="Upload">
                  Опубликовать
                </el-dropdown-item>
              </row-actions>
            </template>
          </el-table-column>
        </el-table>
        <pager :page="revisions" />
      </el-tab-pane>
      <el-tab-pane lazy label="Программы" name="curricula">
        <el-alert type="info" :closable="false" :title="'Опубликовано занятий: ' + coverage.published_sessions + '. Целей в опубликованных вопросах: ' + coverage.published_objectives" />
        <el-table :data="curricula.data" border stripe empty-text="Программы пока не импортированы. Автоматическое повышение уровня недоступно.">
          <el-table-column type="expand"><template #default="{ row }"><div class="revision-detail">
            <el-table :data="row.coverage" border><el-table-column prop="objective_id" label="Обязательная цель" min-width="200" /><el-table-column prop="published_tasks" label="Опубликовано вопросов" width="200" /></el-table>
            <p>Контроли: {{ row.checkpoint_session_ids.join(', ') || 'Не заданы' }}</p><ul><li v-for="checkpoint in row.checkpoints" :key="checkpoint.id">{{ checkpoint.id }} — {{ checkpoint.ready ? 'Опубликован' : 'Не готов' }}</li></ul>
          </div></template></el-table-column>
          <el-table-column prop="external_id" label="Программа" min-width="240" /><el-table-column prop="level" label="Уровень" width="100" />
          <el-table-column label="Статус" width="170"><template #default="{ row }"><el-tag>{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column fixed="right" width="58" align="center">
            <template #default="{ row }">
              <row-actions :label="'Действия для ' + row.external_id" :disabled="busy">
                <el-dropdown-item @click="action('curricula', row.id, 'publish-direct')" :disabled="row.status === 'published'" :icon="Upload">
                  Опубликовать
                </el-dropdown-item>
                <el-dropdown-item @click="exportJson('curricula', row.id)" :icon="Download">Экспорт JSON</el-dropdown-item>
                <el-dropdown-item @click="action('curricula', row.id, 'archive')" :disabled="row.status === 'archived'" :icon="Folder">
                  В архив
                </el-dropdown-item>
                <el-dropdown-item @click="remove('curricula', row.id, row.external_id)" :icon="Delete" divided class="delete-action">
                  Удалить
                </el-dropdown-item>
              </row-actions>
            </template>
          </el-table-column>
        </el-table>
        <pager :page="curricula" page-param="curricula_page" />
      </el-tab-pane>
    </el-tabs>
  </shell>
</template>
<style scoped>
.catalog-filter.filters { margin: 0 0 .5rem; }
.delete-action { color: var(--el-color-danger); }
.el-alert { margin-bottom: .75rem; }
.revision-detail { padding: 1rem; }
.topic-background {
  width: 80px;
  height: 45px;
  border-radius: 6px;
  object-fit: cover;
}
</style>
