<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import SongForm from '@/Admin/Pages/Song/Components/SongForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  artists: { type: Array, default: [] },
  errors: { type: Object, default: null },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавить песню"
  >
    <Head>
      <title>Добавить песню - Панель администратора</title>
      <meta name="description" content="Добавить песню" />
      <meta property="og:title" content="Добавить песню" />
      <meta property="og:description" content="Добавить песню" />
    </Head>

    <div>
      <song-form :artists="artists" :errors="errors"/>
    </div>

  </admin-layout>
</template>

<style scoped>

</style>


