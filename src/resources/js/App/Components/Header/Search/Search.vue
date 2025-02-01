<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import useClickOutside from '@/Composables/useClickOutside.js';
import { vOnClickOutside } from '@vueuse/components';
import api from '@/App/Services/Api';

import { Icon } from '@iconify/vue';
import WordItem from '@/App/Components/Header/Search/WordItem.vue';
import SongItem from '@/App/Components/Header/Search/SongItem.vue';
import AfrWordDialog from '@/App/Components/Words/AfrWordDialog.vue';

const searchTypes = [
  { id: 'word', title: 'Слово' },
  { id: 'song', title: 'Песня' },
];
const refSearchTypesList = ref(null);
const refBtnTypesList = ref(null);
const searchText = ref(router.page.props?.searchText || '');
const hints = ref([]);
const isShowListBlock = ref(false);
const activeSearchType = ref(router.page?.component === 'Song/SearchSong' ? 1 : 0);
const isShow = ref(false);
const isSearchLoading = ref(false);
const isShowDropdown = computed(() => isShow.value && searchText.value.length >= 2/* && props.hints.length*/);
const activeSearchTypeTitle = computed(() => searchTypes[activeSearchType.value].title);
const activeSearchTypeId = computed(() => searchTypes[activeSearchType.value].id);
const wordInfo = ref({});
const isShowWordInfoModal = ref(false);

let searchTimeout = null;

useClickOutside(
  refSearchTypesList,
  () => {
    isShowListBlock.value = false
  },
  refBtnTypesList
);

const textLang = computed(() => {
  const fr_pattern = /[a-zëôêûâîùçèàé]+/i;
  const ru_pattern = /[а-яё]+/i;
  const fr = fr_pattern.test(searchText.value);
  const ru = ru_pattern.test(searchText.value);
  if(searchText.value.length >= 1){
    if(fr && ru){
      return 'non';
    }else{
      if(fr){
        return 'Fr';
      }else{
        return 'Ru';
      }
    }
  }
  return null;
});

const changeSearchType = (index) => {
  activeSearchType.value = index;
  isShowListBlock.value = false;
  loadHintsSearch();
}

const toggleListBlock = () => {
  isShowListBlock.value = !isShowListBlock.value;
}

const clickOutSide = (e) => {
  if(e.target.id === 'inputSearch'){
    return;
  }
  isShow.value = false;
}

const focusInputSearch = () => {
  isShow.value = true;
}

const loadHintsSearch = () => {
  clearTimeout(searchTimeout);
  if(!searchText.value){
    isSearchLoading.value = false;
    return;
  }
  isSearchLoading.value = true;
  searchTimeout = setTimeout(() => {
    isShow.value = true;
    loadSearchHints({ text: searchText.value, type: activeSearchTypeId.value, lang: textLang.value });
  }, 500);
}

const loadSearchHints = async (search) => {
  if(search.text.length < 2){
    hints.value = [];
    isSearchLoading.value = false;
    return;
  }
  try{
    const res = await api.search.searchHints({ text: search.text, type: search.type, lang: search.lang });
    hints.value = res.search.map(item => {
      return item
    });
  } catch (e) {
    console.error(e);
  } finally {
    isSearchLoading.value = false;
  }
}

const onSelectWord = (id) => {
  const word = hints.value.find(item => item.id === id);
  searchText.value = word.word;
  isShow.value = false;
}

const onShowInfoWord = (id) => {
  wordInfo.value = hints.value.find(item => item.id === id);
  isShowWordInfoModal.value = true;
}

const onSelectSong = (selectSong) => {
  searchText.value = `${selectSong.artist_name} - ${selectSong.title}`;
  // loadHintsSearch();
  isShow.value = false;
}

const goToSearch = () => {
  if(searchTypes[activeSearchType.value].id === 'word'){
    router.visit(route('word.search', { text: searchText.value }))
  }
  else if(searchTypes[activeSearchType.value].id === 'song'){
    router.visit(route('song.search', { text: searchText.value }))
  }
}
</script>

<template>
  <div class="search">
    <div class="search-container">
      <div class="search-select-container">
        <div
          ref="refBtnTypesList"
          class="select-active"
          @click="toggleListBlock"
        >
          {{ activeSearchTypeTitle }}
        </div>
        <ul
          ref="refSearchTypesList"
          v-show="isShowListBlock"
        >
          <li
            v-for="(type, index) in searchTypes"
            :key="type.id"
            @click="changeSearchType(index)"
          >
            {{ type.title }}
          </li>
        </ul>
      </div>

      <div class="input-block">
        <input
          v-model="searchText"
          id="inputSearch"
          type="text"
          placeholder="Поиск..."
          autocomplete="off"
          @keydown="loadHintsSearch"
          @keydown.enter="goToSearch"
          @focus.stop="focusInputSearch"
        />
        <div class="lang">
          {{ textLang }}
        </div>
      </div>
      <div class="icon-block" @click="goToSearch">
        <template v-if="isSearchLoading">
          <Icon icon="svg-spinners:6-dots-rotate" width="23" height="23" />
        </template>
        <template v-else>
          <Icon v-if="!searchText" icon="tabler:search" width="23" height="23"/>
          <Icon v-else icon="lsicon:arrow-right-outline" width="23px" height="23px" />
        </template>
      </div>
    </div>

    <ul
      v-show="isShowDropdown"
      class="search-result-list"
      v-on-click-outside="clickOutSide"
    >
      <template v-if="!hints.length && !isSearchLoading">
        <li class="text-center">
          Ничего не найдено
        </li>
      </template>
      <template v-else-if="activeSearchTypeId === 'word'">
        <WordItem
          v-for="word in hints"
          :key="word.id"
          :word="word"
          @select="onSelectWord"
          @showInfo="onShowInfoWord"
        />
      </template>
      <template v-else>
        <SongItem
          v-for="song in hints"
          :key="song.id"
          :song="song"
          @select="onSelectSong"
        />
      </template>
    </ul>

    <afr-word-dialog
      v-model="isShowWordInfoModal"
      :word="wordInfo.word"
      :transcription="wordInfo.transcription"
      :translation="wordInfo.translation"
      :example="wordInfo.example"
    />
  </div>
