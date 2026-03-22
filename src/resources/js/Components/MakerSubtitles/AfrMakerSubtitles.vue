<script setup>
import { ref, nextTick, watch } from 'vue';
import { Icon } from '@iconify/vue';
import dayjs from 'dayjs';

import AfrPlayerSubtitres from '@/Components/MakerSubtitles/AfrPlayerSubtitres.vue';

const model = defineModel();
const props = defineProps({
  fr: {type: Array, default: []},
  ru: {type: Array, default: []},
  transcription: {type: Array, default: []},
});
const emits = defineEmits(['change']);

const refsInputSubtitlesFr = ref([]);
const refsInputSubtitlesRu = ref([]);
const refsInputSubtitlesTr = ref([]);
const refPlayerSubtitres = ref();
const activeElementIndex = ref(0);
const isPlay = ref(false);
const subtitlesFr = ref(props.fr ? props.fr : [{time: 0, text: ''}]);
const subtitlesRu = ref(props.ru ? props.ru : [{time: 0, text: ''}]);
const subtitlesTr = ref(props.transcription ? props.transcription : [{time: 0, text: ''}]);
let keyKodeActive = null;

let cursorPositionFr = null;
let cursorPositionRu = null;
let cursorPositionTr = null;

const inputSubtitlesItem = (e, index, type) => {
  if (keyKodeActive === 'Enter') {
    subtitlesFr.value.splice(index + 1, 0, {time: 0, text: ''});
    subtitlesRu.value.splice(index + 1, 0, {time: 0, text: ''});
    subtitlesTr.value.splice(index + 1, 0, {time: 0, text: ''});

    if(type === 'fr'){
      const currentRowText = subtitlesFr.value[index].text; // Получаем весь текст из textarea
      const textAfterCursor = currentRowText.substring(cursorPositionFr); // Извлекаем текст после курсора
      const textBeforeCursor = currentRowText.substring(0, cursorPositionFr); // Извлекаем текст перед курсором

      subtitlesFr.value[index].text = textBeforeCursor;
      subtitlesFr.value[index + 1].text = textAfterCursor.replace(/\s*\n/, '');

      nextTick(() => {
        refsInputSubtitlesFr.value[index + 1].focus();
        refsInputSubtitlesFr.value[index + 1].setSelectionRange(0, 0);
      });
    }

    if(type === 'ru'){
      const currentRowText = subtitlesRu.value[index].text; // Получаем весь текст из textarea
      const textAfterCursor = currentRowText.substring(cursorPositionRu); // Извлекаем текст после курсора
      const textBeforeCursor = currentRowText.substring(0, cursorPositionRu); // Извлекаем текст перед курсором

      subtitlesRu.value[index].text = textBeforeCursor;
      subtitlesRu.value[index + 1].text = textAfterCursor.replace(/\s*\n/, '');

      nextTick(() => {
        refsInputSubtitlesRu.value[index + 1].focus();
        refsInputSubtitlesRu.value[index + 1].setSelectionRange(0, 0);
      });
    }

    if(type === 'tr'){
      const currentRowText = subtitlesTr.value[index].text; // Получаем весь текст из textarea
      const textAfterCursor = currentRowText.substring(cursorPositionTr); // Извлекаем текст после курсора
      const textBeforeCursor = currentRowText.substring(0, cursorPositionTr); // Извлекаем текст перед курсором

      subtitlesTr.value[index].text = textBeforeCursor;
      subtitlesTr.value[index + 1].text = textAfterCursor.replace(/\s*\n/, '');

      nextTick(() => {
        refsInputSubtitlesTr.value[index + 1].focus();
        refsInputSubtitlesTr.value[index + 1].setSelectionRange(0, 0);
      });
    }
  }

  if (keyKodeActive === 'Backspace' && (cursorPositionFr === 0 || cursorPositionRu === 0 || cursorPositionTr === 0) && index > 0) {

    if(type === 'fr' && cursorPositionFr === 0 && !subtitlesRu.value[index].text && !subtitlesTr.value[index].text){
      const currentRowText = subtitlesFr.value[index].text; // Получаем весь текст из textarea
      const prevTextLength = subtitlesFr.value[index - 1].text.length;

      subtitlesFr.value.splice(index, 1);
      subtitlesRu.value.splice(index, 1);
      subtitlesTr.value.splice(index, 1);
      subtitlesFr.value[index - 1].text = subtitlesFr.value[index - 1].text + currentRowText;
      nextTick(() => {
        refsInputSubtitlesFr.value[index - 1].focus();
        refsInputSubtitlesFr.value[index - 1].setSelectionRange(prevTextLength, prevTextLength);
      });
    }
    else if(type === 'ru'){
      const currentRowTextFr = subtitlesFr.value[index].text; // Получаем весь текст из textarea
      const currentRowTextRu = subtitlesRu.value[index].text; // Получаем весь текст из textarea
      const currentRowTextTr = subtitlesTr.value[index].text; // Получаем весь текст из textarea

      if(!currentRowTextFr && !currentRowTextRu && !currentRowTextTr){
        const prevTextLength = subtitlesRu.value[index - 1].text.length;
        subtitlesFr.value.splice(index, 1);
        subtitlesRu.value.splice(index, 1);
        subtitlesTr.value.splice(index, 1);
        subtitlesFr.value[index - 1].text = subtitlesFr.value[index - 1].text + currentRowText;
        nextTick(() => {
          refsInputSubtitlesFr.value[index - 1].focus();
          refsInputSubtitlesFr.value[index - 1].setSelectionRange(prevTextLength, prevTextLength);
        });
      }
      else if(!currentRowTextRu){
        const pos = subtitlesFr.value[index].text.length;
        nextTick(() => {
          refsInputSubtitlesFr.value[index].focus();
          refsInputSubtitlesFr.value[index].setSelectionRange(pos, pos);
        });
      }
    }
    else if(type === 'tr'){
      const currentRowTextFr = subtitlesFr.value[index].text; // Получаем весь текст из textarea
      const currentRowTextRu = subtitlesRu.value[index].text; // Получаем весь текст из textarea
      const currentRowTextTr = subtitlesTr.value[index].text; // Получаем весь текст из textarea

      if(!currentRowTextFr && !currentRowTextRu && !currentRowTextTr){
        const prevTextLength = subtitlesRu.value[index - 1].text.length;
        subtitlesFr.value.splice(index, 1);
        subtitlesRu.value.splice(index, 1);
        subtitlesTr.value.splice(index, 1);
        subtitlesFr.value[index - 1].text = subtitlesFr.value[index - 1].text + currentRowText;
        nextTick(() => {
          refsInputSubtitlesFr.value[index - 1].focus();
          refsInputSubtitlesFr.value[index - 1].setSelectionRange(prevTextLength, prevTextLength);
        });
      }
      else if(!currentRowTextTr){
        const pos = subtitlesRu.value[index].text.length;
        nextTick(() => {
          refsInputSubtitlesRu.value[index].focus();
          refsInputSubtitlesRu.value[index].setSelectionRange(pos, pos);
        });
      }
    }
  }

  // emits('change', {
  //   fr: subtitlesFr.value,
  //   ru: subtitlesRu.value,
  //   tr: subtitlesTr.value,
  // });
}

