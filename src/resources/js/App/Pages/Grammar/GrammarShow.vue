<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrMenuLeft from '@/App/Components/Menu/MenuLeft/AfrMenuLeft.vue';
import AfrMenuLeftItem from '@/App/Components/Menu/MenuLeft/AfrMenuLeftItem.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  menu: { type: Array, default: [] },
  grammarContent: { type: Object, default: {} },
});

const subMenu = computed(() => props.menu.map(item => ({
  ...item,
  href: route('grammar.show', { id: item.id }),
  is_active: route().current('grammar.show', { id: item.id })
})));

const titlePage = `Грамматика Французского языка - ${props.grammarContent.title}`;
</script>

<template>
  <mini-layout
    :auth-user="authUser"
    :sub-menu="subMenu"

  >
    <Head>
      <title>{{ titlePage }}</title>
      <meta name="description" :content="grammarContent.description" />
      <meta property="og:title" :content="titlePage" />
      <meta property="og:description" :content="grammarContent.description" />
    </Head>

    <div class="grammar-container">
      <div class="grammar-menu">
        <afr-menu-left>
          <afr-menu-left-item
            v-for="menuItem in menu"
            :href="route('grammar.show', { id: menuItem.id })"
            :title="menuItem.title"
            :is-active="route().current('grammar.show', { id: menuItem.id })"
          >
            {{ menuItem.title }}
          </afr-menu-left-item>
        </afr-menu-left>
      </div>
      <div class="grammar-content">
        <h1 class="text-center font-bold text-2xl py-4">{{ grammarContent.title }}</h1>
        <div v-html="grammarContent.content"></div>
      </div>
    </div>
  </mini-layout>
</template>

<style scoped>
.grammar-container{
  @apply flex flex-row;
}

.grammar-menu{
  @apply min-w-[300px] hidden lg:block;
}

.grammar-content{
  @apply flex-1 px-4 py-2 border-l border-l-sky-200;
}

:deep(.grammar-content p){
  @apply py-2;
}

:deep(.grammar-content .prim_gramm){
  float: left;
  padding-left: 100px;
  width: 400px;
}

:deep(.grammar-content .prim_gramm2){
  margin: 0 auto;
  width: 550px;
  line-height: 30px !important;
}

:deep(.grammar-content .zagolovki2){
  @apply font-bold;
}
</style>

