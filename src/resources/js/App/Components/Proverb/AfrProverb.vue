<script setup>

const props = defineProps({
  proverb: { type: Object, default: {} },
});
</script>

<template>
  <div class="afr-proverb-components">
    <div class="text-center">
      {{ proverb.text }}
    </div>
    <div class="text-center text-green-600">
      {{ proverb.translation }}
    </div>
  </div>
</template>

<style scoped>

</style>
