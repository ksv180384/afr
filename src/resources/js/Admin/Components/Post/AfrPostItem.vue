<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

import AfrPostInfoItem from '@/App/Components/Post/AfrPostInfoItem.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';

const props = defineProps({
  post: { type: Object, default: {} },
  postStatuses: { type: Array, default: [] },
  isUpdatingStatus: { type: Boolean, default: false },
});
const emits = defineEmits(['changeStatus']);

const statusId = ref(props.post.status.id);

const onChangeStatus = () => {
  emits('changeStatus', props.post.id, { status_id: statusId.value })
}
</script>

<template>
<article class="post-item">
  <header>
    <div class="header-info">
      <div class="header-info-user">
        <img :src="post.user.avatar_link" :alt="post.user.name" />
        <div class="flex flex-col gap-1 w-full">
          <div class="flex flex-row justify-between items-center w-full">
            <h2>
              <a
                :href="route('post.show', { id: post.id })"
                class="text-sky-800"
              >
                {{ post.title }}
              </a>
            </h2>
          </div>

          <div class="flex flex-col ">
            <a
              :href="route('user.show', { id: post.user.id })"
            >
              {{ post.user.name }}
            </a>
            <div class="text-xs">
              {{ post.user.rang.title }}
            </div>
          </div>
        </div>

        <div class="header-info-date">
          <div class="text-nowrap" :title="post.updated_at">
            Обновлено: {{ post.updated }}
          </div>
          <div class="text-nowrap" :title="post.created_at">
            Добавлено: {{ post.created }}
          </div>
        </div>
      </div>
    </div>
  </header>

  <div v-html="post.preview" class="post-content"></div>

  <div class="post-footer">
    <div class="flex gap-5 text-sky-800">
      <afr-post-info-item>
        <Icon icon="mdi:show" width="18" height="18" /> {{ post.views_count }}
      </afr-post-info-item>

      <afr-post-info-item>
        <Icon icon="mdi:comments-text-outline" width="18" height="18" /> {{ post.comments_count }}
      </afr-post-info-item>
    </div>
    <div class="inline-block">
      <div class="flex flex-row min-w-[240px]">
        <el-button text :loading="isUpdatingStatus"></el-button>
        <el-select
          v-model="statusId"
          class="flex-1"
          placeholder="Статус"
          :disabled="isUpdatingStatus"
          @change="onChangeStatus"
        >
          <el-option
            v-for="status in postStatuses"
            :key="status.id"
            :label="status.title"
            :value="status.id"
          />
        </el-select>
      </div>
    </div>
  </div>
</article>
</template>

<style scoped>
.post-item{
  @apply bg-sky-50 rounded p-2 shadow-lg;
}

.post-item header{
  @apply mb-4;
}

.post-item header h2{

}

.post-item header h2 a{
  @apply text-2xl;
}

.header-info{
  @apply flex items-center gap-5 text-sm;
}

.header-info-user{
  @apply flex items-center gap-4 flex-1;
}

.header-info-user img{
  @apply rounded-md w-[52px] h-[68px] object-cover object-center;
}

.header-info-user a{
  @apply font-semibold text-sm;
}

.header-info-date{
  @apply flex flex-col gap-2;
}

.post-footer{
  @apply flex justify-between border-t border-blue-200 pt-2 mt-2;
}
</style>
