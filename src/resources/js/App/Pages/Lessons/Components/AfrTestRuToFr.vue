<script setup>
import { ref, computed, watch } from 'vue';
import { getRandomId } from '@/Helpers/helper.js';
import { useKeyboardFr } from '@/App/Components/KeyboardFr/Composables/useKeyboardFr.js';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';
import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrKeyboardFr from '@/App/Components/KeyboardFr/AfrKeyboardFr.vue';

const props = defineProps({
  words: { type: Array, default: () => [] },
});

const { refInputWord, wordInputText, addLetter } = useKeyboardFr();
const isOpenModal = ref(false);
const wordsList = ref([]);
const wordActive = ref({});
const isTestOver = ref(false);
const countErrors = ref(0);
const countWordsPassed = ref(computed(() => wordsList.value.filter(item => item.passed).length));
const isErrorCurrentWord = ref(false);
const isBlurWord = ref(true);

// Открытие модального окна и выбор случайного слова
const openModal = () => {
  isOpenModal.value = true;
  wordsList.value = props.words;
  wordInputText.value = '';
  isErrorCurrentWord.value = false;
  selectRandomWord();
};


// Закрытие модального окна и сброс теста
const closeModal = () => {
  resetTest();
}

// Пройти тест заново
const newTest = () => {
  resetTest();
  selectRandomWord();
}

// Сброс теста
const resetTest = () => {
  isTestOver.value = false;
  wordsList.value.forEach(item => {
    item.passed = false;
  });
  countErrors.value = 0;
  isErrorCurrentWord.value = false;
}

// Выбор случайного слова, которое еще не переведено
const selectRandomWord = () => {
  const wordsNotPassed = wordsList.value.filter(item => !item.passed);
  if (wordsNotPassed.length > 0) {
    const index = getRandomId(wordsNotPassed);
    wordActive.value = { ...wordsNotPassed[index] };
  } else {
    wordActive.value = {};
    isTestOver.value = true;
  }
};

// Проверка правильности перевода
const checkTranslation = () => {
  const inputVal = wordInputText.value.replace(/'/g, '').toUpperCase().trim();
  const currentWord = wordActive.value.fr ? wordActive.value.fr.toUpperCase().trim() : '';
  if (inputVal === currentWord) {
    markWordAsPassed();
    wordInputText.value = '';
    selectRandomWord();
    isErrorCurrentWord.value = false;
    isBlurWord.value = true;
  }
  if(!compareStrings(inputVal, currentWord)){
    countErrors.value++;
    isErrorCurrentWord.value = true;
  }else{
    isErrorCurrentWord.value = false;
  }
};

// Отметка слова как переведенного
const markWordAsPassed = () => {
  wordsList.value.forEach(item => {
    if (wordActive.value.fr === item.fr) {
      item.passed = true;
    }
  });
};

function compareStrings(str1, str2) {
  // Определяем длину короткой строки
  const minLength = Math.min(str1.length, str2.length);

  // Обрезаем обе строки до длины короткой
  const trimmedStr1 = str1.slice(0, minLength);
  const trimmedStr2 = str2.slice(0, minLength);

  // Проверяем, одинаковые ли строки
  return trimmedStr1 === trimmedStr2;
}

const toggleBlur = () => {
  isBlurWord.value = !isBlurWord.value;
}

watch(
  () => wordInputText.value,
  () => {
    checkTranslation();
  }
);

// Инициализация компонента
// onMounted(() => {
//   nextTick(selectRandomWord);
// });
</script>

<template>
  <div>
    <afr-button
      class="w-full py-2 font-bold"
      type="primary"
      size="small"
      @click="openModal"
    >
      Проверка знаний Ru => Fr
    </afr-button>

    <afr-dialog
      v-model="isOpenModal"
      width="500px"
      @close="closeModal"
    >
      <template #header>
        Переведите с русского на французский
      </template>

      <template v-if="!isTestOver">
        <div class="flex pt-2 justify-between">
          <div class="text-sm">
            <template v-if="countErrors > 0">
              Ошибок: <span class="text-red-500 font-bold">{{ countErrors }}</span>
            </template>
          </div>
          <div>
            {{ countWordsPassed }}/ {{ wordsList.length }}
          </div>
        </div>

        <div class="flex flex-col pb-3">
          <div
            class="text-xl font-bold text-center py-4"
            :class="{ 'text-red-500': isErrorCurrentWord }"
          >
            {{ wordActive.ru }}
          </div>
          <div
            class="text-center cursor-pointer"
            :class="{ 'blur-text': isBlurWord }"
            @click="toggleBlur"
          >
            {{ wordActive.fr }}
          </div>

          <div class="px-6 py-4">
            <afr-input-label
              ref="refInputWord"
              v-model="wordInputText"
              class="text-2xl font-semibold"
              size="large"
              :label="`Напишите перевод слова '${wordActive.ru?.split(',')[0]}'`"
            />
          </div>

          <div>
            <afr-keyboard-fr @change="addLetter"/>
          </div>
        </div>
      </template>
      <template v-else>
        <div>
          <div class="text-center py-4">
            Вы успешно прошли тест!
          </div>
          <div class="flex justify-center py-4">
            <afr-button type="primary" @click="newTest">Пройти тест еще</afr-button>
          </div>
        </div>
      </template>
    </afr-dialog>
  </div>
</template>

<style scoped>
.blur-text{
  color: transparent;
  text-shadow: 0 0 8px rgba(0, 0, 0, 0.5)
}
</style>
