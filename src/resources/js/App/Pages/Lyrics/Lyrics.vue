<script setup>
import { Head, Link } from '@inertiajs/vue3';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  artists: { type: Array, default: [] },
});

</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <Head>
      <title>Тексты, транскрипции и переводы французских песен</title>
      <meta name="description" content="Тексты, транскрипции и переводы французских песен" />
      <meta property="og:title" content="Тексты, транскрипции и переводы французских песен" />
      <meta property="og:description" content="Тексты, транскрипции и переводы французских песен" />
    </Head>

    <div class="lyrics-container">

      <h1 class="font-bold text-2xl text-center py-4">Тексты, транскрипции и переводы французских песен</h1>

      <div class="lyrics-content">
        <template v-for="artist in artists">
          <div class="mb-2">
            <div class="font-bold">{{ artist.name }}</div>
            <div>
              <template v-for="song in artist.songs">
                <div class="ps-4 py-1">
                  <Link :href="route('lyrics.show', { id: song.id })" class="link" :title="song.title">
                    {{ song.title }}
                  </Link>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>
  </default-layout>
</template>

<style scoped>
.lyrics-container{
  @apply bg-sky-50 min-h-full;
}

.lyrics-content{
  @apply px-4;
}
</style>
