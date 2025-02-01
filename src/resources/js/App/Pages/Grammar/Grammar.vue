<script setup>
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrMenuFullPage from '@/App/Components/Menu/MenuFullPage/AfrMenuFullPage.vue';
import AfrMenuFullPageItem from '@/App/Components/Menu/MenuFullPage/AfrMenuFullPageItem.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  menu: { type: Array, default: [] },
});

const titlePage = `Грамматика Французского языка`;
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>{{ titlePage }}</title>
      <meta name="description" content="Грамматика Французского языка" />
      <meta property="og:title" :content="titlePage" />
      <meta property="og:description" content="Грамматика Французского языка" />
    </Head>

    <div class="grammar-container">
      <div class="grammar-content">
        <h1 class="text-center font-bold text-2xl py-4">{{ titlePage }}</h1>

        <afr-menu-full-page>
          <afr-menu-full-page-item
            v-for="menuItem in menu" :key="menuItem.id"
            :href="route('grammar.show', { id: menuItem.id })"
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
.grammar-container{
  @apply flex flex-row;
}

.grammar-content{
  @apply flex-1 px-4 py-2 border-l border-l-sky-200;
}
/*
.grammar-content>ul{

}

.grammar-content>ul>li{
  @apply text-center;
}

.grammar-content>ul>li>a{
  @apply py-2 inline-block border-b border-solid border-sky-200 transition-transform duration-700;
}

.grammar-content>ul>li>a:hover{
  @apply border-dashed border-sky-900 text-sky-800;
}
 */
</style>

