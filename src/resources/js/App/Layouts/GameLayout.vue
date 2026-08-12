<script setup>
defineProps({
  authUser: { type: Object, default: null },
});
</script>

<template>
  <main class="game-layout">
    <slot />
  </main>
</template>

<style scoped>
.game-layout {
  min-height: 100svh;
  overflow: hidden;
  background: #82d8eb;
  color: #fff;
}
</style>
