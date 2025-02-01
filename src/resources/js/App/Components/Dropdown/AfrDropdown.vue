<script setup>
import { ref } from 'vue';
import { vOnClickOutside } from '@vueuse/components';

const isShowDropdown = ref(false);


const clickOutSide = () => {
  isShowDropdown.value = false;
}

const showDropdown = () => {
  isShowDropdown.value = true;
}
</script>

<template>
<div
  class="afr-dropdown"
  @click="showDropdown"
>
  <div class="afr-dropdown-label">
    <slot></slot>
  </div>
  <div
    v-if="isShowDropdown"
    v-on-click-outside="clickOutSide"
    class="afr-dropdown-block"
  >
    <slot name="dropdown"></slot>
  </div>
</div>
</template>

<style scoped>
.afr-dropdown{
  @apply relative text-center;
}

.afr-dropdown-label{

}

.afr-dropdown-block{
  @apply absolute bg-white p-2 rounded shadow-lg right-0 mt-1;
}
</style>
