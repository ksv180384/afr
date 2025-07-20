<script setup>
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import api from '@/App/Services/Api';
import { Icon } from '@iconify/vue';
import parse from 'id3-parser';
import { convertFileToBuffer } from 'id3-parser/lib/util';
import { useDropZone } from '@vueuse/core'
import dayjs from 'dayjs';

import AfrVolume from '@/App/Components/Player/AfrVolume.vue';
import AfrProgressBar from '@/App/Components/Player/AfrProgressBar.vue';
import AfrPlayerText from '@/App/Components/Player/AfrPlayerText.vue';
import AfrCloseBtn from '@/App/Components/Form/AfrCloseBtn.vue';
import AfrPlayerSearch from '@/App/Components/Player/AfrPlayerSearch.vue';
import InputError from '@/App/Components/InputError.vue';

const refInputMp3 = ref(null);
const refDropzone = ref(null);
const isPlay = ref(false);
const audio = ref(null);
const fileName = ref(null);
const artist = ref(null);
const track = ref(null);
const album = ref(null);
const imageLink = ref(null);
const timeInterval = ref(null);
const duration = ref(0);
const volume = ref( 0.5);
const durationHuman = ref(0);
const currentTime = ref(0);
const currentTimeHuman = ref(0);
const progressLineValue = ref(0);
const isLoadingSongText = ref(false);
const pixelsToTime = ref([]);
const offsetText = ref(0);
const oldTime = ref(0);
const hRow = ref(28);
const songText = reactive({
  fr: [],
  ru: [],
  transcription: [],
});

const play = () => {
  isPlay.value = true;
  audio.value.play();
  timeInterval.value = setInterval(checkPlayerTime, 30);
}

const pause = () => {
  isPlay.value = false;
  audio.value.pause();
  clearTimeout(timeInterval.value);
}

const stop = () => {
  isPlay.value = false;
  clearTimeout(timeInterval.value);
  if(audio.value){
    audio.value.currentTime = 0;
    offsetText.value = 0;
    oldTime.value = 0;
    checkPlayerTime();
    audio.value.pause();
  }
}

const close = () => {
  stop();
  audio.value = null;
  songText.fr = [];
  songText.ru = [];
  songText.transcription = [];
  artist.value = null;
  track.value = null;
  album.value = null;
  fileName.value = null;
  duration.value = null;
  durationHuman.value = 0;
  imageLink.value = null;
  if(refInputMp3.value){
    refInputMp3.value.value = '';
  }
}

const checkPlayerTime = () => {
  if(audio.value){
    // Устанавливаем позицию прогресс бара
    currentTime.value = audio.value.currentTime; // Текущее время проигрывания трека
    currentTimeHuman.value = dayjs(currentTime.value * 1000).format('mm:ss');
    progressLineValue.value = currentTime.value / (duration.value / 100);

    // Устанавливаем позицию текста песни (сдвиг текста относительно начальной позиции)
    let currentOffsetPixel = 0; // Текущий сдвиг в пикселях
    let nextOffsetPixel = 1;

    // Проходим по всему массиву пиксель - время и если время пикселя между текущим временем и прошлым (время пикселя
    // установленое при прошлом сдвиге), то сдвигаем на нужное кол-во пикселей
    pixelsToTime.value.forEach((pixelTime, pixelIndex) => {
      currentOffsetPixel = pixelIndex;

      if(pixelTime <= currentTime.value && pixelTime > oldTime.value){
        offsetText.value = currentOffsetPixel;
        nextOffsetPixel = currentOffsetPixel + 1;
        oldTime.value = pixelTime;
      }
    });
  }
}

const uploadFile = (e) => {

  if(typeof e.target.files[0] === 'undefined'){
    return true;
  }
  const file_type =  e.target.files[0].type;
  if (file_type.indexOf('audio') !== -1){
    audioFileInit(e.target.files[0]);
  }
};

const audioFileInit = async (file) => {
  close();
  const sound = URL.createObjectURL(file);
  audio.value = new Audio(sound);
  audio.value.onloadedmetadata = async () => {
    audio.value.volume = volume.value;

    const fileDat = await convertFileToBuffer(file);
    const tags = parse(fileDat);

    artist.value = tags.artist;
    track.value = tags.title;
    album.value = tags.album;
    fileName.value = file.name;

    duration.value = audio.value.duration;
    durationHuman.value = dayjs(duration.value * 1000).format('mm:ss');

    if(tags.image){
      const imageData = new Uint8Array(tags.image.data);
      imageLink.value = URL.createObjectURL(
        new Blob([imageData.buffer], { type: tags.image.mime } /* (1) */)
      );
    }

    progressLineValue.value = 0;

    // Поиск текста песни
    searchSongText();
  }
};

