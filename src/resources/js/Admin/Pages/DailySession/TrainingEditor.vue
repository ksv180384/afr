<script setup>
import { computed, ref } from 'vue';
import { trainingModes } from '@/App/Learning/training.js';
import TaskFields from './TaskFields.vue';
import PreviewPlayer from './PreviewPlayer.vue';

const props = defineProps({ session: {type: Object, required: true}, audioUrls: Object });
const mode = ref('recall');
const type = ref('single_choice');
const expanded = ref('');
const preview = ref(null);
const previewKey = ref(0);
const types = {single_choice: 'Выбор реплики', fill_blank: 'Пропуск в реплике', word_order: 'Собрать фразу'};
const items = computed(() => props.session.training_tasks?.[mode.value] ?? []);
const option = (id, lang) => ({id, text: '', text_lang: lang, ...(lang === 'fr' ? {transcription_ru: ''} : {})});
const add = () => {
  const session = props.session;
  session.training_tasks ??= {};
  const list = session.training_tasks[mode.value] ??= [];
  let n = 1;
  while (list.some(item => item.id === 'T' + n)) n++;
  const task = {id: 'T' + n, type: type.value, instruction: '', prompt: {text: '', text_lang: 'ru'},
    feedback: {correct: '', incorrect: ''}, skill: session.skills[0], objective_ids: [], vocabulary_ids: [], assessed_vocabulary_ids: []};
  if (task.type === 'word_order') {
    task.tokens = [option('w1', 'fr'), option('w2', 'fr')];
    task.accepted_orders = [['w1', 'w2']];
  } else {
    task.choices = [option('a', 'fr'), option('b', 'fr')];
    task.correct_choice_id = 'a';
  }
  if (task.type !== 'single_choice') task.completed_phrases = [{fr: '', transcription_ru: ''}];
  if (task.type === 'fill_blank') Object.assign(task, {input_mode: 'choice', text_before: '', text_after: '',
    text_before_transcription_ru: '', text_after_transcription_ru: ''});
  list.push(task);
  expanded.value = task.id;
};
const addOption = (task, field) => {
  const options = task[field];
  let n = options.length + 1;
  while (options.some(opt => opt.id === 'v' + n)) n++;
  const id = 'v' + n;
  options.push(option(id, options[0].text_lang));
  if (field === 'tokens') task.accepted_orders.forEach(order => order.push(id));
};
const setLanguage = (item, lang) => {
  item.text_lang = lang;
  if (lang === 'fr') item.transcription_ru ??= '';
  else delete item.transcription_ru;
};
const toggleAudio = (task, enabled) => {
  if (enabled) task.prompt.audio = {text: '', transcription_ru: '', lang: 'fr-FR', asset_id: null};
  else delete task.prompt.audio;
};
const openPreview = task => {
  previewKey.value++;
  preview.value = {...props.session, title: trainingModes.find(item => item.id === mode.value).title,
    tasks: [task], introductions: []};
};
</script>
<template>
  <section class="training-editor">
    <h3>Тренировки</h3>
    <el-tabs v-model="mode" @tab-change="expanded = ''; preview = null">
      <el-tab-pane v-for="entry in trainingModes" :key="entry.id" :name="entry.id"
        :label="entry.title + ' · ' + (session.training_tasks?.[entry.id]?.length ?? 0)" />
    </el-tabs>
    <p v-if="!items.length" class="muted">Для этой тренировки пока нет вопросов.</p>
    <el-collapse v-model="expanded" accordion>
      <el-collapse-item v-for="(task, index) in items" :key="task.id" :name="task.id"
        :title="task.id + ' · ' + (task.instruction || types[task.type] || task.type)">
        <el-form-item label="Навык"><el-select v-model="task.skill"><el-option v-for="skill in session.skills" :key="skill" :value="skill" /></el-select></el-form-item>
        <el-form-item label="Выражения в задании"><el-select v-model="task.vocabulary_ids" multiple
          @change="ids => task.assessed_vocabulary_ids = task.assessed_vocabulary_ids.filter(id => ids.includes(id))">
          <el-option v-for="w in session.vocabulary" :key="w.id" :value="w.id" :label="w.fr" />
        </el-select></el-form-item>
        <el-form-item label="Из них проверяются"><el-select v-model="task.assessed_vocabulary_ids" multiple>
          <el-option v-for="w in session.vocabulary.filter(w => task.vocabulary_ids.includes(w.id))" :key="w.id" :value="w.id" :label="w.fr" />
        </el-select></el-form-item>
        <el-form-item label="Язык вопроса"><el-select :model-value="task.prompt.text_lang" @change="lang => setLanguage(task.prompt, lang)">
          <el-option label="Русский" value="ru" /><el-option label="Французский" value="fr" />
        </el-select></el-form-item>
        <el-checkbox :model-value="!!task.prompt.audio" @change="enabled => toggleAudio(task, enabled)">Добавить озвучку</el-checkbox>
        <task-fields :task="task" />
        <template v-if="task.choices">
          <el-form-item label="Язык вариантов"><el-select :model-value="task.choices[0].text_lang" @change="lang => task.choices.forEach(choice => setLanguage(choice, lang))">
            <el-option value="fr" label="Французский" /><el-option value="ru" label="Русский" />
          </el-select></el-form-item>
          <el-button :disabled="task.choices.length >= 6" @click="addOption(task, 'choices')">Добавить вариант</el-button>
        </template>
        <template v-if="task.type === 'fill_blank'">
          <el-form-item label="До пропуска"><el-input v-model="task.text_before" /><el-input v-model="task.text_before_transcription_ru" placeholder="Транскрипция" /></el-form-item>
          <el-form-item label="После пропуска"><el-input v-model="task.text_after" /><el-input v-model="task.text_after_transcription_ru" placeholder="Транскрипция" /></el-form-item>
        </template>
        <template v-if="task.tokens">
          <el-form-item v-for="token in task.tokens" :key="token.id" :label="'Фрагмент ' + token.id">
            <el-input v-model="token.text" /><el-input v-model="token.transcription_ru" placeholder="Транскрипция" />
          </el-form-item>
          <el-button @click="addOption(task, 'tokens')">Добавить фрагмент</el-button>
          <p class="muted">Допустимый порядок: {{ task.accepted_orders.map(order => order.join(' → ')).join(', ') }}</p>
        </template>
        <el-form-item v-for="(phrase, i) in task.completed_phrases" :key="i" label="Полная фраза">
          <el-input v-model="phrase.fr" /><el-input v-model="phrase.transcription_ru" placeholder="Транскрипция" />
        </el-form-item>
        <div class="actions"><el-button @click="openPreview(task)">Проверить вопрос</el-button>
          <el-button type="danger" plain @click="items.splice(index, 1); preview = null">Убрать из тренировки</el-button></div>
      </el-collapse-item>
    </el-collapse>
    <div class="actions"><el-select v-model="type" aria-label="Тип нового задания">
      <el-option v-for="(label, value) in types" :key="value" :label="label" :value="value" />
    </el-select><el-button @click="add">Добавить вопрос</el-button></div>
    <div v-if="preview"><el-button @click="preview = null">Закрыть проверку</el-button>
      <preview-player :key="previewKey" :session="preview" :audio-urls="audioUrls" /></div>
  </section>
</template>
<style scoped>
.training-editor { margin: 1.25rem 0; }
.training-editor h3 { font-weight: 650; }
.training-editor .actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-block: .75rem; }
.training-editor .actions .el-select { width: min(16rem, 100%); }
</style>
