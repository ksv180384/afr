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
  ...props.song,
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

const parseSubtitleLine = (line) => {
  const timeRegex = /^\[(\d{2}:\d{2}(?:\.\d{1,3})?)\](.*)/;
  const match = line.match(timeRegex);
  if (match) {
    const [_, time, text] = match;
    return { time, text };
  }
  return { time: '00:00', text: line };
};

const changeForm = (formData) => {
  songData.title = formData.title;
  songData.duration = formData.duration;
  const processLines = (text) => {
    if (!text) return [];
    return text.split('\n').map(line => parseSubtitleLine(line));
  };
  const frLines = processLines(formData.text_fr);
  const ruLines = processLines(formData.text_ru);
  const transcriptionLines = processLines(formData.text_transcription);
  const maxLength = Math.max(frLines.length, ruLines.length, transcriptionLines.length);
  const padArray = (arr) => {
    const add = Array(maxLength - arr.length).fill({ time: '00:00', text: '' });
    return [...arr, ...add];
  };
  subtitlesFr.value = padArray(frLines);
  subtitlesRu.value = padArray(ruLines).map((item, key) => {
    item.time = subtitlesFr.value[key].time;
    return item;
  });
  subtitlesTranscription.value = padArray(transcriptionLines).map((item, key) => {
    item.time = subtitlesFr.value[key].time;
    return item;
  });
};

const arTextToStringForm = (arr) => {
  return arr.map(item => {
    const time = item.time || '00:00';
    const text = item.text !== undefined ? item.text : '';
    return `[${time}]${text}`;
  }).join('\n');
};

const changeMakerSubtitles = (subtitlesData) => {
  songData.text_fr = arTextToStringForm(subtitlesData.fr);
  songData.text_ru = arTextToStringForm(subtitlesData.ru);
  songData.text_transcription = arTextToStringForm(subtitlesData.tr);
};

const submit = (form) => {
  form.post(route('admin.song.update', { id: props.song.id }), {
    onFinish: () => {},
    preserveScroll: true,
  });
};
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
        @submit="submit"
      />
    </div>

    <div>
      <afr-maker-subtitles
        :fr="subtitlesFr"
        :ru="subtitlesRu"
        :transcription="subtitlesTranscription"
        @change="changeMakerSubtitles"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
