<script setup>
import { ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import Issues from './Issues.vue';
import { useOperation } from './useOperation';
import { sessionStatuses, statusLabel } from './editorUi';
import PublicSessionContent from '@/App/Components/Learning/PublicSessionContent.vue';

const props = defineProps({ authUser: Object, version: Object, validation: Object, publicMeta: Object });
const status = ref(props.version.status);
const notes = ref(props.version.editor_notes ?? '');
watch(() => props.version, (version) => { status.value = version.status; notes.value = version.editor_notes ?? ''; });
const slug = ref(props.publicMeta.session_slug ?? '');
const confirmed = ref(false);
const publicWidth = ref('100%');
const { busy, failure, run } = useOperation();
const saveStatus = () => run(() => api.dailyPractice.action('versions/status', { version_ids: [props.version.id], status: status.value, notes: notes.value }), true);
const rename = () => run(() => api.dailyPractice.action('sessions/' + props.version.session_id + '/rename', { slug: slug.value, expected_slug: props.publicMeta.session_slug, confirmed: confirmed.value }), true);
</script>
<template>
  <shell :auth-user="authUser" :title="version.title">
    <div class="actions"><el-tag>{{ statusLabel(version.status) }}</el-tag><el-text>{{ version.external_id }} · v{{ version.version }} · {{ version.level }} · {{ version.steps }} вопросов</el-text></div>
    <div class="actions"><Link :href="'/admin/daily-practice/versions/' + version.id + '/preview'"><el-button>Пройти тестовое занятие</el-button></Link><el-link :href="'/admin/daily-practice/versions/' + version.id + '/export'">Экспорт JSON</el-link><el-link v-if="version.import_batch_id" :href="'/admin/daily-practice/imports/' + version.import_batch_id + '/export'">Экспорт пакета</el-link></div>
    <issues :report="failure" />
    <el-card class="panel" shadow="never">
      <template #header>Статус занятия</template>
      <el-form label-position="top" @submit.prevent="saveStatus">
        <el-form-item label="Статус всего занятия"><el-select v-model="status" :disabled="busy" style="max-width: 300px"><el-option v-for="item in sessionStatuses" :key="item.value" :value="item.value" :label="item.label" /></el-select></el-form-item>
        <el-form-item label="Заметка редактора (необязательно)"><el-input v-model="notes" type="textarea" :rows="3" maxlength="20000" :disabled="busy" /></el-form-item>
        <el-button type="primary" native-type="submit" :loading="busy" :disabled="busy">Сохранить статус</el-button>
      </el-form>
      <p class="muted">Статус применяется ко всему занятию. Подтверждать каждый вопрос не требуется. Формат, обязательные поля и зависимости проверяются автоматически.</p>
    </el-card>
    <el-collapse>
      <el-collapse-item title="Результаты автоматической проверки" name="validation"><issues :report="validation" /></el-collapse-item>
    </el-collapse>
    <el-card class="panel" shadow="never">
      <template #header>Публичная страница</template>
      <el-descriptions :column="1" border>
        <el-descriptions-item label="Адрес">{{ publicMeta.canonical }}</el-descriptions-item>
        <el-descriptions-item label="Заголовок">{{ version.payload.public_page?.seo_title || 'Не заполнен' }}</el-descriptions-item>
        <el-descriptions-item label="Описание">{{ version.payload.public_page?.seo_description || 'Не заполнено' }}</el-descriptions-item>
      </el-descriptions>
      <el-alert v-if="publicMeta.missing.length" class="panel" type="warning" :closable="false" :title="'Не хватает: ' + publicMeta.missing.join(', ')" />
      <el-collapse>
        <el-collapse-item title="Просмотреть публичный материал" name="public">
          <div class="actions"><el-button @click="publicWidth = '100%'">Компьютер</el-button><el-button @click="publicWidth = '375px'">Телефон</el-button></div>
          <div :style="{ width: publicWidth, maxWidth: '100%' }"><public-session-content :session="version.payload" /></div>
        </el-collapse-item>
        <el-collapse-item v-if="publicMeta.session_slug" title="Изменить адрес страницы" name="rename">
          <el-form label-position="top" @submit.prevent="rename">
            <el-form-item label="Новый slug"><el-input v-model="slug" maxlength="120" /></el-form-item>
            <el-form-item><el-checkbox v-model="confirmed">Подтверждаю смену адреса и перенаправление со старого URL</el-checkbox></el-form-item>
            <el-button native-type="submit" :disabled="busy || !confirmed || slug === publicMeta.session_slug">Изменить адрес</el-button>
          </el-form>
        </el-collapse-item>
      </el-collapse>
    </el-card>
    <el-card class="panel" shadow="never">
      <template #header>Вопросы занятия · просмотр</template>
      <el-collapse><el-collapse-item v-for="task in version.payload.tasks" :key="task.id" :name="task.id" :title="task.id + ' · ' + task.instruction"><pre>{{ JSON.stringify(task, null, 2) }}</pre></el-collapse-item></el-collapse>
    </el-card>
  </shell>
</template>
