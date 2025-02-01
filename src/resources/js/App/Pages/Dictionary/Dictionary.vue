<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';
import AfrWordDialog from "@/App/Components/Words/AfrWordDialog.vue";
import AfrPlayerWord from "@/App/Components/AfrPlayerWord.vue";

const props = defineProps({
  authUser: { type: Object, default: null },
  partOfSpeechList: { type: Array, default: [] },
  dictionaryWords: { type: Array, default: [] },
  lang: { type: String, default: null },
  pagination: { type: Object, default: {} },
  query: { type: Object, default: {} },
});

const title = ref(props.lang === 'ru' ? 'Русско-французский словарь' : 'Французско-русский словарь');
const isShowWordModal = ref(false);
const activeWord = ref(null);

const showModalWord = (word) => {
  activeWord.value = word;
  isShowWordModal.value = true;
}
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>{{ title }}</title>
      <meta name="description" :content="`${title} - перевод, транскрипция, примеры`" />
      <meta property="og:title" :content="title" />
      <meta property="og:description" :content="`${title} - перевод, транскрипция, примеры`" />
    </Head>

    <div class="dictionary-container">
      <div class="dictionary-content">
        <div class="dictionary-header-container">
          <div class="dictionary-header-block">
            <h1>{{ title }}</h1>
          </div>
          <div class="dictionary-search-block">

          </div>
        </div>

        <div class="part-of-speech-list">
          <ul>
            <li
              title="Все"
              :class="!query.parts_of_speech ? 'active' : ''"
            >
              <Link :href="route('dictionary', { ...query, parts_of_speech: null, page: null })">Все</Link>
            </li>
            <li
              v-for="partOfSpeech in partOfSpeechList"
              :key="partOfSpeech.id"
              :title="partOfSpeech.title"
              :class="query.parts_of_speech === partOfSpeech.id.toString() ? 'active' : ''"
            >
              <Link :href="route('dictionary', { ...query, parts_of_speech: partOfSpeech.id, page: null })">{{ partOfSpeech.title }}</Link>
            </li>
          </ul>
        </div>
        <div class="dictionary-toolbar-container">
          <div class="change-lang-wrapper">
            <Link
              :href="route('dictionary', { ...query, lang: null, page: null })"
              class="link-change-lang"
              :class="query.lang === 'fr' || !query.lang ? 'active' : ''"
            >
              fr
            </Link>
            <Link
              :href="route('dictionary', { ...query, lang: 'ru', page: null })"
              class="link-change-lang"
              :class="query.lang === 'ru' ? 'active' : ''"
            >
              ru
            </Link>
          </div>
          <div class="dictionary-pagination-container">
            <pagination
              :current-page="pagination.current_page"
              :last-page="pagination.last_page"
              :per-page="pagination.per_page"
              :total="pagination.total"
              :route-name="'dictionary'"
            />
          </div>
        </div>

        <div class="dictionary-words-container">
          <div
            v-for="wordItem in dictionaryWords"
            :key="wordItem.id"
            class="dictionary-word-item"
            :title="`${wordItem.translation} - ${wordItem.transcription}`"
          >
            <div
              class="text-base flex flex-row items-center"
              :title="`${wordItem.word} - ${wordItem.translation}`"
            >
              <afr-player-word
                :word="wordItem.word"
                class="me-3"
                :lang="query.lang === 'ru' ?	'ru-RU' : 'fr-FR'"
              />
              <span class="link cursor-pointer" @click="showModalWord(wordItem)">
                {{ wordItem.word }}
              </span>
            </div>
            <div class="text-xs flex flex-row mt-1 items-center gap-2">
              <Link :href="route('dictionary.show', { id: wordItem.id })" class="link">
                <Icon icon="tabler:external-link" width="20" height="20" />
              </Link>
              {{ wordItem.part_of_speech_name }}
            </div>
          </div>
        </div>

        <div v-if="dictionaryWords.length > 30" class="flex justify-end mt-4">

          <div class="dictionary-pagination-container">
            <pagination
              :current-page="pagination.current_page"
              :last-page="pagination.last_page"
              :per-page="pagination.per_page"
              :total="pagination.total"
              :route-name="'dictionary'"
            />
          </div>
        </div>
      </div>
    </div>

    <afr-word-dialog
      v-model="isShowWordModal"
      :word="activeWord?.word"
      :transcription="activeWord?.transcription"
      :translation="activeWord?.translation"
      :example="activeWord?.example"
      :first-lang="query.lang"
    />
  </mini-layout>
</template>

<style scoped>
.dictionary-container{
  @apply bg-sky-50 min-h-full mb-2;
}

.dictionary-content{
  @apply px-4;
}

.dictionary-header-container{
  @apply flex items-center;
}

.dictionary-header-block{
  @apply flex-1;
}

.dictionary-header-block h1{
  @apply font-bold py-4;
}

.dictionary-search-block{

}

.part-of-speech-list{

}

.part-of-speech-list ul{
  @apply flex flex-wrap gap-1;
}

.part-of-speech-list ul li{
  @apply min-w-[60px] flex flex-1 text-center rounded border border-blue-200 bg-blue-200 text-sm
         transition-colors ease-in-out duration-300 cursor-pointer hover:bg-blue-100 hover:text-blue-800
         hover:border-blue-400;
}

.part-of-speech-list ul li a{
  @apply py-1 px-2 inline-block truncate w-full;
}

.part-of-speech-list ul li.active{
  @apply text-white bg-blue-400;
}

.dictionary-toolbar-container{
  @apply flex justify-between mt-2 lg:flex-row flex-col;
}

.change-lang-wrapper{
  @apply flex gap-1;
}

.change-lang-wrapper a{
  @apply flex-1 text-center;
}

.link-change-lang{
  @apply uppercase px-2 rounded border border-blue-300 bg-white;
}

.link-change-lang.active{
  @apply text-white bg-blue-400;
}

.dictionary-pagination-container{
  @apply flex justify-end mt-2 lg:mt-0;
}

.dictionary-words-container{
  @apply flex flex-wrap justify-center py-4 gap-6;
}

.dictionary-word-item{
  @apply w-[200px] px-2;
}
</style>
