<script setup>

</script>

<template>
  <div class="error-text">
    <slot/>
  </div>
</template>

<style scoped>
.error-text{
  @apply text-red-500 text-xs;
}
</style>
