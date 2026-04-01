<script setup>
import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
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
    <seo-head
      title="Правила пользовательского соглашения - ApprendreFr"
      description="Правила пользовательского соглашения - ApprendreFr"
      :no-index="true"
    />

    <div class="bg-sky-50">
      <h1 class="text-center mt-8 text-xl font-semibold">Правила пользовательского соглашения</h1>
      <afr-terms-user-content/>
    </div>

  </default-layout>
</template>

<style scoped>

</style>
