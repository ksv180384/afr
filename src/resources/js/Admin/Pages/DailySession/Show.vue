<script setup>
import { ref, watch } from 'vue';
import { deleteContent } from './deleteContent';
import { router, Link } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';
import Shell from './Shell.vue';
import Issues from './Issues.vue';
import { useOperation } from './useOperation';
import { sessionStatuses, statusLabel } from './editorUi';
import PublicSessionContent from '@/App/Components/Learning/PublicSessionContent.vue';

const props = defineProps({ authUser: Object, version: Object, validation: Object, publicMeta: Object });
const draft = ref(JSON.parse(JSON.stringify(props.version.payload)));
const raw = ref('');
const advanced = ref(false);
const saveContent = () => run(async () => { const content = advanced.value ? JSON.parse(raw.value) : draft.value; return api.dailyPractice.action('versions/' + props.version.id + '/save', { content, checksum: props.version.checksum }); }, true);
const remove = () => run(async () => { const result = await deleteContent('sessions', props.version.session_id, props.version.title); if (result) router.visit('/admin/daily-practice'); return result; });
const status = ref(props.version.status);
const notes = ref(props.version.editor_notes ?? '');
watch(() => props.version, (version) => { draft.value = JSON.parse(JSON.stringify(version.payload)); advanced.value = false; status.value = version.status; notes.value = version.editor_notes ?? ''; });
const slug = ref(props.publicMeta.session_slug ?? '');
const confirmed = ref(false);
const publicWidth = ref('100%');
const { busy, failure, run } = useOperation();
const saveStatus = () => run(() => api.dailyPractice.action('versions/status', { version_ids: [props.version.id], status: status.value, notes: notes.value }), true);
const rename = () => run(() => api.dailyPractice.action('sessions/' + props.version.session_id + '/rename', { slug: slug.value, expected_slug: props.publicMeta.session_slug, confirmed: confirmed.value }), true);
</script>
<template>
  <shell :auth-user="authUser" :title="version.title">
    <div class="actions"><el-tag>{{ statusLabel(version.status) }}</el-tag><el-text>{{ version.external_id }} · {{ version.level }} · {{ version.steps }} вопросов</el-text></div>
    <div class="actions"><Link :href="'/admin/daily-practice/versions/' + version.id + '/preview'"><el-button>Пройти тестовое занятие</el-button></Link><el-link :href="'/admin/daily-practice/versions/' + version.id + '/export'">Экспорт JSON</el-link><el-link v-if="version.import_batch_id" :href="'/admin/daily-practice/imports/' + version.import_batch_id + '/export'">Экспорт пакета</el-link></div>
    <issues :report="failure" />
    <el-card class="panel" shadow="never"><template #header>Редактирование занятия</template>
      <el-switch v-model="advanced" active-text="Полный JSON" @change="value => { if (value) raw = JSON.stringify(draft, null, 2); else { try { draft = JSON.parse(raw); } catch { advanced = true; } } }" />
      <el-input v-if="advanced" v-model="raw" type="textarea" :rows="24" aria-label="Полное содержимое занятия JSON" />
      <el-form v-else label-position="top">
        <el-form-item label="Название"><el-input v-model="draft.title" /></el-form-item>
        <el-form-item label="Описание"><el-input v-model="draft.description" type="textarea" /></el-form-item>
        <el-form-item label="Продолжительность, минут"><el-input-number v-model="draft.duration_minutes" :min="1" /></el-form-item>
        <el-collapse><el-collapse-item v-for="task in draft.tasks" :key="task.id" :name="task.id" :title="task.id + ' · ' + task.instruction">
          <el-form-item label="Вопрос / инструкция"><el-input v-model="task.instruction" type="textarea" /></el-form-item>
          <el-form-item v-if="task.prompt" label="Текст"><el-input v-model="task.prompt.text" type="textarea" /></el-form-item>
          <el-form-item v-if="task.prompt?.transcription_ru" label="Транскрипция"><el-input v-model="task.prompt.transcription_ru" type="textarea" /></el-form-item>
          <template v-if="task.prompt?.audio"><el-form-item label="Текст озвучки"><el-input v-model="task.prompt.audio.text" type="textarea" /></el-form-item><el-form-item label="Транскрипция озвучки"><el-input v-model="task.prompt.audio.transcription_ru" type="textarea" /></el-form-item></template>
          <el-form-item v-for="choice in task.choices ?? []" :key="choice.id" :label="'Вариант ' + choice.id"><el-input v-model="choice.text" /><el-input v-if="choice.text_lang === 'fr'" v-model="choice.transcription_ru" placeholder="Транскрипция" /></el-form-item>
          <el-form-item v-if="task.choices" label="Правильный вариант"><el-select v-model="task.correct_choice_id"><el-option v-for="choice in task.choices" :key="choice.id" :value="choice.id" :label="choice.id + ': ' + choice.text" /></el-select></el-form-item>
          <el-form-item label="Объяснение верного ответа"><el-input v-model="task.feedback.correct" type="textarea" /></el-form-item><el-form-item label="Объяснение ошибки"><el-input v-model="task.feedback.incorrect" type="textarea" /></el-form-item>
        </el-collapse-item></el-collapse>
        <p class="muted">Словарь, вводные блоки, карточки и остальные поля доступны в режиме «Полный JSON».</p>
      </el-form>
      <div class="actions"><el-button type="primary" :loading="busy" :disabled="busy" @click="saveContent">Сохранить занятие</el-button><el-button type="danger" :disabled="busy" @click="remove">Удалить полностью</el-button></div>
    </el-card>
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
