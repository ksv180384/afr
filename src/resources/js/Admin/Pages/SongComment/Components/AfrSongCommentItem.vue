<script setup>
import { computed } from 'vue';

const props = defineProps({
  comment: { type: Object, default: null },
  isUpdating: { type: Boolean, default: false },
});
const emits = defineEmits(['toggleShow']);

const btnToggleShowText = computed(() => {
  return props.comment.is_hidden ? 'Показать' : 'Скрыть';
});
const btnToggleShowType = computed(() => {
  return props.comment.is_hidden ? 'success' : 'danger';
});

const onToggleShow = () => {
  emits('toggleShow', props.comment.id);
}
</script>

<template>
  <div class="bg-white rounded">
    <div class="flex flex-row px-2 pt-2">
      <div class="font-bold flex-1">
        {{ comment.song.artist_name }} - {{ comment.song.title }}
      </div>
      <div>
        {{ comment.user.name }}
      </div>
    </div>
    <div class="p-2" v-html="comment.comment"></div>
    <div class="flex flex-row items-center bg-gray-50 px-2 py-1">
      <div class="text-xs flex-1" :title="comment.created_at">
        {{ comment.created }}
      </div>
      <div>
        <el-button
          :type="btnToggleShowType"
          size="small" plain
          :loading="isUpdating"
          @click="onToggleShow"
        >
          {{ btnToggleShowText }}
        </el-button>
      </div>
    </div>
  </div>
</template>

<style scoped>

</style>
