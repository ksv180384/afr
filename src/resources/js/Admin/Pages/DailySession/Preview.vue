<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Shell from './Shell.vue';
import PreviewPlayer from './PreviewPlayer.vue';
const props = defineProps({ authUser: Object, version: Object, audioUrls: Object, frame: Boolean, scenario: String });
const width = ref('100%');
const selectedScenario = ref(props.scenario || 'session');
const restart = ref(0);
</script>
<template>
  <Head title="Тестовое занятие"><meta name="robots" content="noindex, nofollow" /></Head>
  <preview-player v-if="frame" :key="restart" :session="version.payload" :scenario="selectedScenario" :audio-urls="audioUrls" />
  <shell v-else :auth-user="authUser" title="Тестовое занятие">
    <Link :href="'/admin/daily-practice/versions/' + version.id">← К занятию {{ version.external_id }} v{{ version.version }}</Link>
    <p>Пройдите занятие как ученик: отвечайте, используйте подсказки и проверяйте результат. Проверка не меняет учебный прогресс и словарь администратора.</p>
    <div class="actions"><el-button @click="width = '100%'">Компьютер</el-button><el-button @click="width = '375px'">Телефон · 375 px</el-button><el-button type="primary" @click="restart++">Начать заново</el-button></div>
    <el-form label-position="top"><el-form-item label="Состояние интерфейса"><el-select v-model="selectedScenario" style="width: 100%"><el-option value="session" label="Обычное занятие: ошибка, помощь и результат" /><el-option value="load_error" label="Сбой загрузки и повтор запроса" /><el-option value="empty" label="Пустое занятие и словарь" /><el-option value="exhausted" label="Доступный набор закончился" /><el-option value="save" label="Черновик при выходе (только в памяти)" /></el-select></el-form-item></el-form>
    <div :style="{ width }" class="preview-player"><preview-player :key="selectedScenario + ':' + restart" :session="version.payload" :scenario="selectedScenario" :audio-urls="audioUrls" /></div>
  </shell>
</template>
<style scoped>.preview-player { max-width: 100%; margin: 1rem auto; }</style>
