<script setup>
import { computed } from 'vue';
const props = defineProps({ report: Object, reviewed: Boolean });
const details = computed(() => props.report?.details ?? props.report ?? {});
const fields = computed(() => !Array.isArray(details.value.errors) ? details.value.errors : {});
</script>
<template>
  <div v-if="report" class="panel">
    <el-alert v-if="report.message" :title="report.message" type="error" :closable="false" show-icon />
    <template v-for="kind in ['errors', 'warnings']" :key="kind">
      <el-alert v-for="(issue, index) in (Array.isArray(details[kind]) ? details[kind] : [])" :key="kind + index" class="issue" :type="kind === 'errors' ? 'error' : 'warning'" :closable="false" show-icon :title="issue.message">
        <template #default><code>{{ issue.path || '/' }}</code><span v-if="issue.session_id"> · {{ issue.session_id }}<span v-if="issue.task_id"> / {{ issue.task_id }}</span></span></template>
      </el-alert>
    </template>
    <el-alert v-for="(messages, field) in fields" :key="field" class="issue" type="error" :closable="false" :title="field + ': ' + (Array.isArray(messages) ? messages.join(' ') : messages)" />
    <el-alert v-if="details.truncated" title="Отчёт сокращён. Исправьте ошибки и загрузите файл повторно." type="warning" :closable="false" />
    <el-alert v-if="!report.message && !details.errors?.length && !details.warnings?.length" title="Ошибок автоматической проверки нет." type="success" :closable="false" show-icon />
  </div>
</template>
<style scoped>.issue { margin-top: .5rem; }</style>
