<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import SongForm from '@/Admin/Pages/Song/Components/SongForm.vue';
import AfrMakerSubtitles from '@/Components/MakerSubtitles/AfrMakerSubtitles.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  artists: { type: Array, default: [] },
  song: { type: Object, required: true },
  errors: { type: Object, default: null },
});

const subtitlesFr = ref(props.song.ar_text_fr);
const subtitlesRu = ref(props.song.ar_text_ru);
const subtitlesTranscription = ref(props.song.ar_text_transcription);

console.log(subtitlesFr.value)
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    :title="`Редактировать песню ${song.artist.name} - ${song.title}`"
  >
    <Head>
      <title>Редактировать песню {{ song.title }} - Панель администратора</title>
      <meta name="description" content="Редактировать песню" />
      <meta property="og:title" content="Редактировать песню" />
      <meta property="og:description" content="Редактировать песню" />
    </Head>

    <div>
      <song-form :song="song" :artists="artists" :errors="errors"/>
    </div>

    <div>
      <afr-maker-subtitles
        :fr="subtitlesFr"
        :ru="subtitlesRu"
        :transcription="subtitlesTranscription"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
