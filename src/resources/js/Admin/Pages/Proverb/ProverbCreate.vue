<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import ProverbForm from '@/Admin/Pages/Proverb/Components/ProverbForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  redirect: { type: String, default: null },
  errors: { type: Object, default: {} },
});

const submit = (form) => {
  form.post(route('admin.proverb.store', { redirect: props.redirect }), {
    onFinish: (res) => {
    },
  });
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавить пословицу"
  >
    <Head>
      <title>Добавить пословицу - Панель администратора</title>
      <meta name="description" content="Добавить пословицу" />
      <meta property="og:title" content="Добавить пословицу" />
      <meta property="og:description" content="Добавить пословицу" />
    </Head>

    <proverb-form
      :errors="errors"
      @submit="submit"
    />
  </admin-layout>
</template>

<style scoped>

</style>
