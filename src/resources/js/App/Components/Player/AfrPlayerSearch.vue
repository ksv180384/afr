<script setup>
import { ref } from 'vue';
import api from '@/App/Services/Api';
import { vOnClickOutside } from '@vueuse/components';

import AfrInput from '@/App/Components/Form/AfrInput.vue';

const emits = defineEmits(['loadSong']);

const searchText = ref('');
const songs = ref([]);
const isLoading = ref(false);

const clickOutSide = () => {
  songs.value = [];
}

const onInputSearch = async () => {

  if(searchText.value.length < 3){
    songs.value = [];
    return;
  }

  isLoading.value = true;
  try {
    const res = await api.songText.search({ search: searchText.value });

    songs.value = res.songs;
  } catch (e) {
    console.error(e);
  } finally {
    isLoading.value = false;
  }
}

const loadSong = async (songId) => {
  try {
    const res = await api.songText.getById(songId);
    emits('loadSong', res.song);
    songs.value = [];
    searchText.value = `${res.song.artist_name} - ${res.song.title}`;
  } catch (e) {
    console.error(e);
  } finally {

  }
}
</script>

<template>
<div
  v-on-click-outside="clickOutSide"
>
  <afr-input
    v-model="searchText"
    placeholder="Поиск подходящей песни..."
    @input="onInputSearch"
    @focusin="onInputSearch"
  />

  <div
    v-if="songs.length"
    class="search-result"
  >
    <ul>
      <template v-for="song in songs">
        <li @click="loadSong(song.id)">
          <div class="font-text-xs">
            {{ song.artist_name }}
          </div>
          <div class="font-bold text-sm">
            {{ song.title }}
          </div>
        </li>
      </template>
    </ul>
  </div>
</div>
</template>

<style scoped>
.search-result{
  @apply relative mx-1;
}

.search-result ul{
  @apply absolute text-center bg-white rounded-b text-gray-500 shadow-lg overflow-hidden w-full;
  /*width: calc(100% + 1rem);
  transform: translateX(-0.5rem);*/
  z-index: 11;
}

.search-result ul li{
  @apply py-1 cursor-pointer transition duration-300 hover:bg-blue-50 text-start px-4;
}
</style>
