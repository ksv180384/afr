<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import ProverbForm from '@/Admin/Pages/Proverb/Components/ProverbForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  proverb: { type: Object, required: true },
  redirect: { type: String, default: null },
  errors: { type: Object, default: {} },
});

const submit = (form) => {
  form.post(route('admin.proverb.update', { id: props.proverb.id, redirect: props.redirect }), {
    onFinish: (res) => {
    },
  });
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Редактирование пословицы"
  >
    <Head>
      <title>Редактировать пословицу - Панель администратора</title>
      <meta name="description" content="Редактировать пословицу" />
      <meta property="og:title" content="Редактировать пословицу" />
      <meta property="og:description" content="Редактировать пословицу" />
    </Head>

    <proverb-form
      :proverb="proverb"
      :errors="errors"
      @submit="submit"
    />
  </admin-layout>
</template>

<style scoped>

</style>
