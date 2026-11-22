<script setup>
import DailyPracticeIcon from './DailyPracticeIcon.vue';

defineProps({ message: { type: String, required: true } });
</script>

<template>
  <div class="practice-error-notice" role="alert">
    <daily-practice-icon name="info" />
    <p>{{ message }}</p>
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
.practice-error-notice p { min-width: 0; margin: 0; overflow-wrap: anywhere; }
</style>
