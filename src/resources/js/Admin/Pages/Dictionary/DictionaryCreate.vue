<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавить слово"
  >
    <Head>
      <title>Добавить слово - Панель администратора</title>
      <meta name="description" content="Добавить слово" />
      <meta property="og:title" content="Добавить слово" />
      <meta property="og:description" content="Добавить слово" />
    </Head>



  </admin-layout>
</template>

<style scoped>

</style>



