<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  text: { type: Array, default: [] },
  hRow: { type: Number, default: 28 },
  offset: { type: Number, default: 0 }
});

const textPositionStart = ref(0);
const refTextContainer = ref(null);
const textPosition = computed(() => {
  return textPositionStart.value > 0 ? `${textPositionStart.value - props.offset}px` : '50%';
});

// Отслеживаем загрузку текста и устанавливаем начальную позицию текста в пикселях (изначально 50%)
watch(
  () => props.text,
  () => {
    textPositionStart.value = parseInt(window.getComputedStyle(refTextContainer.value).top);
});

</script>

<template>
  <div class="afr-player-text-block">
    <div ref="refTextContainer" class="text-container" :style="`top: ${textPosition}`">
      <template v-if="text">
        <div
          v-for="(rowText, time) in text"
          :key="time"
          class="text-item"
          :style="`height: ${hRow}px`"
        >
          {{ rowText.text || '&nbsp;' }}
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.afr-player-text-block{
  @apply h-full overflow-hidden rounded-md bg-sky-50 p-4 flex-grow shadow-[0_0_10px_-3px_rgba(0,0,0,0.6)] relative;
}

.afr-player-text-block:before{
  @apply absolute w-full h-0.5 bg-black left-0 top-1/2 opacity-5;
  content: '';
  transform: translateY(-50%);
}

.text-container{
  @apply absolute w-full left-0 z-[1];
}

.text-item{
  @apply text-nowrap flex justify-center items-center text-sm;
}
</style>
