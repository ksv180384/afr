<script setup>
import { Icon } from '@iconify/vue';

const props = defineProps({
  word: { type: Object, default: {} }
});
const emits = defineEmits(['showInfo', 'select']);

const showWordInfo = (id) => {
  emits('showInfo', id);
}

const selectWord = (id) => {
  emits('select', id);
}
</script>

<template>
<li :title="word.translation" class="flex justify-between items-center">
  <div class="flex-1" @click="selectWord(word.id)">
    {{ word.word }}
  </div>
  <div @click="showWordInfo(word.id)">
    <Icon icon="ion:eye" width="16px" height="16px" />
  </div>
</li>
</template>

<style scoped>

</style>
