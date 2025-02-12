<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  words:  { type: Array, required: true },
});
</script>

<template>
  <div class="flex-1 flex flex-col px-2">
    <template v-for="word in words" :key="word.id">
      <div class="flex flex-col px-2 py-3 gap-2 border-b border-gray-300 hover:bg-blue-100">
        <div class="flex w-full">
          <span class="font-bold flex-1 text-center">{{ word.word }}</span>
          <span class="text-orange-600 flex-1 text-center">{{ word.transcription }}</span>
          <span class="text-green-600 flex-1 text-center">{{ word.translation }}</span>
        </div>
        <div v-html="word.example"></div>
      </div>
    </template>
  </div>
</template>

<style scoped>

</style>

