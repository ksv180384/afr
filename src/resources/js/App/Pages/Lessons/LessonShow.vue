<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrMenuLeft from '@/App/Components/Menu/MenuLeft/AfrMenuLeft.vue';
import AfrMenuLeftItem from '@/App/Components/Menu/MenuLeft/AfrMenuLeftItem.vue';
import AfrPlayerWord from '@/App/Components/AfrPlayerWord.vue';
import AfrTestRuToFr from '@/App/Pages/Lessons/Components/AfrTestRuToFr.vue';
import AfrTestFrToRu from '@/App/Pages/Lessons/Components/AfrTestFrToRu.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  menu: { type: Array, default: [] },
  lessonContent: { type: Object, default: {} },
});

const words = ref([]);
const contentHtml = ref('');
const subMenu = computed(() => props.menu.map(item => ({
  ...item,
  href: route('lesson.show', { id: item.id }),
  is_active: route().current('lesson.show', { id: item.id })
})));

const titlePage = `Тема урока ${props.lessonContent.title}`;

onMounted(() => {
  const contentHtmlContainer = document.createElement('div');
  contentHtmlContainer.innerHTML = props.lessonContent.content;

  const listWords = contentHtmlContainer.querySelectorAll('.spis_sl li');
  words.value = Array.from(listWords).filter(item => !!item.innerText.trim()).map(li => {
    const strong = li.querySelector('strong');
    const span = li.querySelector('.Pervod');

    return {
      fr: strong.textContent.trim(),
      ru: span.textContent.trim(),
      tr: li.textContent.split('=>')[1].split('-')[0].trim(),
    };
  });

  // Удаляем ul из contentHtmlContainer
  contentHtml.value = contentHtmlContainer.innerHTML.replace(contentHtmlContainer.querySelector('.spis_sl').outerHTML, '');
});
</script>

<template>
  <mini-layout
    :auth-user="authUser"
    :sub-menu="subMenu"
  >
    <Head>
      <title>{{ titlePage }}</title>
      <meta name="description" :content="lessonContent.description" />
      <meta property="og:title" :content="titlePage" />
      <meta property="og:description" :content="lessonContent.description" />
    </Head>

    <div class="lesson-container">
      <div class="lesson-menu">
        <afr-menu-left>
          <afr-menu-left-item
            v-for="menuItem in subMenu"
            :href="route('lesson.show', { id: menuItem.id })"
            :title="menuItem.title"
            :is-active="route().current('lesson.show', { id: menuItem.id })"
          >
            {{ menuItem.title }}
          </afr-menu-left-item>
        </afr-menu-left>
      </div>
      <div class="lesson-content">
        <h1 class="text-center font-bold text-2xl py-4">{{ lessonContent.title }}</h1>

        <div class="lesson-words-list">
          <ul>
            <li v-for="word in words">
              <afr-player-word :word="word.fr" class="me-3" @click.stop="() => false"/>
              <strong class="ms-3 me-6">{{ word.fr }}</strong>  <span>{{ word.tr }}</span> <span class="Pervod ms-6">{{ word.ru }}</span>
            </li>
            <li></li>
          </ul>
        </div>
        <div v-html="contentHtml"></div>

        <div class="flex justify-around py-5 gap-3 flex-col lg:flex-row">
          <afr-test-ru-to-fr :words="words"/>
          <afr-test-fr-to-ru :words="words"/>
        </div>
      </div>
    </div>
  </mini-layout>
</template>

<style scoped>
.lesson-container{
  @apply flex flex-row;
}

.lesson-menu{
  @apply min-w-[300px] hidden lg:block;
}

.lesson-content{
  @apply flex-1 px-4 py-2 border-l border-l-sky-200;
}

.lesson-words-list{

}

.lesson-words-list ul{

}

.lesson-words-list ul li{
  @apply flex flex-wrap py-2 hover:bg-blue-100 items-center px-3;
}

.lesson-words-list ul li span,
.lesson-words-list ul li strong{
  @apply flex items-center;
}
</style>