const changeVolume = (data) => {
  volume.value = (data / 100).toFixed(2);
  localStorage.setItem('player-volume', volume.value);

  if(!audio.value){
    return true;
  }
  audio.value.volume = volume.value;
  audio.value.volume = volume.value;
}

// Меняем позицию прогресбара при клике
const changePositionProgressBar = (position) => {
  if(!audio.value){
    return;
  }
  audio.value.currentTime = position;
  oldTime.value = 0;
  checkPlayerTime();
}

// Из строки, формируем массив [{ text: String, duration: Number}, ...], сортируя его по полю duration
const textToArray = (text) => {
  const textRows =  text.split("\n").map(textRow => textRow.replace(/\[.*?\]/ig, ''));
  const timesRows = text.split("\n").map(textRow => textRow.match(/\[.*?\]/ig));

  const arrTextRows = [];

  if(!textRows || !timesRows){
    return arrTextRows;
  }

  let newIndex = 0;
  timesRows.forEach((time, indexTime) => {
    if(time){
      if(time.length > 1){
        time.forEach((rowTime, index) => {
          const duration = timeToSecond(rowTime);
          arrTextRows[newIndex] = {
            text: textRows[indexTime].replace('\r', ''),
            duration: duration
          };
          newIndex++;
        });
      }else{
        const duration = timeToSecond(time[0]);
        arrTextRows[newIndex] = {
          text: textRows[indexTime].replace('\r', ''),
          duration: duration
        };
        newIndex++;
      }
    }
  });

  return arrTextRows.sort((a, b) => {
    if (a.duration < b.duration){
      return -1;
    }
    if (a.duration > b.duration){
      return 1;
    }
    return 0;
  });
}

// Время в секунды
const timeToSecond = (time) => {
  const arTime = time.split(':');
  const secInMin = 60;
  let result = 0;

  arTime.forEach((item, index) => {
    item = item.replace(/\[|\]/ig, '');
    if(index === (arTime.length - 1)){
      result += parseFloat(item);
    }else{
      result += (index + 1) * secInMin * parseFloat(item);
    }
  });
  return result;
}

// Получаем время для каждого пикселя
const getTextPositions = (arText) => {
  //console.log(arText);
  const result = [];
  arText.forEach((item, index) => {
    const next = index + 1;
    if(arText[next]){
      // Получаем продолжительность времени, которое текущая строка отображается (продолжительность прокрутки строки)
      const durationRow = arText[next].duration - arText[index].duration;
      // Получаем время за которое происходит смещение на один пиксель
      const tickOnePixel = durationRow / hRow.value;
      for (let i = 0; hRow.value > i; i++){
        result.push(arText[index].duration + ((i + 1) * tickOnePixel));
      }
    }
  });
  return result;
}

// Подгружаем текст песни если он найден
const searchSongText = async () => {

  isLoadingSongText.value = true;
  try {
    const res = await api.songText.searchByArtistTitle({ artist: artist.value, title: track.value, file_name: fileName.value });

    if(res.song?.text_fr){
      songText.fr = textToArray(res.song?.text_fr);
      songText.ru = textToArray(res.song?.text_ru);
      songText.transcription = textToArray(res.song?.text_transcription);
      pixelsToTime.value = getTextPositions(songText.fr);
    }
  } catch (e) {
    console.error(e);
  } finally {
    isLoadingSongText.value = false;
  }
}

const onLoadSearchSong = (song) => {
  songText.fr = textToArray(song.text_fr);
  songText.ru = textToArray(song.text_ru);
  songText.transcription = textToArray(song.text_transcription);
  pixelsToTime.value = getTextPositions(songText.fr);
}

const onDrop = (files) => {

  let list = new DataTransfer();
  const blob = files[0].slice(0, files[0].size, files[0].type);
  let file = new File([blob], files[0].name, { type: files[0].type });
  list.items.add(file);

  refInputMp3.value.files = list.files;
  const event = new Event('change');
  refInputMp3.value.dispatchEvent(event);
}

const { isOverDropZone } = useDropZone(refDropzone, {
  onDrop,
});

