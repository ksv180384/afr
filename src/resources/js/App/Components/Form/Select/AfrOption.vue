<script setup>
const props = defineProps(['label', 'value', 'disabled', 'searchText']);
</script>
<template><option :value="value" :disabled="disabled" data-option>{{ label || '&nbsp;' }}</option></template>
<style scoped>
option { @apply px-3 py-0.5 hover:bg-blue-50 cursor-pointer overflow-hidden transition duration-300; }
</style>
