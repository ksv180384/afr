<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import Issues from './Issues.vue';
import Pager from './Pager.vue';
import { useOperation } from './useOperation';
import { statusLabel } from './editorUi';
import PronunciationNotes from '@/App/Components/Learning/PronunciationNotes.vue';

defineProps({ authUser: Object, levels: Array, revisions: Object, curricula: Object, coverage: Object, filters: Object });
const diffs = ref({});
const slugs = ref({});
const confirmations = ref({});
const { busy, failure, run } = useOperation();
const action = (path, id, name) => run(() => api.dailyPractice.action(path + '/' + id + '/' + name), true);
const renameUnit = (unit) => run(() => api.dailyPractice.action('units/' + unit.id + '/rename', { slug: slugs.value[unit.id], expected_slug: unit.slug, confirmed: confirmations.value[unit.id] }), true);
const diff = async (id) => { const result = await run(() => api.dailyPractice.diff(id)); if (result) diffs.value[id] = result; };
</script>
<template>
  <shell :auth-user="authUser" title="Уровни, темы и программы">
    <el-form class="filters" label-position="top">
      <el-form-item label="Уровень"><el-select :model-value="filters.level || ''" clearable placeholder="Все уровни" @change="value => router.get('/admin/daily-practice/catalog', value ? { level: value } : {})"><el-option v-for="level in levels" :key="level.code" :value="level.code" :label="level.code + ' · ' + level.title" /></el-select></el-form-item>
    </el-form>
    <issues :report="failure" />
    <el-collapse>
      <el-collapse-item title="Текущие описания уровней" name="levels">
        <el-table :data="levels" border stripe>
          <el-table-column prop="code" label="Уровень" width="100" /><el-table-column prop="title" label="Название" min-width="200" /><el-table-column prop="summary" label="Описание" min-width="300" />
          <el-table-column label="Статус" width="170"><template #default="{ row }"><el-tag :type="row.published_at ? 'success' : 'info'">{{ row.published_at ? 'Опубликовано' : 'Не опубликовано' }}</el-tag></template></el-table-column>
        </el-table>
      </el-collapse-item>
    </el-collapse>
    <el-card class="panel" shadow="never">
      <template #header>Описания уровней и тем</template>
      <p class="muted">Редакции импортируются в JSON. Опубликуйте уровень, затем тему. Отдельные чек-листы не требуются.</p>
      <el-table :data="revisions.data" border stripe row-key="id" empty-text="Редакций пока нет. Загрузите JSON с описаниями уровней и тем.">
        <el-table-column type="expand"><template #default="{ row }">
          <div class="revision-detail">
            <p>{{ row.content.summary }}</p><ul><li v-for="outcome in row.content.learning_outcomes ?? []" :key="outcome">{{ outcome }}</li></ul><pronunciation-notes :notes="row.content.pronunciation_notes ?? []" />
            <div class="actions"><el-button :disabled="busy" @click="diff(row.id)">Посмотреть изменения</el-button><el-link :href="'/admin/daily-practice/catalog/' + row.id + '/export'">Экспорт JSON</el-link></div>
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
        <el-table-column label="Действие" width="180"><template #default="{ row }"><el-button type="primary" :disabled="busy || row.status === 'published'" @click="action('catalog', row.id, 'publish-direct')">Опубликовать</el-button></template></el-table-column>
      </el-table>
      <pager :page="revisions" />
    </el-card>
    <el-card class="panel" shadow="never">
      <template #header>Программы повышения уровня</template>
      <el-alert type="info" :closable="false" :title="'Опубликовано занятий: ' + coverage.published_sessions + '. Целей в опубликованных вопросах: ' + coverage.published_objectives" />
      <el-table :data="curricula.data" border stripe empty-text="Программы пока не импортированы. Автоматическое повышение уровня недоступно.">
        <el-table-column type="expand"><template #default="{ row }"><div class="revision-detail">
          <el-table :data="row.coverage" border><el-table-column prop="objective_id" label="Обязательная цель" min-width="200" /><el-table-column prop="published_tasks" label="Опубликовано вопросов" width="200" /></el-table>
          <p>Контроли: {{ row.checkpoint_session_ids.join(', ') || 'Не заданы' }}</p><ul><li v-for="checkpoint in row.checkpoints" :key="checkpoint.id">{{ checkpoint.id }} — {{ checkpoint.ready ? 'Опубликован' : 'Не готов' }}</li></ul>
          <el-link :href="'/admin/daily-practice/curricula/' + row.id + '/export'">Экспорт программы JSON</el-link>
        </div></template></el-table-column>
        <el-table-column prop="external_id" label="Программа" min-width="240" /><el-table-column prop="level" label="Уровень" width="100" /><el-table-column prop="version" label="Версия" width="90" />
        <el-table-column label="Статус" width="170"><template #default="{ row }"><el-tag>{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
        <el-table-column label="Действия" min-width="280"><template #default="{ row }"><el-button type="primary" :disabled="busy || row.status === 'published'" @click="action('curricula', row.id, 'publish-direct')">Опубликовать</el-button><el-button :disabled="busy || row.status === 'archived'" @click="action('curricula', row.id, 'archive')">В архив</el-button></template></el-table-column>
      </el-table>
      <pager :page="curricula" />
    </el-card>
  </shell>
</template>
<style scoped>.revision-detail { padding: 1rem; }</style>
