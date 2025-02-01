<script setup>
import { ref } from 'vue';
import dayjs from 'dayjs';

const props = defineProps({
  progressLineValue: { type: Number, default: 0 },
  duration: { type: Number, default: 0 },
});
const emits = defineEmits(['changePosition']);

const durationProgressBarPos = ref(0);
const cursorPositionProgressBar = ref(0);
const durationProgressBarPosHuman = ref(0);

const mousemoveProgressBar = (e) => {
  const rect = e.currentTarget.getBoundingClientRect();
  const cursorPosition = e.clientX - rect.left;
  const durationTick = props.duration / rect.width;
  durationProgressBarPos.value = durationTick * cursorPosition;
  cursorPositionProgressBar.value = cursorPosition / (rect.width / 100);
  durationProgressBarPosHuman.value = dayjs(durationProgressBarPos.value * 1000).format('mm:ss');
}

const clickProgressBar = () => {
  emits('changePosition', durationProgressBarPos.value);
}
</script>

<template>
  <div
    class="afr-progress-bar"
    @click="clickProgressBar"
    @mousemove="mousemoveProgressBar"
  >
    <div
      v-if="durationProgressBarPos"
      class="afr-progress-bar-cursor-position"
      :style="`left: ${cursorPositionProgressBar}%`"
    >
      {{ durationProgressBarPosHuman }}
    </div>
    <div
      class="afr-progress-line"
      :style="`width: ${progressLineValue}%`"
    ></div>
  </div>
</template>

<style scoped>
.afr-progress-bar{
  @apply w-full h-4 bg-blue-100 rounded-t;
}

.afr-progress-line{
  @apply h-full bg-blue-300 rounded-tl rounded-tr;
}

.afr-progress-bar-cursor-position{
  @apply absolute bg-white text-xs justify-center w-[60px] -ms-[30px] py-1 rounded-xl -mt-7 z-[3] hidden;
}

.afr-progress-bar:hover .afr-progress-bar-cursor-position{
  @apply flex;
}
</style>