onMounted(() => {
  if(localStorage.getItem('player-volume')){
    volume.value = localStorage.getItem('player-volume');
  }
});

onUnmounted(() => {
  close();
});
</script>

<template>
  <div class="afr-player-component">

    <div>
      <div v-if="fileName" class="lg:px-0 px-4">
        <div class="afr-player-file-name">{{ fileName }}</div>
        <div class="mt-2 flex flex-row items-center justify-between">
          <div class="text-sm lg:text-xs">Продолжительность: {{ durationHuman }} мин</div>
          <afr-close-btn size="small" title="Отмена" @click="close"/>
        </div>

        <afr-player-search
          class="mt-2"
          @loadSong="onLoadSearchSong"
        />

        <template v-if="!songText.fr.length">
          <InputError message="Текст не найден, попробуйте найти его через поиск"/>
        </template>

      </div>
      <div
        v-else
        class="afr-player-load-file"
        :class="{ 'is-over-drop-zone': isOverDropZone }"
      >
        <div
          ref="refDropzone"
          class="afr-player-info"
          @click="() => refInputMp3.click()"
        >
          Перетащите mp3 файл или нажмите на выделенную область. Программа попытается сама найти текст, если не найдет,
          то введите исполнителя и название необходимой песни.
          <div class="text-center py-1 text-lg">mp3</div>
        </div>
      </div>
      <input ref="refInputMp3" type="file" @change="uploadFile" hidden/>
    </div>

    <div
      v-if="fileName"
      class="afr-player-bar"
    >

      <AfrPlayerText
        :fr="songText.fr"
        :ru="songText.ru"
        :transcription="songText.transcription"
        :current-time="currentTime"
        :h-row="hRow"
        :offset="offsetText"
      />

      <AfrProgressBar
        :progress-line-value="progressLineValue"
        :duration="duration"
        @changePosition="changePositionProgressBar"
      />

      <div class="afr-player-controls">

        <div v-if="!isPlay" class="afr-player-btn" title="Play" @click="play">
          <Icon icon="mingcute:play-line" />
        </div>
        <div v-else class="afr-player-btn" title="Pause" @click="pause">
          <Icon icon="mingcute:pause-fill" />
        </div>
        <div class="afr-player-btn" title="Stop" @click="stop">
          <Icon icon="mingcute:stop-line" />
        </div>

        <div class="flex flex-col flex-1 lg:flex-row">

          <div class="afr-track-info">
            <div class="afr-track-tags">
              <div class="afr-artist">
                {{ artist }}
              </div>
              <div class="afr-track">
                {{ track }}
              </div>
            </div>
            <div v-if="imageLink" class="afr-track-image">
              <img :src="imageLink" :alt="album" :title="album"/>
            </div>
          </div>

          <div v-if="durationHuman" class="afr-track-time">
            {{currentTimeHuman}} / {{ durationHuman }}
          </div>

        </div>

        <AfrVolume :volume="volume * 100" @change="changeVolume"/>

        <div class="afr-player-btn"  title="Close" @click="close">
          <Icon icon="mingcute:close-fill" />
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.afr-player-component{

}

.afr-player-bar{
  @apply fixed bottom-0 lg:max-w-[1440px] w-full mx-auto z-10 rounded-t lg:-ms-2;
}

.afr-player-load-file{
  @apply p-2 mx-2 border-blue-500 border rounded-lg border-dashed relative;
}

.is-over-drop-zone{
  @apply bg-white border-blue-800;
}

.afr-player-info{
  @apply lg:text-xs text-lg lg:leading-5 leading-8 cursor-pointer;
}

.afr-player-file-name{
  @apply  text-lg lg:text-xs font-semibold text-nowrap truncate text-center;
}

.afr-player-controls{
  @apply flex flex-row justify-center items-center px-4 text-[32px] bg-blue-50;
}

.afr-player-btn{
  @apply px-3 lg:px-2 py-4 lg:p-4 cursor-pointer hover:bg-blue-100;
}

.afr-track-info{
  @apply flex items-center lg:ms-2.5 flex-grow;
}

.afr-track-tags{

}

.afr-track-image{
  @apply ms-2;
}

.afr-track-image img{
  @apply w-[60px] h-[60px] object-contain hidden lg:block;
}

.afr-artist{
  @apply text-sm;
}

.afr-track{
  @apply text-sm font-bold;
}

.afr-track-time{
  @apply text-xs lg:text-sm flex justify-start items-center;
}
</style>
