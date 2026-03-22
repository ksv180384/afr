<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Close, UploadFilled } from '@element-plus/icons-vue';
import dayjs from 'dayjs';

const emits = defineEmits(['setTime', 'changeActiveRowIndex']);

const audioPlayer = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const oldTime = ref(currentTime.value);
const currentTimeHuman = ref(0);
const timeInterval = ref(null);

const uploadAudio = (uploadFile) => {
  const file = uploadFile.raw;
  const file_type =  file.type;
  if (file_type.indexOf('audio') !== -1){
    audioFileInit(file);
  }
};

const removeAudio = () => {
  stop();
  audioPlayer.value = null;
}

const audioFileInit = async (file) => {
  const sound = URL.createObjectURL(file);
  audioPlayer.value = new Audio(sound);
  audioPlayer.value.onloadedmetadata = async () => {
    audioPlayer.value.volume = 0.5;
  }
};

const play = (time) => {
  // activeElementIndex.value = elementIndex
  const [minutes, seconds] = time.split(':').map(Number);
  const timeInSeconds = minutes * 60 + seconds;
  isPlaying.value = true;
  audioPlayer.value.currentTime = timeInSeconds;
  audioPlayer.value.play();
  timeInterval.value = setInterval(checkPlayerTime, 30);
}

const stop = () => {
  isPlaying.value = false;
  clearTimeout(timeInterval.value);
  if(audioPlayer.value){
    // audioPlayer.value.currentTime = 0;
    audioPlayer.value.pause();
    checkPlayerTime();
  }
}

const checkPlayerTime = () => {
  if(audioPlayer.value){
    // Устанавливаем позицию прогресс бара
    currentTime.value = audioPlayer.value.currentTime; // Текущее время проигрывания трека
    const totalMs = currentTime.value * 1000;
    currentTimeHuman.value = dayjs(totalMs)
      .format('mm:ss')
      .concat('.')
      .concat(dayjs(totalMs).format('SSS'));
  }
}

const addTimeToElement = () => {
  console.log(currentTimeHuman.value);
  // subtitlesFr.value[activeElementIndex.value].time = currentTimeHuman.value;
}

const handleKeyDown = (event) => {
  if (!isPlaying.value) return;

  const audio = audioPlayer.value;
  const skipTime = 2; // секунды для перемотки

  switch (event.code) {
    case 'ArrowLeft':
      audio.currentTime = Math.max(0, audio.currentTime - skipTime);
      break;
    case 'ArrowRight':
      audio.currentTime = Math.min(audio.duration, audio.currentTime + skipTime);
      break;
    case 'ArrowUp':
      event.preventDefault();
      emits('changeActiveRowIndex', -1);
      break;
    case 'ArrowDown':
      event.preventDefault();
      emits('changeActiveRowIndex', 1);
      break;
    case 'Period':
      play(oldTime.value)
      break;
    case 'Comma':
      stop();
      break;
    case 'Space':
      event.preventDefault();
      const totalMs = currentTime.value * 1000;
      oldTime.value = dayjs(totalMs)
        .format('mm:ss')
        .concat('.')
        .concat(dayjs(totalMs).format('SSS'));
      emits('setTime', audio.currentTime)
      break;
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});

defineExpose({
  play,
  stop
});
</script>

<template>
  <div>
    <div v-if="!audioPlayer" class="px-4">
      <el-upload
        class="upload-demo"
        drag
        :auto-upload="false"
        :show-file-list="false"
        @change="uploadAudio"
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          Перетащите сюда mp3 файл или <em>загрузите его кликнув сюда</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            mp3 файл
          </div>
        </template>
      </el-upload>
    </div>
    <div v-else class="px-4">
      <el-button
        :icon="Close"
        class="w-full"
        @click="removeAudio"
      >
        Выгрузить mp3 файл
      </el-button>
    </div>

    <div
      class="fixed bottom-0 left-1/2 transform -translate-x-1/2 text-lg bg-white px-4 py-1 cursor-pointer rounded shadow"
      @click="addTimeToElement"
    >
      {{ currentTimeHuman }}
    </div>
  </div>
</template>

<style scoped>

</style>
