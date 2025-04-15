<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  user: { type: Object, required: true },
  comment: { type: Object, required: true },
});
</script>

<template>
  <div class="afr-post-comment-item">
    <div class="flex items-center gap-2 mb-2">
      <img :src="user.avatar_link" class="w-[42px] h-[42px] object-cover rounded-full" :alt="user.name">
      <div class="font-semibold">
        <Link :href="route('user.show', { id: user.id })">
          {{ user.name }}
        </Link>
        <div class="text-xs font-normal text-gray-500">
          {{ comment.created }}
        </div>
      </div>
    </div>
    <div v-html="comment.comment"></div>
  </div>
</template>

<style scoped>
.afr-post-comment-item{
  @apply flex flex-col py-1 border-b border-blue-100 pb-3 mb-3;
}

.afr-post-comment-item:last-child{
  @apply border-b-0 pb-0 mb-0;
}
</style>
