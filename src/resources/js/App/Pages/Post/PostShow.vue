<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import AfrAddComment from '@/App/Components/Comment/AfrAddComment.vue';
import AfrCommentItem from '@/App/Components/Comment/AfrCommentItem.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, required: true },
  proverb: { type: Object, required: true },
  post: { type: Object, required: true },
  comments: { type: Array, default: [] },
  errors: { type: Object, required: true },
});

const form = useForm({
  comment: '',
  post_id: props.post.id,
});

const postPlainText = computed(() => {
  if (typeof document === 'undefined') {
    return props.post.title;
  }

  const container = document.createElement('div');
  container.innerHTML = props.post.content || '';

  return (container.textContent || '').replace(/\s+/g, ' ').trim();
});

const seoDescription = computed(() => {
  if (!postPlainText.value) {
    return `${props.post.title} - статья автора ${props.post.user.name} на ApprendreFr о французском языке.`;
  }

  return postPlainText.value.length > 180
    ? `${postPlainText.value.slice(0, 177)}...`
    : postPlainText.value;
});

const currentUrl = computed(() => typeof window === 'undefined' ? 'https://apprendrefr.ru' : window.location.href);
const jsonLd = computed(() => [
  {
    '@context': 'https://schema.org',
    '@type': 'Article',
    '@id': `${currentUrl.value}#article`,
    'headline': props.post.title,
    'description': seoDescription.value,
    'author': { '@type': 'Person', 'name': props.post.user.name },
    'datePublished': props.post.created_at_iso || props.post.created_at,
    'dateModified': props.post.updated_at_iso || props.post.updated_at,
    'inLanguage': 'ru',
    'mainEntityOfPage': currentUrl.value,
    'url': currentUrl.value,
  },
  {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    '@id': `${currentUrl.value}#breadcrumbs`,
    'itemListElement': [
      { '@type': 'ListItem', 'position': 1, 'name': 'Главная', 'item': 'https://apprendrefr.ru' },
      { '@type': 'ListItem', 'position': 2, 'name': props.post.title, 'item': currentUrl.value },
    ],
  },
]);

const submitComment = () => {
  form.post(route('post-comment.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset('comment'),
  });
}
</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <seo-head
      :title="post.title"
      :description="seoDescription"
      :json-ld="jsonLd"
    />

    <div class="flex flex-col gap-0.5 min-h-full bg-sky-50">
      <article class="post-item border-b border-sky-800 !rounded-b-none">
        <header>
          <div class="header-info">
            <div class="header-info-user">
              <img :src="post.user.avatar_link" :alt="post.user.name" loading="lazy" />
              <Link
                :href="route('user.show', { id: post.user.id })"
              >
                {{ post.user.name }}
              </Link>
            </div>
            <div class="header-info-date" :title="post.created_at">
              {{ post.created }}
            </div>
          </div>
        </header>

        <h1>
          {{ post.title }}
        </h1>

        <div v-html="post.content" class="post-content"></div>
      </article>

      <div v-if="authUser" class="p-2 bg-sky-50 rounded">
        <afr-add-comment
          v-model="form.comment"
          placeholder="Введите текст комментария"
          @submit="submitComment"
        />

        <div v-if="errors" class="mt-2">
          <afr-input-error-message v-for="error in errors">
            {{ error }}
          </afr-input-error-message>
        </div>
      </div>

      <div class="p-2 bg-sky-50 rounded">
        <template v-if="comments.length">
          <afr-comment-item
            v-for="comment in comments"
            :key="comments.id"
            :user="comment.user"
            :comment="comment"
          />
        </template>
      </div>
    </div>
  </default-layout>
</template>

<style scoped>
.post-item{
  @apply bg-sky-50 rounded p-2;
}

.post-item h1{
  @apply text-2xl lg:text-3xl font-medium mb-2 lg:mb-6;
}

.header-info{
  @apply flex items-center gap-5 text-sm;
}

.header-info-user{
  @apply flex items-center gap-3;
}

.header-info-user img{
  @apply rounded-full w-[36px] h-[36px] object-cover object-center;
}

.header-info-user a{
  @apply font-semibold text-sm;
}
</style>
