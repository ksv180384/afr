<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import ArtistAvatar from '@/App/Pages/Lyrics/Components/ArtistAvatar.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  artists: { type: Array, default: [] },
});

const searchQuery = ref('');
const selectedLetter = ref('ALL');

const normalizeText = (value) => String(value ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLocaleUpperCase('ru-RU');

const getArtistLetter = (name) => {
  const firstCharacter = Array.from(normalizeText(name).trim())[0] ?? '#';
  return /^[A-ZА-ЯЁ]$/u.test(firstCharacter) ? firstCharacter : '#';
};

const alphabet = computed(() => Array.from(new Set(
  props.artists.map((artist) => getArtistLetter(artist.name)),
)).sort((left, right) => {
  if (left === '#') return 1;
  if (right === '#') return -1;
  return left.localeCompare(right, 'ru');
}));

const filteredArtists = computed(() => {
  const query = normalizeText(searchQuery.value.trim());

  return props.artists.filter((artist) => {
    const matchesLetter = selectedLetter.value === 'ALL'
      || getArtistLetter(artist.name) === selectedLetter.value;
    const matchesQuery = query === '' || normalizeText(artist.name).includes(query);

    return matchesLetter && matchesQuery;
  });
});

const songsLabel = (count) => {
  const value = Number(count) || 0;
  const lastTwoDigits = value % 100;
  const lastDigit = value % 10;

  if (lastTwoDigits >= 11 && lastTwoDigits <= 14) return `${value} песен`;
  if (lastDigit === 1) return `${value} песня`;
  if (lastDigit >= 2 && lastDigit <= 4) return `${value} песни`;
  return `${value} песен`;
};

const resetFilters = () => {
  searchQuery.value = '';
  selectedLetter.value = 'ALL';
};
</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <seo-head
      title="Тексты, транскрипции и переводы французских песен"
      description="Тексты французских песен с построчным переводом на русский язык и транскрипцией. Слушайте и подпевайте на французском."
    />

    <div class="lyrics-container">
      <div class="catalog-shell">
        <header class="catalog-header">
          <h1>Тексты, транскрипции и переводы французских песен</h1>
          <p>
            Выберите исполнителя, чтобы посмотреть доступные тексты песен,
            построчные переводы и транскрипции.
          </p>
        </header>

        <form class="artist-search" role="search" @submit.prevent>
          <label for="artist-search" class="sr-only">Поиск исполнителя</label>
          <input
            id="artist-search"
            v-model="searchQuery"
            type="search"
            inputmode="search"
            autocomplete="off"
            placeholder="Поиск исполнителя…"
          >
          <svg aria-hidden="true" viewBox="0 0 24 24">
            <path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
          </svg>
        </form>

        <nav class="alphabet-filter" aria-label="Фильтр исполнителей по алфавиту">
          <button
            type="button"
            :class="{ active: selectedLetter === 'ALL' }"
            :aria-pressed="selectedLetter === 'ALL'"
            @click="selectedLetter = 'ALL'"
          >
            Все
          </button>
          <button
            v-for="letter in alphabet"
            :key="letter"
            type="button"
            :class="{ active: selectedLetter === letter }"
            :aria-pressed="selectedLetter === letter"
            @click="selectedLetter = letter"
          >
            {{ letter }}
          </button>
        </nav>

        <div v-if="filteredArtists.length" class="artist-grid">
          <Link
            v-for="(artist, index) in filteredArtists"
            :key="artist.id"
            :href="route('lyrics.artist.show', { id: artist.id })"
            class="artist-card group"
            :aria-label="`Открыть песни исполнителя ${artist.name}`"
          >
            <ArtistAvatar
              :src="artist.image_url"
              :name="artist.name"
              :loading="index < 3 ? 'eager' : 'lazy'"
              class="h-28 w-28 text-2xl"
            />
            <span class="artist-name">{{ artist.name }}</span>
            <span class="song-count">
              <span aria-hidden="true">♫</span>
              {{ songsLabel(artist.songs_count) }}
            </span>
            <span class="artist-action">
              Смотреть песни
              <span aria-hidden="true">›</span>
            </span>
          </Link>
        </div>

        <div v-else class="empty-results">
          <p>Исполнители не найдены.</p>
          <button type="button" @click="resetFilters">Сбросить фильтры</button>
        </div>
      </div>
    </div>
  </default-layout>
</template>

<style scoped>
.lyrics-container {
  @apply min-h-full bg-gradient-to-b from-sky-50 to-white px-4 py-6 sm:px-6;
}

.catalog-shell {
  @apply mx-auto w-full max-w-5xl;
}

.catalog-header {
  @apply mb-6 text-center;
}

.catalog-header h1 {
  @apply text-2xl font-bold leading-tight text-sky-950 sm:text-3xl;
}

.catalog-header p {
  @apply mx-auto mt-3 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base;
}

.artist-search {
  @apply relative mb-4;
}

.artist-search input {
  @apply h-12 w-full rounded-lg border border-sky-100 bg-white px-4 pr-12 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100;
}

.artist-search svg {
  @apply pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 fill-none stroke-sky-800 stroke-2;
}

.alphabet-filter {
  @apply mb-7 flex flex-wrap items-center gap-1.5;
}

.alphabet-filter button {
  @apply min-w-8 rounded-md px-2.5 py-1.5 text-sm font-semibold text-sky-800 transition hover:bg-sky-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500;
}

.alphabet-filter button.active {
  @apply bg-sky-700 text-white shadow-sm hover:bg-sky-700;
}

.artist-grid {
  @apply grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3;
}

.artist-card {
  @apply flex min-h-64 flex-col items-center rounded-lg border border-sky-100 bg-white px-5 py-6 text-center shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2;
}

.artist-name {
  @apply mt-4 text-base font-bold leading-snug text-sky-900;
}

.song-count {
  @apply mt-2 flex items-center gap-1.5 text-sm font-medium text-sky-600;
}

.artist-action {
  @apply mt-auto inline-flex items-center gap-2 rounded-full bg-sky-50 px-4 py-2 text-xs font-semibold text-sky-800 transition group-hover:bg-sky-100;
}

.empty-results {
  @apply rounded-lg border border-dashed border-sky-200 bg-white px-6 py-12 text-center text-slate-600;
}

.empty-results button {
  @apply mt-3 rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800;
}
</style>
