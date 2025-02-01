<script setup>

import AfrCloseBtn from '@/App/Components/Form/AfrCloseBtn.vue';

const model = defineModel({ default: false });
const { notification = null } = defineProps({ notification: String });
const emits = defineEmits(['close']);


const close = () => {
  model.value = false;
  emits('close');
}
</script>

<template>
  <div v-if="model" class="notification-success-block">
    <afr-close-btn class="absolute right-0 top-0" size="small" @click="close"/>
    {{ notification }}
  </div>
</template>

<style scoped>
.notification-success-block{
  @apply p-4 bg-green-100 border border-dashed border-green-400 rounded text-xs mt-4 relative;
}
</style>
