<script setup>


const props = defineProps({
  isActive: { type: Boolean, default: false },
});
</script>

<template>
  <div class="link-item" :class="{ 'active': isActive }">
    <slot/>
  </div>
</template>

<style scoped>
.link-item.active{
  @apply bg-blue-200;
}
</style>
