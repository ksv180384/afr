<script setup>
import { computed, ref, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import PracticeSettingHeading from './PracticeSettingHeading.vue';
const { controller, levels } = defineProps({
  controller: { type: Object, required: true },
  levels: { type: Array, default: () => [] },
});
const state = controller.state;
const level = ref('');
const saved = ref(false);
const currentLevel = computed(() => state.profile?.current_level);
const currentAvailable = computed(() => levels.some(item => item.code === currentLevel.value));
const selectedAvailable = computed(() => levels.some(item => item.code === level.value));
const error = computed(() => state.operation === 'level' ? state.error : '');
const feedback = usePracticeFeedback({ error, source: 'practice-level-setting',
  status: computed(() => saved.value ? { kind: 'success', message: 'Текущий уровень сохранён.' } : null) });
watch([currentLevel, currentAvailable], ([value, available]) => {
  level.value = available ? value : '';
}, { immediate: true });

const save = async (retry = false) => {
  if (!retry && !selectedAvailable.value) return;
  saved.value = false;
  try {
    const profile = await (retry ? controller.save() : controller.saveLevel(level.value));
    if (!profile) return;
    level.value = profile.current_level;
    saved.value = true;
  } catch {}
};
</script>
<template>
  <section class="level-setting" aria-labelledby="practice-level-heading">
    <practice-setting-heading heading-id="practice-level-heading" title="Текущий уровень">
      Словарь, ошибки и расписание повторений сохранятся. Начатое занятие можно закончить на прежнем уровне,
      а следующее будет подобрано по новому. Новые слова добавляются в словарь после завершения занятия.
    </practice-setting-heading>
    <p v-if="!currentLevel && !state.error" role="status">Проверяем сохранённый уровень…</p>
    <p v-if="currentLevel && !currentAvailable">Сохранённый уровень {{ currentLevel === 'PRE_A1' ? '«С нуля»' : currentLevel }} сейчас недоступен.</p>
    <p v-if="currentLevel && !levels.length">Нет доступных уровней.</p>
    <form v-if="currentLevel && levels.length" @submit.prevent="save()">
      <label for="practice-current-level" class="sr-only">Текущий уровень</label>
      <afr-select id="practice-current-level" v-model="level" class="level-select" native :disabled="state.busy || state.pending">
        <afr-option v-if="!currentAvailable" value="" label="Выберите доступный уровень" disabled />
        <afr-option v-for="item in levels" :key="item.code" :value="item.code" :label="item.title" />
      </afr-select>
      <afr-button class="level-save" native-type="submit" type="primary" :loading="state.busy && state.operation === 'level'" :disabled="!selectedAvailable || state.busy || state.pending">Сохранить уровень</afr-button>
    </form>
    <p v-if="saved && !feedback.enabled" role="status">Текущий уровень сохранён.</p>
    <afr-button v-if="error && state.pending && !state.busy" @click="save(true)">Повторить сохранение</afr-button>
    <p v-if="error && !feedback.enabled" role="alert">{{ error }}</p>
  </section>
</template>
<style scoped>
form { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.level-select { flex: 1 1 8rem; min-width: 0; }
.level-select :deep(select) { min-height: 44px; }
.level-save { min-height: 44px; }
p { margin-block: .5rem; font-size: .875rem; }
</style>
