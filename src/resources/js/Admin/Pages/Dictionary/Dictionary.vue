<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { isObjectEmpty } from '@/Helpers/helper';
import api from '@/Admin/Services/Api';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import DictionaryList from '@/Admin/Pages/Dictionary/Components/DictionaryList.vue';
import DictionarySearchList from '@/Admin/Pages/Dictionary/Components/DictionarySearchList.vue';
import DictionaryFilter from '@/Admin/Pages/Dictionary/Components/DictionaryFilter.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  filter: { type: Object, default: null },
  words: { type: Array, default: null },
  pagination: { type: Object, default: null },
});

const searchItems = ref([]);
const isLoadingSearch = ref(false);

const filterData = async (data) => {
  if(isObjectEmpty(data)){
    searchItems.value = [];
    return;
  }
  isLoadingSearch.value = true;
  try {
    const res = await api.dictionary.filter(data);
    searchItems.value = res.words
  } catch (e) {
    console.error(e)
  } finally {
    isLoadingSearch.value = false;
  }
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Словарь"
  >
    <Head>
      <title>Словарь - Панель администратора</title>
      <meta name="description" content="Тексты песен" />
      <meta property="og:title" content="Тексты песен" />
      <meta property="og:description" content="Тексты песен" />
    </Head>

    <div class="flex px-3 justify-between py-2 sticky top-0 bg-blue-50 shadow">
      <div class="flex flex-row justify-between w-full">

        <div>
          <DictionaryFilter
            :part-of-speeches="filter.part_of_speeches"
            @change="filterData"
          />
        </div>

        <div>
          <Link :href="route('admin.dictionary.create')">
            <el-button plain type="success">
              Добавить слово
            </el-button>
          </Link>
        </div>
      </div>
    </div>

    <template v-if="searchItems.length > 0">
      <DictionarySearchList :words="searchItems" />
    </template>
    <template v-else>
      <DictionaryList
        :words="words"
        :pagination="pagination"
      />
    </template>

  </admin-layout>
</template>

<style scoped>

</style>


