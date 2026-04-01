<script setup>
import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
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
    <seo-head
      title="Политика по защите персональных данных - ApprendreFr"
      description="Политика по защите персональных данных - ApprendreFr"
      :no-index="true"
    />

    <div class="bg-sky-50">
      <h1 class="text-center mt-8 text-xl font-semibold">Политика по защите персональных данных</h1>
      <afr-privacy-policy-content/>
    </div>

  </default-layout>
</template>

<style scoped>

</style>
