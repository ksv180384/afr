<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

defineProps({ active: String, compact: Boolean });
const emit = defineEmits(['resize']);
const element = ref(null);
let observer;
onMounted(() => {
  const measure = () => {
    if (element.value) emit('resize', Math.ceil(element.value.getBoundingClientRect().height));
  };
  measure();
  if (typeof ResizeObserver === 'function') {
    observer = new ResizeObserver(measure);
    observer.observe(element.value);
  }
});
onBeforeUnmount(() => observer?.disconnect());
const sections = [
  { id: 'learn', title: 'Учиться', path: '/practice', icon: 'book' },
  { id: 'review', title: 'Повторять', path: '/daily/vocabulary', icon: 'message' },
  { id: 'progress', title: 'Мой прогресс', path: '/daily/progress', icon: 'progress' },
];
</script>
<template>
  <nav ref="element" :class="compact ? 'compact-nav' : 'practice-nav'" aria-label="Навигация практики">
    <Link v-for="section in sections" :key="section.id" :href="section.path" :aria-current="active === section.id ? 'page' : null">
      <daily-practice-icon :name="section.icon" />
      <span>{{ section.title }}</span>
    </Link>
  </nav>
</template>

<style scoped>
.compact-nav {
  @apply grid grid-cols-[1fr_1fr_1.25fr] gap-1 border-t border-sky-100 py-1;
}

.compact-nav a {
  @apply flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-lg px-0.5 py-1
    text-center text-xs font-medium leading-tight text-sky-800 hover:bg-sky-50 focus-visible:outline-sky-700;
}

.compact-nav svg { @apply size-4; }
</style>
