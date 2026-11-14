<script setup>
import { sessionStatuses } from './editorUi';

defineProps({ field: Object, levels: Array });
const model = defineModel();
</script>

<template>
  <el-select v-if="field.key === 'level'" v-model="model" clearable :placeholder="field.label" :aria-label="field.label">
    <el-option v-for="level in levels" :key="level.code" :value="level.code" :label="level.title" />
  </el-select>
  <el-select v-else-if="field.key === 'status'" v-model="model" clearable :placeholder="field.label" :aria-label="field.label">
    <el-option v-for="status in sessionStatuses" :key="status.value" :value="status.value" :label="status.label" />
  </el-select>
  <el-date-picker
    v-else-if="field.key === 'from' || field.key === 'to'"
    v-model="model"
    type="date"
    value-format="YYYY-MM-DD"
    :placeholder="field.label"
    :aria-label="field.label"
    style="width: 100%"
  />
  <el-input v-else v-model="model" maxlength="200" clearable :placeholder="field.label" :aria-label="field.label" />
</template>
