<script setup>
import { Link } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrPostInfoItem from '@/App/Components/Post/AfrPostInfoItem.vue';

defineProps({
  currentUserId: { type: Number, default: null },
  post: { type: Object, default: {} },
});
</script>

<template>
<article class="post-item">
  <header>
    <div class="header-info">
      <div class="header-info-user">
        <img :src="post.user.avatar_link" :alt="post.user.name" />
        <div class="flex flex-col gap-1">
          <h2>
            <Link
              :href="route('post.show', { id: post.id })"
              class="text-sky-800"
            >
              {{ post.title }}
            </Link>
          </h2>
          <div class="flex flex-row gap-4">
            <Link
              :href="route('user.show', { id: post.user.id })"
            >
              {{ post.user.name }}
            </Link>

            <div class="header-info-date" :title="post.created_at">
              {{ post.created }}
            </div>
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
    <div>
      <template v-if="currentUserId === post.user.id">
        <Link :href="route('post.edit', { id: post.id })">
          <afr-button class="flex gap-2" size="small" title="Редактировать">
            <Icon icon="lucide:edit" width="18" height="18" /> Редактировать
          </afr-button>
        </Link>
      </template>
    </div>
  </div>
</article>
</template>

<style scoped>
.post-item{
  @apply bg-sky-50 rounded p-2 shadow;
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
  @apply flex items-center gap-4;
}

.header-info-user img{
  @apply rounded-md w-[52px] h-[68px] object-cover object-center;
}

.header-info-user a{
  @apply font-semibold text-sm;
}

.header-info-date{

}

.post-footer{
  @apply flex justify-between border-t border-blue-200 pt-2 mt-2;
}
</style>
