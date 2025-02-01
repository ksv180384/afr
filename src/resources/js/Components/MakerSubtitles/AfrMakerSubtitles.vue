<script setup>
import { ref, watch, nextTick } from 'vue';
import { Icon } from '@iconify/vue';

const model = defineModel();

const refsInputSubtitles = ref([]);
const subtitles = ref(model.value ? model.value : [{ time: 0, text: '' }]);
let keyKodeActive = null;
let cursorPosition = null;

const inputSubtitlesItem = (e, index) => {
  if(keyKodeActive === 'Enter'){
    subtitles.value.splice(index + 1, 0, { time: 0, text: '' });

    const currentRowText = subtitles.value[index].text; // Получаем весь текст из textarea
    const textAfterCursor = currentRowText.substring(cursorPosition); // Извлекаем текст после курсора
    const textBeforeCursor = currentRowText.substring(0, cursorPosition); // Извлекаем текст после курсора

    subtitles.value[index].text = textBeforeCursor;
    subtitles.value[index + 1].text = textAfterCursor.replace(/\s*\n/, '');
    nextTick(() => {
      refsInputSubtitles.value[index + 1].focus();
      refsInputSubtitles.value[index + 1].setSelectionRange(0, 0);
    });
  }

  if(keyKodeActive === 'Backspace' && cursorPosition === 0 && index > 0){
    const currentRowText = subtitles.value[index].text; // Получаем весь текст из textarea
    const prevTextLength = subtitles.value[index - 1].text.length;

    subtitles.value.splice(index, 1);
    subtitles.value[index - 1].text = subtitles.value[index - 1].text + currentRowText;
    nextTick(() => {
      refsInputSubtitles.value[index - 1].focus();
      refsInputSubtitles.value[index - 1].setSelectionRange(prevTextLength, prevTextLength);
    });
  }
}

const keydownSubtitlesItem = (e, index) => {
  cursorPosition = refsInputSubtitles.value[index].selectionStart; // Получаем позицию курсора
  keyKodeActive = e.code;

  if(keyKodeActive === 'ArrowDown'){
    e.preventDefault();
    if(!refsInputSubtitles.value[index + 1]){
      return
    }
    refsInputSubtitles.value[index + 1].focus();
    refsInputSubtitles.value[index + 1].setSelectionRange(cursorPosition, cursorPosition);
  }
  if(keyKodeActive === 'ArrowUp'){
    e.preventDefault();
    if(!refsInputSubtitles.value[index - 1]){
      return
    }
    refsInputSubtitles.value[index - 1].focus();
    refsInputSubtitles.value[index - 1].setSelectionRange(cursorPosition, cursorPosition);
  }
}

const keyupSubtitlesItem = (e, index) => {
  // if(e.code === 'Enter'){
    // nextTick(() => {
    //   console.log(subtitles.value[index])
      // refsInputSubtitles.value[index].setSelectionRange(0, 0);
    // });
  // }

  if(e.code === 'Backspace'){
    inputSubtitlesItem(e, index);
  }
  keyKodeActive = null;
}

// watch(
//   () => subtitles.value,
//   (newVal)  => {
//     // model.value = subtitles.value;
//     model.value = newVal;
//   }
// )
</script>

<template>
<div class="flex flex-col">

    <template v-if="subtitles.length">
      <template v-for="(sItem, key) in subtitles">
        <div class="flex flex-row bg-white py-3">
          <div class="flex items-center px-3 py-1 cursor-pointer hover:bg-sky-100">
            <Icon icon="mingcute:play-line" />
          </div>
          <div class="flex items-center">
            <input
              v-model="subtitles[key].time"
              class="px-3 py-1 outline-none w-20"
            />
          </div>
          <div class="flex-1">
            <textarea
              ref="refsInputSubtitles"
              class="w-full px-3 py-1 outline-none resize-none overflow-hidden"
              type="text"
              placeholder="Fr"
              rows="1"
              v-model="subtitles[key].text"
              @input="(e) => inputSubtitlesItem(e, key)"
              @keydown="(e) => keydownSubtitlesItem(e, key)"
              @keyup="(e) => keyupSubtitlesItem(e, key)"
            />
          </div>
        </div>
      </template>
    </template>

</div>
</template>

<style scoped>

</style>
