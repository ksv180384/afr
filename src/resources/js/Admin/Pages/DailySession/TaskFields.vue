<script setup>
defineProps({ task: {type: Object, required: true} });
</script>
<template>
  <el-form-item label="Вопрос / инструкция"><el-input v-model="task.instruction" type="textarea" /></el-form-item>
  <el-form-item v-if="task.prompt" label="Текст"><el-input v-model="task.prompt.text" type="textarea" /></el-form-item>
  <el-form-item v-if="task.prompt?.text_lang === 'fr' || task.prompt?.transcription_ru" label="Транскрипция"><el-input v-model="task.prompt.transcription_ru" type="textarea" /></el-form-item>
  <template v-if="task.prompt?.audio"><el-form-item label="Текст озвучки"><el-input v-model="task.prompt.audio.text" type="textarea" /></el-form-item><el-form-item label="Транскрипция озвучки"><el-input v-model="task.prompt.audio.transcription_ru" type="textarea" /></el-form-item></template>
  <el-form-item v-for="choice in task.choices ?? []" :key="choice.id" :label="'Вариант ' + choice.id"><el-input v-model="choice.text" /><el-input v-if="choice.text_lang === 'fr'" v-model="choice.transcription_ru" placeholder="Транскрипция" /></el-form-item>
  <el-form-item v-if="task.choices" label="Правильный вариант"><el-select v-model="task.correct_choice_id"><el-option v-for="choice in task.choices" :key="choice.id" :value="choice.id" :label="choice.id + ': ' + choice.text" /></el-select></el-form-item>
  <el-form-item label="Объяснение верного ответа"><el-input v-model="task.feedback.correct" type="textarea" /></el-form-item><el-form-item label="Объяснение ошибки"><el-input v-model="task.feedback.incorrect" type="textarea" /></el-form-item>
</template>
