<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  ogTitle: { type: String, default: '' },
  ogDescription: { type: String, default: '' },
  noIndex: { type: Boolean, default: false },
  jsonLd: { type: [Object, Array], default: null },
});

const resolvedOgTitle = computed(() => props.ogTitle || props.title);
const resolvedOgDescription = computed(() => props.ogDescription || props.description);

const jsonLdScript = computed(() => {
  if (!props.jsonLd) return null;
  return JSON.stringify(props.jsonLd);
});
</script>

<template>
  <Head>
    <title>{{ title }}</title>
    <meta v-if="description" name="description" :content="description" />
    <meta v-if="noIndex" name="robots" content="noindex, follow" />
    <meta property="og:title" :content="resolvedOgTitle" />
    <meta v-if="resolvedOgDescription" property="og:description" :content="resolvedOgDescription" />
    <meta name="twitter:title" :content="resolvedOgTitle" />
    <meta v-if="resolvedOgDescription" name="twitter:description" :content="resolvedOgDescription" />
    <component v-if="jsonLdScript" :is="'script'" type="application/ld+json" v-text="jsonLdScript" />
  </Head>
</template>