const keydownSubtitlesItem = (e, index, type) => {
  keyKodeActive = e.code;
  cursorPositionFr = refsInputSubtitlesFr.value[index].selectionStart; // Получаем позицию курсора
  cursorPositionRu = refsInputSubtitlesRu.value[index].selectionStart; // Получаем позицию курсора
  cursorPositionTr = refsInputSubtitlesTr.value[index].selectionStart; // Получаем позицию курсора

  if (keyKodeActive === 'ArrowDown') {
    e.preventDefault();
    if(type === 'fr'){
      refsInputSubtitlesRu.value[index].focus();
      refsInputSubtitlesRu.value[index].setSelectionRange(cursorPositionFr, cursorPositionFr);
    }
    if(type === 'ru'){
      refsInputSubtitlesTr.value[index].focus();
      refsInputSubtitlesTr.value[index].setSelectionRange(cursorPositionRu, cursorPositionRu);
    }
    if(type === 'tr'){
      if (!refsInputSubtitlesFr.value[index + 1]) {
        return
      }
      refsInputSubtitlesFr.value[index + 1].focus();
      refsInputSubtitlesFr.value[index + 1].setSelectionRange(cursorPositionTr, cursorPositionTr);
    }
  }

  if (keyKodeActive === 'ArrowUp') {
    e.preventDefault();
    if(type === 'fr'){
      if (!refsInputSubtitlesTr.value[index - 1]) {
        return
      }
      refsInputSubtitlesTr.value[index - 1].focus();
      refsInputSubtitlesTr.value[index - 1].setSelectionRange(cursorPositionFr, cursorPositionFr);
    }
    if(type === 'ru'){

      refsInputSubtitlesFr.value[index].focus();
      refsInputSubtitlesFr.value[index].setSelectionRange(cursorPositionRu, cursorPositionRu);
    }
    if(type === 'tr'){
      refsInputSubtitlesRu.value[index].focus();
      refsInputSubtitlesRu.value[index].setSelectionRange(cursorPositionTr, cursorPositionTr);
    }
  }
}

