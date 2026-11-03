<script setup>
import { computed } from 'vue';
import ru from 'element-plus/es/locale/lang/ru';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import { activePracticeSection, practiceSections } from './editorUi';

defineProps({ authUser: Object, title: { type: String, default: 'Задания на сегодня' } });
const page = usePage();
const active = computed(() => activePracticeSection(page.url));
</script>
<template>
  <admin-layout :auth-user="authUser" :title="title">
    <Head :title="title"><meta name="robots" content="noindex, nofollow" /></Head>
    <el-config-provider :locale="ru"><section class="daily-admin">
      <nav aria-label="Управление практикой">
        <Link v-for="section in practiceSections" :key="section.path" :href="section.path" class="practice-tab" :class="{ active: active === section.path }" :aria-current="active === section.path ? 'page' : undefined">{{ section.label }}</Link>
      </nav>
      <h1>{{ title }}</h1>
      <slot />
    </section></el-config-provider>
  </admin-layout>
</template>
<style scoped>
.daily-admin { padding: 1rem; min-width: 0; overflow-wrap: anywhere; }
nav { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--el-border-color-light); }
.practice-tab { padding: .75rem 0; border-bottom: 2px solid transparent; }
.practice-tab.active { color: var(--el-color-primary); border-bottom-color: var(--el-color-primary); }
h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 1rem; }
:deep(.panel) { margin: 1rem 0; }
:deep(.actions), :deep(.filters) { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin: 1rem 0; }
:deep(.filters .el-form-item) { margin: 0; }
:deep(.filters .el-input), :deep(.filters .el-select) { width: 200px; }
:deep(.el-card__body) { min-width: 0; }
:deep(.el-form-item__content) { min-width: 0; }
:deep(p) { line-height: 1.6; margin: .5rem 0; }
:deep(pre) { white-space: pre-wrap; overflow-wrap: anywhere; background: var(--el-fill-color-light); padding: .75rem; border-radius: 4px; font-size: .85rem; }
:deep(.muted) { color: var(--el-text-color-secondary); font-size: .9rem; }
:deep(.el-pagination) { margin: 1rem 0; flex-wrap: wrap; }
@media (max-width: 600px) {
  .daily-admin { padding: .75rem; }
  :deep(.filters .el-form-item), :deep(.filters .el-input), :deep(.filters .el-select) { width: 100%; }
  :deep(.el-card__body) { padding: 12px; }
}
</style>
