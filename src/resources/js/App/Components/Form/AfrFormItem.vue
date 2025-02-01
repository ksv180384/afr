<script setup>
import { watch } from 'vue';

import AfrInputErrorMessage from '@/components/form/AfrInputErrorMessage.vue';

const props = defineProps({
  name: { type: String, default: '' },
  error: { type: String, default: '' },
});

console.log(props);

watch(
  () => props.error,
  (newVal) => {
    console.log(newVal);
  },
  { deep: true }
);
</script>

<template>
<div>
  <slot/>
  <afr-input-error-message>{{ error }} ---</afr-input-error-message>
</div>
</template>

<style scoped>

</style>
