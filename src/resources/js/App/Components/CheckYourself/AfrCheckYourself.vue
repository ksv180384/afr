<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useElementSize } from '@vueuse/core';
import { speak } from '@/Helpers/helper';
import api from '@/App/Services/Api';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrPlayerWord from '@/App/Components/AfrPlayerWord.vue';
import AfrWordItem from "@/App/Components/CheckYourself/AfrWordItem.vue";

const refHistoryWordsContainer = ref(null);
const balls = ref(0);
const words = ref([]);
const wordActiveCount = ref(0);
const wordActive = computed(() => words.value[wordActiveCount.value] ? words.value[wordActiveCount.value] : []);
const historyWords = ref([]);
const isOpenModal = ref(false);
const isLoading = ref(false);

const openModal = async () => {
  historyWords.value = [];
  isLoading.value = true;
  try {
    words.value = await loadWords();
    isOpenModal.value = true;
    speak(wordActive.value.answer.word);
  } catch (e) {
    console.error(e);
  } finally {
    isLoading.value = false;
  }
}

// TODO сделать сохранение баллов
const check = async (isCorrect) => {
  const wordHistoryItem = wordActive.value.answer;

  if(isCorrect){
    if(wordActiveCount.value === 9){
      words.value = await loadWords();
      wordActiveCount.value = 0;
      balls.value = 0;
    }else{
      wordActiveCount.value++;
    }

    speak(wordActive.value.answer.word);
    balls.value++;
    if(!historyWords.value.length || historyWords.value[historyWords.value.length - 1].id !== wordHistoryItem.id){
      historyWords.value.push({ ...wordHistoryItem,  is_true_answer: true });
      scrollToBottom();
    }
  }else {
    balls.value--;
    if(!historyWords.value.length || historyWords.value[historyWords.value.length - 1].id !== wordHistoryItem.id){
      historyWords.value.push({ ...wordHistoryItem,  is_true_answer: false });
      scrollToBottom();
    }
  }
}

const loadWords = async () => {
  const res = await api.word.getWordsTestYourself();
  return res.words;
}

const scrollToBottom = () => {
  if (refHistoryWordsContainer.value) {
    nextTick(() => {
      refHistoryWordsContainer.value.scrollTo({
        top: refHistoryWordsContainer.value.scrollHeight,
        behavior: 'smooth',
      });
    });
  }
};

onMounted(() => {
  const { height } = useElementSize(refHistoryWordsContainer);
});

</script>

<template>
  <div class="w-full">
    <afr-button
      class="w-full py-2"
      type="primary"
      :loading="isLoading"
      @click="openModal"
    >
      Проверь себя
    </afr-button>
    <afr-dialog
      v-model="isOpenModal"
      width="500px"
    >
      <template #header>
        Проверь себя
      </template>

      <div class="flex flex-col py-3">
        <div class="flex flex-col">
          <div class="flex justify-end font-bold">
            {{ balls }}
          </div>

          <template v-if="wordActive?.answer_options">
            <template v-for="answerOption in wordActive.answer_options" :key="answerOption.id">
              <afr-word-item :word="answerOption" :answer="wordActive.answer" @click="check"/>
            </template>
          </template>

          <div class="flex justify-center my-4">
            <afr-player-word :word="wordActive?.answer?.word" class="me-3" @click.stop="() => false"/>
          </div>
        </div>

        <div
          ref="refHistoryWordsContainer"
          class="history-words-list"
        >
          <ul>
            <li
              v-for="word in historyWords"
              :class="{ 'error-word': !word.is_true_answer }"
              :title="`${word.word} - ${word.translation} - ${word.transcription}`"
            >
              {{ word.word }}
            </li>
          </ul>
        </div>
      </div>
    </afr-dialog>
  </div>
</template>

<style scoped>
.history-words-list{
  @apply lg:absolute bg-white p-2 rounded overflow-auto lg:ms-[496px] lg:-mt-[64px] lg:w-[200px] h-[200px] lg:h-[360px]  border-t;
  /*margin-left: 496px;
  margin-top: -64px;
  width: 200px;
  height: 360px;*/
}

.history-words-list ul{

}

.history-words-list ul li{
  @apply py-1 cursor-pointer;
}

.error-word{
  @apply text-red-500;
}
</style>
