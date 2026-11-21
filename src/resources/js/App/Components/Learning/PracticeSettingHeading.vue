<script setup>
import { ref } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

defineProps({
  headingId: { type: String, required: true },
  title: { type: String, required: true },
});

const expanded = ref(false);
</script>

<template>
  <div class="setting-heading">
    <h2 :id="headingId">{{ title }}</h2>
    <button
      type="button"
      :aria-label="`Подробнее о настройке «${title}»`"
      :aria-expanded="expanded"
      :aria-controls="`${headingId}-help`"
      @click="expanded = !expanded"
    >
      <daily-practice-icon name="info" />
    </button>
  </div>
  <div v-show="expanded" :id="`${headingId}-help`" class="setting-help">
    <slot />
  </div>
</template>

<style scoped>
.setting-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  margin-bottom: .375rem;
}

h2 { font-weight: 600; line-height: 1.35; }

button {
  display: grid;
  flex: 0 0 44px;
  width: 44px;
  height: 44px;
  place-items: center;
  margin-inline-end: -.5rem;
  border-radius: .5rem;
  color: #155e91;
}

button:hover, button[aria-expanded='true'] { background: #eff6ff; }
button:focus-visible { outline: 2px solid #155e91; outline-offset: 2px; }
button :deep(svg) { width: 19px; height: 19px; }

.setting-help {
  margin-bottom: .75rem;
  padding: .625rem .75rem;
  border-radius: .5rem;
  background: #eff6ff;
  color: #304760;
  font-size: .875rem;
  line-height: 1.5;
}
</style>
