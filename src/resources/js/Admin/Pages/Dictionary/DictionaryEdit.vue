<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import DictionaryForm from '@/Admin/Pages/Dictionary/Components/DictionaryForm.vue';
import DictionarySearchList from '@/Admin/Pages/Dictionary/Components/DictionarySearchList.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  word: { type: Object, default: null },
  partOfSpeeches: { type: Object, default: null },
  errors: { type: Object, default: {} },
});

const searchItems = ref([]);

const onSubmitForm = (form) => {

  form.post(route('admin.dictionary.update', { id: props.word.id }), {
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
    :title="`Редактировать слово ${word.word}`"
  >
    <Head>
      <title>Редактировать слово {{ word.word }} - Панель администратора</title>
      <meta name="description" :content="`${word.word} редактировать слово`" />
      <meta property="og:title" :content="`${word.word} редактировать слово`" />
      <meta property="og:description" :content="`${word.word} редактировать слово`" />
    </Head>

    <DictionaryForm
      :word="word"
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
