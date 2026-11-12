<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

defineProps({active:String});
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
  <nav ref="element" class="practice-nav" aria-label="Навигация практики">
    <Link v-for="section in sections" :key="section.id" :href="section.path" :aria-current="active === section.id ? 'page' : null">
      <daily-practice-icon :name="section.icon" />
      <span>{{ section.title }}</span>
    </Link>
  </nav>
</template>
