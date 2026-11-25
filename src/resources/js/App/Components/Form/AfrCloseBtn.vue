<script setup>
import { computed } from 'vue';

const props = defineProps({
  styleClass: { type: String, default: '' },
  size: {
    type: String,
    default: 'default',
    validator(value) {
      return ['large', 'default', 'small'].includes(value);
    }
  },
});

const classSize = computed(() => `size-${props.size}`);
</script>

<template>
  <button
    type="button"
    aria-label="Закрыть"
    class="afr-close-btn"
    :class="[styleClass, classSize]"
  >
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d="m6 6 12 12M18 6 6 18" />
    </svg>
  </button>
</template>

<style scoped>
.afr-close-btn {
  @apply grid cursor-pointer select-none place-items-center rounded
    hover:bg-red-500 hover:text-red-50 focus-visible:outline focus-visible:outline-2
    focus-visible:outline-offset-2 focus-visible:outline-blue-600 motion-safe:transition-colors;
}

.size-large {
  @apply size-11;
}
.size-default {
  @apply size-8;
}
.size-small {
  @apply size-6;
}

.afr-close-btn svg {
  @apply size-4;
}

.size-large svg {
  @apply size-5;
}
</style>
