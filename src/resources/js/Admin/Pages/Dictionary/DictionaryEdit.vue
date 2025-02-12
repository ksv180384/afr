<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  word: { type: Object, default: null },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    :title="`Редактировать слово ${word.word}`"
  >
    <Head>
      <title>Редактировать слово {{ word.word }} - Панель администратора</title>
      <meta name="description" :content="`${word.word} редактировать слово`" />
      <meta property="og:title" :content="`${word.word} редактировать слово`" />
      <meta property="og:description" :content="`${word.word} редактировать слово`" />
    </Head>

    {{ word }}

  </admin-layout>
</template>

<style scoped>

</style>
