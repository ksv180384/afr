<script setup>
import { nextTick, onMounted, onUnmounted, onBeforeUnmount } from 'vue';

const emits = defineEmits(['open', 'opened', 'close', 'closed'])

onMounted(() => {
  emits('open');
  nextTick(() => {
    emits('opened');
  });
});

onBeforeUnmount(() => {
  emits('close');
});

onUnmounted(() => {
  emits('closed');
});
</script>

<template>

</template>
