<script setup>
import DailyPracticeIcon from './DailyPracticeIcon.vue';

defineProps({
  title: String,
  message: { type: String, required: true },
  announce: { type: Boolean, default: true },
});
</script>

<template>
  <div class="practice-error-notice" :role="announce ? 'alert' : 'note'">
    <daily-practice-icon name="info" />
    <div class="notice-content">
      <p v-if="title" class="notice-title">{{ title }}</p>
      <p>{{ message }}</p>
    </div>
  </div>
</template>

<style scoped>
.practice-error-notice {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  margin-block: .75rem;
  padding: .75rem 1rem;
  border: 1px solid #edc4c9;
  border-radius: var(--practice-radius, .85rem);
  background: #fdf3f4;
  color: #862f3a;
  line-height: 1.5;
}
.notice-content { min-width: 0; overflow-wrap: anywhere; }
.notice-content p { margin: 0; }
.notice-content .notice-title { margin-bottom: .2rem; font-weight: 700; }
</style>
