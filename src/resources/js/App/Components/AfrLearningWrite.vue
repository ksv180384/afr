<script setup>
import { ref, computed } from 'vue';
import api from '@/App/Services/Api';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';

const refInputWord = ref(null);
const words = ref([]);
const wordsWait = ref([]);
const wordInputText = ref('');
const isOpenModal = ref(false);
const isLoading = ref(false);
const letters = 'ëôêûâîùçèàé';
const countActiveWord = ref(0);
const wordActive = computed(() => words.value[countActiveWord.value]);

const openModal = async () => {
  isLoading.value = true;
  try {
    words.value = await loadWords();
    isOpenModal.value = true;
  } catch (e) {
    console.error(e);
  } finally {
    isLoading.value = false;
  }
}

const loadWords = async () => {
  try {
    const res = await api.word.getWordsLearningWrite();
    return res.words || [];
  } catch (e) {
    console.error(e);
    return [];
  }
}

const loadWordsWait = async () => {
  wordsWait.value = await loadWords();
}

const addLetter = (letter) => {
  const position = refInputWord.value.getCursorPosition();

  // текст до + вставка + текст после
  wordInputText.value = wordInputText.value.substring(0, position.start) + letter + wordInputText.value.substring(position.end);
  const focusPosition = ( position.start === position.end )? (position.end + letter.length) : position.start ;
  refInputWord.value.focus(focusPosition);
}

const onInputWord = () => {
  if(wordInputText.value?.toLowerCase() === wordActive.value.word?.toLowerCase()){
    wordInputText.value = '';

    // Подгружаем дополнительные слова, чтоб у пользователя не было ожидания загрузки, когда слова закончатся
    if(countActiveWord.value === 5){
      loadWordsWait();
    }
    // Когда слова закончились, обнуляем счетчики и подставляем ранее загруженные слова
    if(countActiveWord.value === 9){
      words.value = wordsWait.value;
      countActiveWord.value = 0;
    } else {
      countActiveWord.value++;
    }
  }
}
</script>

<template>
  <div class="w-full">
    <afr-button
      type="primary"
      class="py-2 w-full"
      :loading="isLoading"
      @click="openModal"
    >
      Учимся писать
    </afr-button>
    <afr-dialog
      v-model="isOpenModal"
      width="500px"
    >
      <template #header>
        Учимся писать
      </template>

      <div class="flex flex-col gap-3 text-center pt-5 pb-3">
        <div class="text-xl font-black">{{ wordActive.word }}</div>
        <div>{{ wordActive.transcription }}</div>
        <div class="Pervod">{{ wordActive.translation }}</div>
      </div>

      <div>
        <afr-input-label
          v-model="wordInputText"
          ref="refInputWord"
          :label="`Напишите слово ${wordActive.word}`"
          @input="onInputWord"
        />
      </div>

      <div class="letters-block">
        <template v-for="letter in letters">
          <afr-button
            class="btn-letter"
            @click="addLetter(letter)"
          >
            {{ letter }}
          </afr-button>
        </template>
      </div>
    </afr-dialog>
  </div>
</template>

<style scoped>
.letters-block{
  @apply flex flex-row justify-around my-5;
}

.btn-letter{
  @apply flex justify-center items-center w-[28px] h-[28px] font-bold;
}
</style>
