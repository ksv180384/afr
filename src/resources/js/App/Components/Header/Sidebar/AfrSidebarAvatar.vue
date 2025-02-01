<script setup>
defineProps({
  avatar: { type: String, default: '' },
});
</script>

<template>
  <div class="afr-sidebar-avatar">
    <img :src="avatar" alt="аватар" />
  </div>
</template>

<style scoped>
.afr-sidebar-avatar{
  @apply w-[180px] h-[240px] rounded-lg overflow-hidden;
}

.afr-sidebar-avatar img{
  @apply w-full h-full object-cover object-top;
}
</style>
