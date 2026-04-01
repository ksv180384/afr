<script setup>
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
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
    <seo-head
      :title="titlePage"
      description="Уроки французского языка. Каждый урок французского языка для начинающих представлен в виде темы для общения."
    />

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
