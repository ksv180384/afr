<script setup>
import { useSlots } from 'vue';

const props = defineProps({
  header: { type: String, default: null },
});
const slots = useSlots();
</script>

<template>
<div class="afr-card">
  <div v-if="slots.header || header" class="header">
    <template v-if="slots.header">
      <slot name="header"/>
    </template>
    <template v-else-if="header">
      {{ header }}
    </template>
  </div>
  <div class="body">
    <slot></slot>
  </div>
</div>
</template>

<style scoped>
.afr-card{
  @apply w-full
}

.afr-card .header{
  @apply py-2 rounded-sm text-center bg-blue-100 font-bold uppercase text-xs;
}

.afr-card .body{
  @apply p-2 rounded-b-sm bg-blue-50;
}
</style>
