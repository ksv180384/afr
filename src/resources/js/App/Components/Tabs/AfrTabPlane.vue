<script setup>
const props = defineProps({
  label: { type: String, default: '' },
  name: { type: String, default: '' },
});
</script>

<template>
<div class="afr-tab-plane">
  <slot/>
</div>
</template>

<style scoped>
.afr-tab-plane{
  @apply h-full;
}
</style>
