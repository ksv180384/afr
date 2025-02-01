<script setup>
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrMenuFullPageItem from '@/App/Components/Menu/MenuFullPage/AfrMenuFullPageItem.vue';
import AfrMenuFullPage from '@/App/Components/Menu/MenuFullPage/AfrMenuFullPage.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  menu: { type: Array, default: [] },
});

const titlePage = `Уроки французского языка`;
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>{{ titlePage }}</title>
      <meta name="description" content="Уроки французского языка. Каждый урок французского языка для начинающих представлен в виде темы для общения." />
      <meta property="og:title" :content="titlePage" />
      <meta property="og:description" content="Уроки французского языка. Каждый урок французского языка для начинающих представлен в виде темы для общения." />
    </Head>

    <div class="grammar-container">
      <div class="grammar-content">
        <h1 class="text-center font-bold text-2xl py-4">{{ titlePage }}</h1>

        <afr-menu-full-page>
          <afr-menu-full-page-item
            v-for="menuItem in menu" :key="menuItem.id"
            :href="route('lesson.show', { id: menuItem.id })"
            :title="menuItem.title"
          >
            {{ menuItem.title }}
          </afr-menu-full-page-item>
        </afr-menu-full-page>
      </div>
    </div>
  </mini-layout>
</template>

<style scoped>

</style>
