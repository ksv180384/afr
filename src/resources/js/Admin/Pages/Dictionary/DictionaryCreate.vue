<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import DictionaryForm from '@/Admin/Pages/Dictionary/Components/DictionaryForm.vue';
import DictionarySearchList from '@/Admin/Pages/Dictionary/Components/DictionarySearchList.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  partOfSpeeches: { type: Object, default: null },
  errors: { type: Object, default: {} },
});

const searchItems = ref([]);

const onSubmitForm = (form) => {

  form.post(route('admin.dictionary.store'), {
    onFinish: (res) => {
    },
  });
}

const onChangeSearchItems = (items) => {
  searchItems.value = items;
}
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

    <DictionaryForm
      :part-of-speeches="partOfSpeeches"
      :errors="errors"
      @changeSearchItems="onChangeSearchItems"
      @submit="onSubmitForm"
    />

    <DictionarySearchList :words="searchItems"/>
  </admin-layout>
</template>

<style scoped>

</style>



