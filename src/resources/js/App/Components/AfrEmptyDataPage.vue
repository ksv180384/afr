<script setup>
const props = defineProps({
  text: { type: String, required: true },
});
</script>

<template>
  <div class="flex-1 flex py-24 justify-center text-3xl text-gray-500 bg-sky-100">
    {{ text }}
  </div>
</template>

<style scoped>

</style>