const keyupSubtitlesItem = (e, index, type) => {
  if (e.code === 'Backspace' && e.target.selectionStart === 0) {
    inputSubtitlesItem(e, index, type);
  }
  keyKodeActive = null;
}

const play = (indexRow, timeStart) => {
  refPlayerSubtitres.value.play(timeStart);
  activeElementIndex.value = indexRow;
  isPlay.value = true;
}

const stop = () => {
  isPlay.value = false;
  refPlayerSubtitres.value.stop();
}

// const onSetTime = (time) => {
//   const totalMs = time * 1000;
//   subtitlesFr.value[activeElementIndex.value].time = dayjs(totalMs)
//     .format('mm:ss')
//     .concat('.')
//     .concat(dayjs(totalMs).format('SSS'));
//   console.log(subtitlesFr.value)
//   if(subtitlesFr.value.length > activeElementIndex.value){
//     activeElementIndex.value++;
//   }
//   // console.log(activeElementIndex.value);
//   // console.log(subtitlesFr.value.length);
// }

const onSetTime = (time) => {
  const totalMs = time * 1000;
  const newTimeStr = dayjs(totalMs)
    .format('mm:ss')
    .concat('.')
    .concat(dayjs(totalMs).format('SSS'));

  // Устанавливаем время для текущего элемента
  subtitlesFr.value[activeElementIndex.value].time = newTimeStr;

  // Обновляем все последующие элементы, у которых время меньше нового
  for (let i = activeElementIndex.value + 1; i < subtitlesFr.value.length; i++) {
    const currentTime = subtitlesFr.value[i].time;

    // Пропускаем, если время не задано (пустая строка или null)
    if (!currentTime || currentTime === "00:00") {
      subtitlesFr.value[i].time = newTimeStr;
      continue;
    }

    // Сравниваем время в миллисекундах
    const currentTimeMs = convertTimeStringToMs(currentTime);
    if (currentTimeMs < totalMs) {
      subtitlesFr.value[i].time = newTimeStr;
    } else {
      break; // Если время больше или равно, выходим из цикла
    }
  }

  // Переходим к следующему элементу, если возможно
  if (activeElementIndex.value < subtitlesFr.value.length - 1) {
    activeElementIndex.value++;
  }
};

// Функция для конвертации строки времени в миллисекунды
const convertTimeStringToMs = (timeStr) => {
  const [mmss, ms] = timeStr.split('.');
  const [mm, ss] = mmss.split(':');
  return (parseInt(mm) * 60 + parseInt(ss)) * 1000 + parseInt(ms);
}

const onChangeActiveRowIndex = (val) => {
  activeElementIndex.value += val;
  if(activeElementIndex.value < 0){
    activeElementIndex.value = 0;
  }
  if(activeElementIndex.value >= subtitlesFr.value.length){
    activeElementIndex.value = subtitlesFr.value.length - 1;
  }
}

