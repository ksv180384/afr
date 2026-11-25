<script setup>
import AfrHelpPopover from '@/App/Components/Info/AfrHelpPopover.vue';

defineProps({
  headingId: { type: String, required: true },
  title: { type: String, required: true },
});
</script>

<template>
  <div class="setting-heading">
    <h2 :id="headingId">{{ title }}</h2>
    <afr-help-popover :label="`Подробнее о настройке «${title}»`"><slot /></afr-help-popover>
  </div>
</template>

<style scoped>
.setting-heading { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .375rem; }
h2 { font-weight: 600; line-height: 1.35; }
</style>
