<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import Issues from './Issues.vue';
import Pager from './Pager.vue';
import { useOperation } from './useOperation';

defineProps({ authUser: Object, reports: Object, filters: Object });
const drafts = ref({});
const statuses = [{ value: 'open', label: 'Новое' }, { value: 'in_review', label: 'В работе' }, { value: 'resolved', label: 'Решено' }, { value: 'dismissed', label: 'Отклонено' }];
const { busy, failure, run } = useOperation();
const update = (report) => run(() => api.dailyPractice.action('reports/' + report.id, drafts.value[report.id] ?? { status: report.status, editor_notes: report.editor_notes }), true);
const change = (report, field, value) => { drafts.value[report.id] = { ...(drafts.value[report.id] ?? { status: report.status, editor_notes: report.editor_notes }), [field]: value }; };
</script>
<template>
  <shell :auth-user="authUser" title="Обращения об ошибках">
    <el-form class="filters" label-position="top"><el-form-item label="Статус обращения"><el-select :model-value="filters.status || ''" clearable placeholder="Все обращения" @change="value => router.get('/admin/daily-practice/reports', value ? { status: value } : {})"><el-option v-for="status in statuses" :key="status.value" :value="status.value" :label="status.label" /></el-select></el-form-item></el-form>
    <issues :report="failure" />
    <el-card class="panel" shadow="never">
      <template #header>Обращения</template>
      <el-table :data="reports.data" border stripe empty-text="Обращений по выбранным условиям нет.">
        <el-table-column type="expand"><template #default="{ row }"><div class="report-detail">
          <p class="report-text">{{ row.text }}</p>
          <el-collapse v-if="row.answer_snapshot"><el-collapse-item title="Ответ пользователя" name="answer"><pre>{{ JSON.stringify(row.answer_snapshot, null, 2) }}</pre></el-collapse-item></el-collapse>
          <el-form label-position="top" @submit.prevent="update(row)">
            <el-form-item label="Статус"><el-select :model-value="drafts[row.id]?.status ?? row.status" :disabled="busy" @change="value => change(row, 'status', value)"><el-option v-for="status in statuses" :key="status.value" :value="status.value" :label="status.label" /></el-select></el-form-item>
            <el-form-item label="Заметка редактора"><el-input :model-value="drafts[row.id]?.editor_notes ?? row.editor_notes" type="textarea" :rows="3" maxlength="10000" :disabled="busy" @update:model-value="value => change(row, 'editor_notes', value)" /></el-form-item>
            <el-button native-type="submit" type="primary" :disabled="busy">Сохранить</el-button>
          </el-form>
        </div></template></el-table-column>
        <el-table-column prop="id" label="#" width="80" />
        <el-table-column label="Занятие" min-width="240"><template #default="{ row }"><Link :href="'/admin/daily-practice/versions/' + row.session_version_id" class="el-link el-link--primary">{{ row.session_version?.session?.external_id || 'Версия #' + row.session_version_id }}</Link><div class="muted">{{ row.task_id || 'Всё занятие' }}</div></template></el-table-column>
        <el-table-column prop="text" label="Сообщение" min-width="300" show-overflow-tooltip />
        <el-table-column label="Статус" width="150"><template #default="{ row }"><el-tag>{{ statuses.find(status => status.value === row.status)?.label || row.status }}</el-tag></template></el-table-column>
        <el-table-column prop="created_at" label="Дата" min-width="200" />
      </el-table>
      <pager :page="reports" />
    </el-card>
  </shell>
</template>
<style scoped>.report-detail { padding: 1rem; } .report-text { white-space: pre-wrap; }</style>