watch(() => props,
  (newVal) => {
    subtitlesFr.value = newVal.fr ? newVal.fr : [{time: 0, text: ''}];
    subtitlesRu.value = newVal.ru ? newVal.ru : [{time: 0, text: ''}];
    subtitlesTr.value = newVal.transcription ? newVal.transcription : [{time: 0, text: ''}];
    emits('change', {
      fr: subtitlesFr.value,
      ru: subtitlesRu.value,
      tr: subtitlesTr.value,
    });
  },
  { deep: true }
);
/*
watch(() => props.fr,
  (newVal) => {
  subtitlesFr.value = newVal ? newVal : [{time: 0, text: ''}];
    emits('change', {
      fr: subtitlesFr.value,
      ru: subtitlesRu.value,
      tr: subtitlesTr.value,
    });
  },
  { deep: true }
);

watch(() => props.ru,
  (newVal) => {
    subtitlesRu.value = newVal ? newVal : [{time: 0, text: ''}];
    emits('change', {
      fr: subtitlesFr.value,
      ru: subtitlesRu.value,
      tr: subtitlesTr.value,
    });
  },
  { deep: true }
);
watch(() => props.transcription,
  (newVal) => {
    subtitlesTr.value = newVal ? newVal : [{time: 0, text: ''}];
    emits('change', {
      fr: subtitlesFr.value,
      ru: subtitlesRu.value,
      tr: subtitlesTr.value,
    });
  },
  { deep: true }
);*/
</script>

<template>
  <div>

    <afr-player-subtitres
      ref="refPlayerSubtitres"
      @setTime="onSetTime"
      @changeActiveRowIndex="onChangeActiveRowIndex"
    />

    <div class="flex flex-col">

      <template v-if="subtitlesFr.length">
        <template v-for="(sItem, key) in subtitlesFr">
          <div class="flex flex-row py-3" :class="[activeElementIndex === key ? 'bg-green-50' : 'bg-white']">
            <div class="flex items-center cursor-pointer hover:bg-sky-100">
              <Icon v-if="!isPlay" icon="mingcute:play-line" class="w-full h-full px-3 py-1" @click="play(key, subtitlesFr[key].time)"/>
              <Icon v-else icon="mingcute:stop-line" class="w-full h-full px-3 py-1" @click="stop"/>
            </div>
            <div class="flex items-center">
              <input
                v-model="subtitlesFr[key].time"
                class="px-3 py-1 outline-none w-20 bg-transparent"
              />
            </div>
            <div class="flex-1">
            <textarea
              ref="refsInputSubtitlesFr"
              class="w-full px-3 py-1 outline-none resize-none overflow-hidden bg-transparent"
              type="text"
              placeholder="Fr"
              rows="1"
              v-model="subtitlesFr[key].text"
              @input="(e) => inputSubtitlesItem(e, key, 'fr')"
              @keydown="(e) => keydownSubtitlesItem(e, key, 'fr')"
              @keyup="(e) => keyupSubtitlesItem(e, key, 'fr')"
            />
              <textarea
                ref="refsInputSubtitlesRu"
                class="w-full px-3 py-1 outline-none resize-none overflow-hidden text-green-600 placeholder-green-600 placeholder-opacity-60 bg-transparent"
                type="text"
                placeholder="Ru"
                rows="1"
                v-model="subtitlesRu[key].text"
                @input="(e) => inputSubtitlesItem(e, key, 'ru')"
                @keydown="(e) => keydownSubtitlesItem(e, key, 'ru')"
                @keyup="(e) => keyupSubtitlesItem(e, key, 'ru')"
              />
              <textarea
                ref="refsInputSubtitlesTr"
                class="w-full px-3 py-1 outline-none resize-none overflow-hidden text-orange-400 placeholder-orange-400 placeholder-opacity-60 bg-transparent"
                type="text"
                placeholder="Транскрипция"
                rows="1"
                v-model="subtitlesTr[key].text"
                @input="(e) => inputSubtitlesItem(e, key, 'tr')"
                @keydown="(e) => keydownSubtitlesItem(e, key, 'tr')"
                @keyup="(e) => keyupSubtitlesItem(e, key, 'tr')"
              />
            </div>
          </div>
        </template>
      </template>

    </div>
  </div>
</template>

<style scoped>

</style>