</template>

<style scoped>
.search{
  @apply relative;
  font-size: 14px;
}

.search-container{
  @apply flex flex-row items-center rounded overflow-hidden;
}

.search .select-active{
  @apply bg-blue-300 text-gray-50 w-20 py-1 text-center cursor-pointer;
}

.search-select-container ul{
  @apply absolute text-center w-20 bg-white rounded text-gray-500 shadow-md overflow-hidden;
}

.search-select-container ul li{
  @apply py-1 cursor-pointer transition duration-300 hover:bg-blue-50;
}

.input-block{
  @apply flex flex-row items-center;
}

.input-block input{
  @apply w-36 outline-none;
  padding: 5px 2rem 5px 6px;
}

.input-block .lang{
  @apply -ml-8 px-2 text-red-600;
  width: 30px;
}

.icon-block{
  @apply text-gray-50 bg-gray-600 px-3 py-1 cursor-pointer;
}

.search-result-list{
  @apply mt-0.5 absolute min-w-full bg-white rounded text-gray-500 shadow-md overflow-hidden;
}

.search-result-list li{
  @apply py-1.5 px-3 whitespace-nowrap cursor-pointer transition duration-300 hover:bg-sky-100;
}
/*
.search{
    display: flex;
    flex-direction: row;
    color: #373737;
    border-radius: 2px;
    background-color: #f4f8fa;
    font-size: 14px;
    box-shadow: 0 1px 2px 0 rgba(34,36,38,.15);
}
.search>.label{
    flex: 1 0 60px;
    text-align: center;
    line-height: 28px;
}
.search>.select{
    flex: 1 0 60px;
    text-align: center;
    position: relative;
}

.search>.select>span{
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100%;
    color: #fff;
    font-size: 14px;
    background-color: #8db3ce;
    cursor: pointer;
    transition: all 0.3s ease 0s;
}

.search>.select>span:hover{
    background-color: #70cc74;
}

.search>.select>ul{
    color: #fff;
    padding: 0;
    margin: 0;
    display: none;
    position: absolute;
    text-align: center;
    background-color: #a5d1f1;
    width: 60px;
    box-shadow: 0 1px 2px 0 rgba(34,36,38,.15);
    border-radius: 0 0 2px 2px;
    left: 0;
    z-index: 0;
}

.search>.select>ul.show{
    display: initial;
}

.search>.select>ul>li{
    list-style: none;
    cursor: pointer;
    padding: 6px 0;
    transition: all 0.3s ease 0s;
}

.search>.select>ul>li:hover{
    background-color: #70cc74;
    color: #fff;
}

.search>.input-search{
    position: relative;
    flex: 100%;
}

.search-lang{
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    width: 28px;
    justify-content: center;
    align-items: center;
    color: red;
    background-color: #f4f8fa;
    display: none;
}

.show-lang.search-lang{
    display: flex;
}

.search-lang>span{
    display: none;
}

.show-ru .search-lang-ru{
    display: initial;
}

.show-fr .search-lang-fr{
    display: initial;
}

.show-non .search-lang-non{
    display: initial;
}

.search>.input-search>input{
    width: 100%;
    padding: 6px 8px;
    font-size: 14px;
    border: none;
    outline: none;
}

.search>.btn-block{
    flex: 1 0 30px;

}

.search>.btn-block>button{
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100%;
    color: #ececec;
    background-color: #4F5257;
    border: 0;
    font-size: 16px;
    border-radius: 0 2px 2px 0;
    cursor: pointer;
}

.search>.btn-block>button:hover{
    color: #fff;
    background-color: #000;
}

.search-result-list{
    position: absolute;
    background-color: #fff;
    width: 100%;
    box-shadow: 0 1px 2px 0 rgba(34,36,38,.15);
    border-radius: 0 0 4px 4px;
    display: none;
}

.search-result-list.show-search-result{
    display: block;
}

.search-result-list>ul{
    max-height: calc(100vh - 100px);
    overflow: auto;
}

.search-result-list>ul>li{
    padding: 8px 6px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.3s ease 0s;
}

.search-result-list>ul>li>span.search-artist-name{
    font-weight: bold;
    display: block;
}

.search-result-list>ul>li:hover{
    color: #176093;
    background-color: #e6f0f6;
}

@media (max-width: 870px) {
    .search{
        margin-left: 6px;
    }
}
*/
</style>
