<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  comment: { type: Object, required: true },
  isLoadedToggleShow: { type: Boolean, required: false },
});
const emits = defineEmits(['toggleShow']);

const btnToggleShowText = computed(() => {
  return props.comment.is_show ? 'Скрыть' : 'Показать';
});
const btnToggleShowType = computed(() => {
  return props.comment.is_show ? 'danger' : 'success';
});

const onToggleShow = () => {
  emits('toggleShow', props.comment.id);
}
</script>

<template>
  <div class="afr-post-comment-item" :class="{ 'bg-red-100': !props.comment.is_show }">
    <div class="flex items-center gap-2 mb-2">
      <div class="flex items-center gap-2 flex-1">
        <img :src="comment.user.avatar_link" class="w-[32px] h-[32px] object-cover rounded-full" :alt="comment.user.name">
        <div class="font-semibold">
          <a :href="route('user.show', { id: comment.user.id })">
            {{ comment.user.name }}
          </a>
          <div class="text-sm font-normal">
            {{ comment.created }}
          </div>
        </div>
      </div>
      <div>
        {{ comment.post.title }}
      </div>
    </div>
    <div v-html="comment.comment"></div>
    <div class="mt-3 pt-3 border-t border-gray-200">
      <el-button
        size="small"
        :loading="isLoadedToggleShow"
        plain
        :type="btnToggleShowType"
        @click="onToggleShow"
      >
        {{ btnToggleShowText }}
      </el-button>
    </div>
  </div>
</template>

<style scoped>
.afr-post-comment-item{
  @apply flex flex-col border-b border-sky-800 px-3 py-3;
}

.afr-post-comment-item:last-child{
  @apply border-b-0 mb-0;
}
</style>
