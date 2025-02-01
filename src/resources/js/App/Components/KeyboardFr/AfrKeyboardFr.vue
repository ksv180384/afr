<script setup>

import AfrButton from '@/App/Components/Form/AfrButton.vue';

const props = defineProps({
  value: { type: String,  },
});
const emits = defineEmits(['change']);
const letters = 'ëôêûâîùçèàé';

const addLetter = (letter) => {
  emits('change', letter);
}
</script>

<template>
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
</template>

<style scoped>
.letters-block{
  @apply flex flex-row justify-around gap-3;
}

.btn-letter{
  @apply flex justify-center items-center w-[32px] h-[32px] font-bold;
}
</style>
