<script setup>
import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import { openCookiePreferences } from '@/App/Services/cookiePreferences.js';

defineProps({
  title: String,
  document: String,
  revision: String,
  content: String,
  authUser: Object,
  words: Array,
  proverb: Object,
});
</script>

<template>
  <DefaultLayout :auth-user="authUser" :words="words" :proverb="proverb">
    <SeoHead :title="title + ' — apprendrefr.ru'" :description="title + ' сайта apprendrefr.ru'" :no-index="true" />
    <article class="legal-document">
      <header>
        <h1>{{ title }}</h1>
        <p>Редакция от {{ revision }}</p>
      </header>
      <div class="legal-text" v-html="content" />
      <button v-if="document === 'privacy'" type="button" class="link mt-4" @click="openCookiePreferences">
        Настройки cookie
      </button>
    </article>
  </DefaultLayout>
</template>

<style scoped>
.legal-document {
  @apply bg-white text-slate-700 px-5 py-6 sm:px-10 sm:py-9;
}
.legal-document > * { max-width: 68ch; margin-inline: auto; }
header { @apply border-b border-sky-100 pb-5 mb-5; }
h1 { @apply text-2xl font-bold leading-tight text-sky-950 sm:text-3xl; }
header p { @apply mt-2 text-sm text-slate-500; }
.legal-text { @apply leading-relaxed; overflow-wrap: anywhere; }
.legal-text :deep(h2) { @apply mt-6 mb-2 text-lg font-semibold text-sky-950; scroll-margin-top: 5rem; }
.legal-text :deep(p + p), .legal-text :deep(li + li) { @apply mt-2; }
.legal-text :deep(ul) { @apply my-2 list-disc ps-5; }
.legal-text :deep(a) { @apply text-sky-800 underline underline-offset-2; }
.legal-document > button { display: block; }
</style>
