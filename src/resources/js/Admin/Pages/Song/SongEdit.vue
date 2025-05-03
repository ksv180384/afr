<script setup>
import {reactive, ref} from 'vue';
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
const songData = reactive({
  title: props.song.title,
  text_fr: props.song.text_fr,
  text_ru: props.song.text_ru,
  text_transcription: props.song.text_transcription,
  ar_text_fr: props.song.ar_text_fr,
  ar_text_ru: props.song.ar_text_ru,
  ar_text_transcription: props.song.ar_text_transcription,
});

const subtitlesFr = ref(songData.ar_text_fr);
const subtitlesRu = ref(songData.ar_text_ru);
const subtitlesTranscription = ref(songData.ar_text_transcription);

const changeForm = (formData) => {
  console.log(formData);
}
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
      <song-form
        :song="songData"
        :artists="artists"
        :errors="errors"
        @change="changeForm"
      />
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
