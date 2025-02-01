<script setup>
import { ref, watch, nextTick } from 'vue';
import { Icon } from '@iconify/vue';

const model = defineModel();
const props = defineProps({
  fr: { type: Array, default: [] },
  ru: { type: Array, default: [] },
  transcription: { type: Array, default: [] },
});

const refsInputSubtitlesFr = ref([]);
const refsInputSubtitlesRu = ref([]);
const subtitlesFr = ref(props.fr ? props.fr : [{ time: 0, text: '' }]);
const subtitlesRu = ref(props.ru ? props.ru : [{ time: 0, text: '' }]);
const subtitlesTranscription = ref(props.transcription ? props.transcription : [{ time: 0, text: '' }]);
let keyKodeActive = null;
let cursorPosition = null;

const inputSubtitlesItemFr = (e, index) => {
  if(keyKodeActive === 'Enter'){
    subtitlesFr.value.splice(index + 1, 0, { time: 0, text: '' });
    subtitlesRu.value.splice(index + 1, 0, { time: 0, text: '' });
    subtitlesTranscription.value.splice(index + 1, 0, { time: 0, text: '' });

    const currentRowText = subtitlesFr.value[index].text; // Получаем весь текст из textarea
    const textAfterCursor = currentRowText.substring(cursorPosition); // Извлекаем текст после курсора
    const textBeforeCursor = currentRowText.substring(0, cursorPosition); // Извлекаем текст после курсора

    subtitlesFr.value[index].text = textBeforeCursor;
    subtitlesFr.value[index + 1].text = textAfterCursor.replace(/\s*\n/, '');
    nextTick(() => {
      refsInputSubtitlesFr.value[index + 1].focus();
      refsInputSubtitlesFr.value[index + 1].setSelectionRange(0, 0);
    });
  }

  if(keyKodeActive === 'Backspace' && cursorPosition === 0 && index > 0){
    const currentRowText = subtitlesFr.value[index].text; // Получаем весь текст из textarea
    const prevTextLength = subtitlesFr.value[index - 1].text.length;

    subtitlesFr.value.splice(index, 1);
    subtitlesRu.value.splice(index, 1);
    subtitlesTranscription.value.splice(index, 1);
    subtitlesFr.value[index - 1].text = subtitles.value[index - 1].text + currentRowText;
    nextTick(() => {
      refsInputSubtitlesFr.value[index - 1].focus();
      refsInputSubtitlesFr.value[index - 1].setSelectionRange(prevTextLength, prevTextLength);
    });
  }
}

const inputSubtitlesItemRu = (e, index) => {
  if(keyKodeActive === 'Enter'){
    subtitlesRu.value.splice(index + 1, 0, { time: 0, text: '' });

    const currentRowText = subtitlesRu.value[index].text; // Получаем весь текст из textarea
    const textAfterCursor = currentRowText.substring(cursorPosition); // Извлекаем текст после курсора
    const textBeforeCursor = currentRowText.substring(0, cursorPosition); // Извлекаем текст после курсора

    subtitlesRu.value[index].text = textBeforeCursor;
    subtitlesRu.value[index + 1].text = textAfterCursor.replace(/\s*\n/, '');
    nextTick(() => {
      refsInputSubtitlesRu.value[index + 1].focus();
      refsInputSubtitlesRu.value[index + 1].setSelectionRange(0, 0);
    });
  }

  if(keyKodeActive === 'Backspace' && cursorPosition === 0 && index > 0){
    const currentRowText = subtitlesRu.value[index].text; // Получаем весь текст из textarea
    const prevTextLength = subtitlesRu.value[index - 1].text.length;

    subtitlesRu.value.splice(index, 1);
    subtitlesRu.value[index - 1].text = subtitles.value[index - 1].text + currentRowText;
    nextTick(() => {
      refsInputSubtitlesRu.value[index - 1].focus();
      refsInputSubtitlesRu.value[index - 1].setSelectionRange(prevTextLength, prevTextLength);
    });
  }
}

const keydownSubtitlesItemFr = (e, index) => {
  cursorPosition = refsInputSubtitlesFr.value[index].selectionStart; // Получаем позицию курсора
  keyKodeActive = e.code;

  if(keyKodeActive === 'ArrowDown'){
    e.preventDefault();
    if(!refsInputSubtitlesFr.value[index + 1]){
      return
    }
    refsInputSubtitlesFr.value[index + 1].focus();
    refsInputSubtitlesFr.value[index + 1].setSelectionRange(cursorPosition, cursorPosition);
  }
  if(keyKodeActive === 'ArrowUp'){
    e.preventDefault();
    if(!refsInputSubtitlesFr.value[index - 1]){
      return
    }
    refsInputSubtitlesFr.value[index - 1].focus();
    refsInputSubtitlesFr.value[index - 1].setSelectionRange(cursorPosition, cursorPosition);
  }
}

const keyupSubtitlesItemFr = (e, index) => {
  if(e.code === 'Backspace'){
    inputSubtitlesItemFr(e, index);
  }
  keyKodeActive = null;
}
</script>

<template>
<div class="flex flex-col">

    <template v-if="subtitlesFr.length">
      <template v-for="(sItem, key) in subtitlesFr">
        <div class="flex flex-row bg-white py-3">
          <div class="flex items-center px-3 py-1 cursor-pointer hover:bg-sky-100">
            <Icon icon="mingcute:play-line" />
          </div>
          <div class="flex items-center">
            <input
              v-model="subtitlesFr[key].time"
              class="px-3 py-1 outline-none w-20"
            />
          </div>
          <div class="flex-1">
            <textarea
              ref="refsInputSubtitlesFr"
              class="w-full px-3 py-1 outline-none resize-none overflow-hidden"
              type="text"
              placeholder="Fr"
              rows="1"
              v-model="subtitlesFr[key].text"
              @input="(e) => inputSubtitlesItemFr(e, key)"
              @keydown="(e) => keydownSubtitlesItemFr(e, key)"
              @keyup="(e) => keyupSubtitlesItemFr(e, key)"
            />
            <textarea
              ref="refsInputSubtitlesFu"
              class="w-full px-3 py-1 outline-none resize-none overflow-hidden text-green-600 placeholder-green-600 placeholder-opacity-60"
              type="text"
              placeholder="Ru"
              rows="1"
              v-model="subtitlesRu[key].text"
              @input="(e) => inputSubtitlesItemRu(e, key)"
            />
            <textarea
              class="w-full px-3 py-1 outline-none resize-none overflow-hidden text-orange-400 placeholder-orange-400 placeholder-opacity-60"
              type="text"
              placeholder="Транскрипция"
              rows="1"
              v-model="subtitlesTranscription[key].text"
            />
          </div>
        </div>
      </template>
    </template>

</div>
</template>

<style scoped>

</style>
