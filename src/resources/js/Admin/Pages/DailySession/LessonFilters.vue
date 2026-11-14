<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Filter, RefreshLeft, Search } from '@element-plus/icons-vue';
import LessonFilterField from './LessonFilterField.vue';

const props = defineProps({ filters: Object, levels: Array });
const fields = [
  { key: 'search', label: 'ID или название', width: 185 },
  { key: 'level', label: 'Уровень', width: 140 },
  { key: 'unit', label: 'ID темы', width: 130 },
  { key: 'status', label: 'Статус', width: 150 },
  { key: 'from', label: 'Создано с', width: 145 },
  { key: 'to', label: 'Создано по', width: 145 },
];
const emptyFilters = () => Object.fromEntries(fields.map(({ key }) => [key, '']));
const filters = ref({ ...emptyFilters(), ...props.filters });
const toolbar = ref(null);
const visibleCount = ref(1);
const expanded = ref(false);
const searching = ref(false);
const resetting = ref(false);
const overflow = computed(() => fields.slice(visibleCount.value));
const overflowActive = computed(() => overflow.value.some(({ key }) => filters.value[key]));
let observer;

onMounted(() => {
  observer = new ResizeObserver(([entry]) => {
    const availableWidth = entry.contentRect.width;
    const total = fields.reduce((sum, field) => sum + field.width + 8, 0);
    if (total + 88 <= availableWidth) {
      visibleCount.value = fields.length;
      expanded.value = false;
      return;
    }
    let used = 144;
    const firstOverflow = fields.findIndex(({ width }) => {
      used += width + 8;
      return used > availableWidth;
    });
    visibleCount.value = Math.max(1, firstOverflow);
  });
  observer.observe(toolbar.value);
});
onBeforeUnmount(() => observer?.disconnect());

const submit = (reset = false) => {
  if (searching.value || resetting.value) return;
  const pending = reset ? resetting : searching;
  pending.value = true;
  expanded.value = false;
  router.get(
    '/admin/daily-practice',
    reset ? {} : Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value != null)),
    {
      only: ['versions', 'paginate', 'filters'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onSuccess: page => { filters.value = { ...emptyFilters(), ...page.props.filters }; },
      onFinish: () => { pending.value = false; },
    },
  );
};
</script>

<template>
  <form ref="toolbar" class="lesson-filters" aria-label="Фильтр занятий" @submit.prevent="submit()">
    <div
      v-for="field in fields.slice(0, visibleCount)"
      :key="field.key"
      class="filter-field"
      :style="{ '--field-width': field.width + 'px' }"
    >
      <lesson-filter-field v-model="filters[field.key]" :field="field" :levels="levels" />
    </div>
    <el-popover
      v-if="overflow.length"
      v-model:visible="expanded"
      trigger="click"
      placement="bottom-end"
      :width="240"
    >
      <template #reference>
        <el-button
          :icon="Filter"
          :type="overflowActive ? 'primary' : 'default'"
          :aria-expanded="expanded"
          aria-label="Дополнительные фильтры"
          title="Дополнительные фильтры"
        />
      </template>
      <div class="overflow-filters">
        <lesson-filter-field
          v-for="field in overflow"
          :key="field.key"
          v-model="filters[field.key]"
          :field="field"
          :levels="levels"
        />
        <el-button type="primary" :icon="Search" aria-label="Найти" title="Найти" @click="submit()" />
      </div>
    </el-popover>
    <el-button
      type="primary"
      native-type="submit"
      :icon="Search"
      :loading="searching"
      :disabled="resetting"
      aria-label="Найти"
      title="Найти"
    />
    <el-button
      :icon="RefreshLeft"
      :loading="resetting"
      :disabled="searching"
      aria-label="Сбросить"
      title="Сбросить"
      @click="submit(true)"
    />
  </form>
</template>

<style scoped>
.lesson-filters { display: flex; align-items: center; gap: 8px; min-width: 0; }
.lesson-filters > :deep(.el-button) { margin: 0; flex-shrink: 0; }
.filter-field { flex: 0 1 var(--field-width); min-width: 0; }
.overflow-filters { display: grid; gap: 8px; }
.overflow-filters > .el-button { justify-self: end; }
</style>
