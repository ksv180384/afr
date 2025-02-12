<script setup>
import { Link } from '@inertiajs/vue3';

import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  words:  { type: Array, required: true },
  pagination:  { type: Object, required: true },
});
</script>

<template>
  <div class="flex justify-end px-3 pt-2">
    <pagination
      :current-page="pagination.current_page"
      :last-page="pagination.last_page"
      :per-page="pagination.per_page"
      :total="pagination.total"
      :route-name="'admin.dictionary'"
    />
  </div>

  <div class="flex-1 flex flex-col px-2">
    <template v-for="word in words" :key="word.id">
      <Link
        :href="route('admin.dictionary.edit', { id: word.id })"
        :title="`${word.word} [${word.transcription}] - ${word.translation}`"
        class="flex flex-row gap-4 items-center px-2 py-2 border-b border-gray-300 hover:bg-blue-100"
      >
        <span class="font-bold">{{ word.word }}</span>
        <span class="text-orange-600">{{ word.transcription }}</span>
        <span class="text-green-600">{{ word.translation }}</span>
      </Link>
    </template>
  </div>

  <div class="flex justify-end px-3 py-2">
    <pagination
      :current-page="pagination.current_page"
      :last-page="pagination.last_page"
      :per-page="pagination.per_page"
      :total="pagination.total"
      :route-name="'admin.dictionary'"
    />
  </div>
</template>

<style scoped>

</style>
