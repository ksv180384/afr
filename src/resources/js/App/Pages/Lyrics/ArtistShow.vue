<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import ArtistAvatar from '@/App/Pages/Lyrics/Components/ArtistAvatar.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  artist: { type: Object, required: true },
});

const compactDescription = computed(() => String(props.artist.description ?? '')
  .replace(/\s+/gu, ' ')
  .trim());

const seoDescription = computed(() => {
  if (!compactDescription.value) {
    return `Песни исполнителя ${props.artist.name}: французские тексты с переводом на русский язык и транскрипцией.`;
  }

  return compactDescription.value.length > 180
    ? `${compactDescription.value.slice(0, 177)}…`
    : compactDescription.value;
});

const canonicalUrl = computed(() => route('lyrics.artist.show', { id: props.artist.id }));

const jsonLd = computed(() => [
  {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    '@id': `${canonicalUrl.value}#artist-songs`,
    name: `${props.artist.name} — песни, тексты и переводы`,
    description: seoDescription.value,
    url: canonicalUrl.value,
    inLanguage: 'ru',
    ...(props.artist.image_url ? { image: props.artist.image_url } : {}),
    mainEntity: {
      '@type': 'ItemList',
      numberOfItems: props.artist.songs.length,
      itemListElement: props.artist.songs.map((song, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: `${song.artist_name} — ${song.title}`,
        url: route('lyrics.show', { id: song.id }),
      })),
    },
  },
  {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    '@id': `${canonicalUrl.value}#breadcrumbs`,
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Главная', item: route('index') },
      { '@type': 'ListItem', position: 2, name: 'Тексты песен', item: route('lyrics') },
      { '@type': 'ListItem', position: 3, name: props.artist.name, item: canonicalUrl.value },
    ],
  },
]);

const songsLabel = (count) => {
  const value = Number(count) || 0;
  const lastTwoDigits = value % 100;
  const lastDigit = value % 10;

  if (lastTwoDigits >= 11 && lastTwoDigits <= 14) return `${value} песен`;
  if (lastDigit === 1) return `${value} песня`;
  if (lastDigit >= 2 && lastDigit <= 4) return `${value} песни`;
  return `${value} песен`;
};
</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <seo-head
      :title="`${artist.name} — песни, тексты и переводы`"
      :description="seoDescription"
      :canonical-url="canonicalUrl"
      :og-image="artist.image_url ?? ''"
      :json-ld="jsonLd"
    />

    <main class="artist-page">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <Link :href="route('lyrics')">Тексты песен</Link>
        <span aria-hidden="true">›</span>
        <span>{{ artist.name }}</span>
      </nav>

      <header class="artist-hero">
        <ArtistAvatar
          :src="artist.image_url"
          :name="artist.name"
          loading="eager"
          class="h-36 w-36 text-4xl sm:h-44 sm:w-44"
        />
        <div class="min-w-0">
          <h1>{{ artist.name }}</h1>
          <p class="artist-count"><span aria-hidden="true">♫</span> {{ songsLabel(artist.songs_count) }}</p>
          <p v-if="artist.description" class="artist-description">{{ artist.description }}</p>
        </div>
      </header>

      <section class="content-section" aria-labelledby="artist-songs-heading">
        <h2 id="artist-songs-heading">Песни исполнителя</h2>
        <div class="song-list">
          <Link
            v-for="song in artist.songs"
            :key="song.id"
            :href="route('lyrics.show', { id: song.id })"
            class="song-link"
          >
            <span class="song-icon" aria-hidden="true">♫</span>
            <span>
              <strong>{{ song.title }}</strong>
              <small v-if="song.artist_name !== artist.name">{{ song.artist_name }}</small>
            </span>
            <span class="song-arrow" aria-hidden="true">›</span>
          </Link>
        </div>
      </section>

      <section
        v-if="artist.lineups.length"
        class="content-section"
        aria-labelledby="lineups-heading"
      >
        <h2 id="lineups-heading">Совместные исполнения</h2>

        <article v-for="lineup in artist.lineups" :key="lineup.id" class="lineup-card">
          <div class="lineup-header">
            <ArtistAvatar
              :src="lineup.image_url"
              :name="lineup.name"
              class="h-24 w-24 text-xl sm:h-28 sm:w-28"
            />
            <div class="min-w-0 flex-1">
              <h3>{{ lineup.name }}</h3>
              <div class="lineup-artists" aria-label="Участники совместного исполнения">
                <template v-for="(member, index) in lineup.artists" :key="member.id">
                  <span v-if="index" aria-hidden="true">+</span>
                  <Link :href="route('lyrics.artist.show', { id: member.id })">
                    {{ member.name }}
                  </Link>
                </template>
              </div>
              <p v-if="lineup.description" class="lineup-description">{{ lineup.description }}</p>
            </div>
          </div>

          <div class="lineup-songs">
            <Link
              v-for="song in lineup.songs"
              :key="song.id"
              :href="route('lyrics.show', { id: song.id })"
            >
              <span aria-hidden="true">♫</span>
              {{ song.title }}
            </Link>
          </div>
        </article>
      </section>
    </main>
  </default-layout>
