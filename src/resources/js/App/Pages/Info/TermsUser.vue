<script setup>
import { Head } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrTermsUserContent from '@/App/Components/Info/AfrTermsUserContent.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
});

</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <Head>
      <title>Правила пользовательского соглашения - ApprendreFr</title>
      <meta name="description" content="Правила пользовательского соглашения - ApprendreFr" />
      <meta property="og:title" content="Правила пользовательского соглашения - ApprendreFr" />
      <meta property="og:description" content="Правила пользовательского соглашения - ApprendreFr" />
    </Head>

    <div class="bg-sky-50">
      <h1 class="text-center mt-8 text-xl font-semibold">Правила пользовательского соглашения</h1>
      <afr-terms-user-content/>
    </div>

  </default-layout>
</template>

<style scoped>

</style>
