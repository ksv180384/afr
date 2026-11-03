<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  ogTitle: { type: String, default: '' },
  ogDescription: { type: String, default: '' },
  ogImage: { type: String, default: '' },
  canonicalUrl: { type: String, default: '' },
  noIndex: { type: Boolean, default: false },
  robots: { type:String, default:null },
  jsonLd: { type: [Object, Array], default: null },
});

const page = usePage();
const currentUrl = computed(() => page.props?.ziggy?.location || (typeof window==='undefined' ? '' : window.location.href));
const resolvedCanonical = computed(() => props.canonicalUrl || currentUrl.value);
const resolvedOgTitle = computed(() => props.ogTitle || props.title);
const resolvedOgDescription = computed(() => props.ogDescription || props.description);

const jsonLdScript = computed(() => {
  if (!props.jsonLd) return null;
  return JSON.stringify(props.jsonLd).replaceAll('<', String.fromCharCode(92)+'u003c');
});
</script>

<template>
  <Head>
    <title>{{ title }}</title>
    <meta head-key="description" v-if="description" name="description" :content="description" />
    <meta v-if="noIndex || robots" head-key="robots" name="robots" :content="robots || 'noindex, follow'" />
    <link head-key="canonical" rel="canonical" :href="resolvedCanonical" />
    <meta head-key="og:url" property="og:url" :content="resolvedCanonical" />
    <meta head-key="og:title" property="og:title" :content="resolvedOgTitle" />
    <meta v-if="resolvedOgDescription" head-key="og:description" property="og:description" :content="resolvedOgDescription" />
    <meta v-if="ogImage" head-key="og:image" property="og:image" :content="ogImage" />
    <meta head-key="twitter:title" name="twitter:title" :content="resolvedOgTitle" />
    <meta v-if="resolvedOgDescription" head-key="twitter:description" name="twitter:description" :content="resolvedOgDescription" />
    <component v-if="jsonLdScript" :is="'script'" head-key="jsonld" type="application/ld+json" >{{ jsonLdScript }}</component>
  </Head>
</template>
