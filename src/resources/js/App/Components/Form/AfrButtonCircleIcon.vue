<script setup>

</script>

<template>
  <div class="afr-button-circle-icon">
    <slot></slot>
  </div>
</template>

<style scoped>
.afr-button-circle-icon{
  @apply rounded-full bg-white w-[22px] h-[22px] leading-none ;
  box-shadow: 0 0 1px 2px rgba(34, 36, 38, .15);
}
</style>