</template>

<style scoped>
.artist-page {
  @apply min-h-full bg-gradient-to-b from-sky-50 to-white px-4 py-6 sm:px-6;
}

.breadcrumbs {
  @apply mx-auto mb-5 flex w-full max-w-5xl items-center gap-2 text-sm text-slate-500;
}

.breadcrumbs a {
  @apply font-medium text-sky-700 hover:text-sky-900 hover:underline;
}

.artist-hero,
.content-section {
  @apply mx-auto w-full max-w-5xl rounded-xl border border-sky-100 bg-white shadow-sm;
}

.artist-hero {
  @apply flex flex-col items-center gap-5 p-5 text-center sm:flex-row sm:items-start sm:p-7 sm:text-left;
}

.artist-hero h1 {
  @apply break-words text-3xl font-bold leading-tight text-sky-950 sm:text-4xl;
}

.artist-count {
  @apply mt-2 font-medium text-sky-600;
}

.artist-description,
.lineup-description {
  @apply mt-4 whitespace-pre-line text-sm leading-6 text-slate-600 sm:text-base;
}

.content-section {
  @apply mt-5 p-5 sm:p-7;
}

.content-section h2 {
  @apply mb-4 text-xl font-bold text-sky-950 sm:text-2xl;
}

.song-list {
  @apply grid gap-2 sm:grid-cols-2;
}

.song-link {
  @apply flex min-w-0 items-center gap-3 rounded-lg border border-sky-100 bg-sky-50/60 px-4 py-3 text-sky-900 transition hover:border-sky-200 hover:bg-sky-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500;
}

.song-link strong {
  @apply block break-words text-sm;
}

.song-link small {
  @apply mt-1 block break-words text-xs text-slate-500;
}

.song-icon {
  @apply text-sky-500;
}

.song-arrow {
  @apply ml-auto text-xl text-sky-500;
}

.lineup-card {
  @apply mt-4 rounded-xl border border-sky-100 bg-sky-50/40 p-4 sm:p-5;
}

.lineup-header {
  @apply flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:text-left;
}

.lineup-header h3 {
  @apply break-words text-lg font-bold text-sky-950 sm:text-xl;
}

.lineup-artists {
  @apply mt-2 flex flex-wrap justify-center gap-2 text-sm sm:justify-start;
}

.lineup-artists a {
  @apply rounded-full bg-white px-3 py-1 font-semibold text-sky-700 shadow-sm hover:text-sky-900 hover:underline;
}

.lineup-songs {
  @apply mt-4 grid gap-2 border-t border-sky-100 pt-4 sm:grid-cols-2;
}

.lineup-songs a {
  @apply flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-medium text-sky-800 shadow-sm transition hover:text-sky-950 hover:shadow;
}
</style>
