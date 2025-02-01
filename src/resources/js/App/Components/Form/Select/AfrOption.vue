<script setup>
const props = defineProps(['label', 'value']);
</script>

<template>
  <option :value="value" data-option>{{ label || '&nbsp;' }}</option>
</template>

<style scoped>
option{
  @apply px-3 py-0.5 hover:bg-blue-50 cursor-pointer overflow-hidden transition duration-300;
}
</style>
