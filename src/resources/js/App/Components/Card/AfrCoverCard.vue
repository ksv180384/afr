<script setup>
import { ref, watch } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrCard from './AfrCard.vue';

defineProps({
  title: { type: String, required: true },
  image: { type: String, required: true },
});

const open = ref(false);
const hasSidebar = useMediaQuery('(min-width: 1024px)');
watch(hasSidebar, (wide) => { if (wide) open.value = false; });
</script>

<template>
  <afr-card class="cover-card" role="region" :aria-label="title" :style="{ '--card-cover': `url('${image}')` }">
    <template #header>
      <h2>
        <span class="cover-title">{{ title }}</span>
        <button
          type="button"
          class="cover-trigger"
          aria-haspopup="dialog"
          :aria-expanded="open"
          @click="open = true"
        >
          <span class="trigger-title">{{ title }}</span>
          <span v-if="$slots.summary" class="trigger-summary"><slot name="summary" /></span>
          <span class="trigger-more">
            Подробнее
            <svg viewBox="0 0 20 20" width="16" height="16" fill="none" aria-hidden="true">
              <path d="m8 5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </button>
      </h2>
    </template>
    <slot v-if="!open" :in-dialog="false" />
  </afr-card>

  <afr-dialog v-model="open" width="28rem">
    <template #header>
      <h2 tabindex="-1" autofocus class="outline-none">{{ title }}</h2>
    </template>
    <slot :in-dialog="true" />
  </afr-dialog>
</template>

<style scoped>
.cover-card {
  @apply min-w-0 overflow-hidden rounded-sm border border-sky-100 bg-white shadow-sm;
}

.cover-card :deep(.header) {
  @apply rounded-none p-0 text-left normal-case text-white;
  background-color: #193e57;
  background-image: linear-gradient(90deg, #11384ef5 8%, #12374ed6 46%, #193a5145 74%, #17364a9c), var(--card-cover);
  background-size: cover;
  background-repeat: no-repeat;
  background-position: center 53%;
}

.cover-card :deep(.body) { @apply hidden; }
.cover-title { @apply hidden; }

.cover-trigger {
  @apply flex min-h-14 w-full items-center gap-2 whitespace-nowrap px-3 text-left sm:px-4;
}

.cover-trigger:focus-visible {
  @apply outline outline-2 -outline-offset-4 outline-white;
}

.trigger-title { @apply min-w-0 truncate text-base font-bold; }
.trigger-summary { @apply hidden text-xs font-medium min-[420px]:inline; }
.trigger-more {
  @apply ml-auto inline-flex shrink-0 items-center gap-0.5 rounded-full px-2.5 py-1.5 text-xs font-semibold;
  background: #123247d9;
}

.cover-trigger:hover .trigger-more { background: #123247; }

@media (min-width: 1024px) {
  .cover-card :deep(.header) {
    background-image: linear-gradient(0deg, #0c2b42eb, #13374d80 31%, transparent 70%), var(--card-cover);
    background-position: center 51%;
  }

  .cover-trigger { display: none; }
  .cover-title { @apply flex min-h-32 items-end px-4 pb-3 text-xl font-bold leading-tight; }
  .cover-card :deep(.body) { @apply block rounded-none bg-white px-4 pb-1 pt-4; }
}
</style>
