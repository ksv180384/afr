<script setup>
import { Head } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrPrivacyPolicyContent from '@/App/Components/Info/AfrPrivacyPolicyContent.vue';

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
      <title>Политика по защите персональных данных - ApprendreFr</title>
      <meta name="description" content="Политика по защите персональных данных - ApprendreFr" />
      <meta property="og:title" content="Политика по защите персональных данных - ApprendreFr" />
      <meta property="og:description" content="Политика по защите персональных данных - ApprendreFr" />
    </Head>

    <div class="bg-sky-50">
      <h1 class="text-center mt-8 text-xl font-semibold">Политика по защите персональных данных</h1>
      <afr-privacy-policy-content/>
    </div>

  </default-layout>
</template>

<style scoped>

</style>
