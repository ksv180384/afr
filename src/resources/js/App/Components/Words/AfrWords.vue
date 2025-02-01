<script setup>
import { ref, computed } from 'vue';

import AfrPlayerWord from '@/App/Components/AfrPlayerWord.vue';
import AfrWordDialog from '@/App/Components/Words/AfrWordDialog.vue';

const props = defineProps({
  words: { type: Array, default: [] },
});

const activeWordId = ref(0);
const activeWord = computed(() => props.words.find(item => item.id === activeWordId.value));
const isShowModal = ref(false);

const showInfo = (id) => {
  isShowModal.value = !isShowModal.value;
  activeWordId.value = id;
}
</script>

<template>
  <div class="afr-words-component">
    <ul>
      <li
        v-for="word in words"
        :key="word.id"
        class="afr-words-item"
        @click="showInfo(word.id)"
      >
        <afr-player-word :word="word?.word" class="me-3" @click.stop="() => false"/>
        <div :title="word.transcription">{{ word.word }} - <span class="Pervod">{{ word.translation }}</span></div>
      </li>
    </ul>

    <afr-word-dialog
      v-model="isShowModal"
      :word="activeWord?.word"
      :transcription="activeWord?.transcription"
      :translation="activeWord?.translation"
      :example="activeWord?.example"
    />
  </div>
</template>

<style scoped>
.afr-words-component{
  @apply text-sm -mx-2;
}

.afr-words ul{

}

li.afr-words-item{
  @apply flex items-center cursor-pointer py-1.5 px-2 hover:bg-sky-100;
}
</style>
