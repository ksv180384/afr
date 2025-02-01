<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  volume: { type: Number, default: 100 },
});
const emits = defineEmits(['change']);

onMounted(() => {
  document.addEventListener('mouseup', volumePointEnd);
  document.addEventListener('mousemove', volumePointMoveAction);
});
onUnmounted(() => {
  document.removeEventListener('mousemove', volumePointEnd);
  document.removeEventListener('mousemove', volumePointMoveAction);
});

const volumePointInfo = computed(() => {
  let val = parseInt(Math.abs(volumePoint.value - 100));
  if(!val){
    return 0;
  }
  return val;
});

const volumePoint = ref(parseInt(Math.abs(props.volume - 100)));
const volumePointMove = ref(false);
const volumePointMoveStartPosition = ref(0);
const volumeProgressPositionTop = ref(0);
const volumeProgressHeight = ref(0);
const refVolumePoint = ref(null);
const refVolumeProgress = ref(null);

const volumePointStart = (e) => {
  volumePointMove.value = true;
  volumePointMoveStartPosition.value = e.pageY;
  //const volumePointTop = refVolumePoint.value.getBoundingClientRect().top;
  const rectProgress = refVolumeProgress.value.getBoundingClientRect();
  volumeProgressHeight.value = rectProgress.height;
  volumeProgressPositionTop.value = rectProgress.top;
}

const volumePointEnd = () => {
  volumePointMove.value = false;
  volumePointMoveStartPosition.value = 0;
}

const volumePointMoveAction = (e) => {
  if(volumePointMove.value){
    const pos = ((e.pageY - volumeProgressPositionTop.value) / (volumeProgressHeight.value / 100)).toFixed(2);

    if(pos < 0 ){
      volumePoint.value = 0;
    }
    else if(pos > 100){
      volumePoint.value = 100;
    }
    else {
      volumePoint.value = pos;
    }
  }
}

watch(
  () => volumePointInfo.value,
  () => {
    emits('change', volumePointInfo.value);
  }
);
</script>

<template>
  <div class="afr-player-volume-container">
    <div class="afr-player-volume">
      <Icon icon="mingcute:volume-line" />
    </div>
    <div class="afr-volume-control">
      <div ref="refVolumeProgress" class="afr-volume-progress">
        <div
          ref="refVolumePoint"
          class="afr-volume-point"
          :style="{ top: `${volumePoint}%` }"
          @mousedown="volumePointStart"
        >
          <div v-if="volumePointMove" class="afr-volume-info">{{ volumePointInfo }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.afr-player-volume-container{
  @apply text-[22px] w-[60px] h-[60px] flex items-center justify-center relative cursor-pointer;
}

.afr-volume-control{
  @apply absolute flex-col w-12 h-[200px] -top-[200px] bg-white rounded-xl shadow-lg py-4 z-[2];
  display: flex;
}

.afr-player-volume-container .afr-volume-control{
  @apply hidden;
}

.afr-player-volume-container:hover .afr-volume-control{
  @apply flex;
}

.afr-volume-progress{
  @apply w-[8px] bg-blue-200 flex-grow rounded-xl mx-auto relative;
}

.afr-volume-point{
  @apply absolute left-1/2 w-[18px] h-[18px] bg-blue-400 shadow-xl rounded-full cursor-pointer select-none;
  transform: translate(-50%, -9px);
}

.afr-volume-info{
  @apply py-1 bg-white text-xs text-center ms-9 w-[30px] rounded;
}
</style>
