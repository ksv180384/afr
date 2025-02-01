<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  word: { type: Object, default: {} },
  answer: { type: Object, default: {} },
});
const emits = defineEmits(['click']);

const isClickError = ref(0);
const wordClassError = computed(() => {
  return isClickError.value ? 'text-red-500 hover:text-red-500' : 'hover:text-green-500';
});

const onClick = () => {
  const isCorrect = props.answer.id === props.word.id;
  if(!isCorrect){
    clickError(props.word.id);
  }
  emits('click', isCorrect);
}

const clickError = (idWord) => {
  isClickError.value = idWord;
  setTimeout(() => {
    isClickError.value = false;
  }, 500);
}
</script>

<template>
  <div
    class="word-item"
    :class="wordClassError"
    :title="word.translation"
    @click="onClick()"
  >
    {{ word.translation }}
  </div>
</template>

<style scoped>
.word-item{
  @apply text-center cursor-pointer py-1 my-1 transition duration-500 text-nowrap truncate px-4;
}
</style>
