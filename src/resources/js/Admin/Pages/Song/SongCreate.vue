<script setup>
import { ref,  reactive } from 'vue';
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import SongForm from '@/Admin/Pages/Song/Components/SongForm.vue';
import AfrMakerSubtitles from "@/Components/MakerSubtitles/AfrMakerSubtitles.vue";

const props = defineProps({
  authUser: { type: Object, default: null },
  artists: { type: Array, default: [] },
  errors: { type: Object, default: null },
});

const songData = reactive({
  title: '',
  duration: null,
  text_fr: '',
  text_ru: '',
  text_transcription: '',
  ar_text_fr: [],
  ar_text_ru: [],
  ar_text_transcription: [],
});
const subtitlesFr = ref(songData.ar_text_fr);
const subtitlesRu = ref(songData.ar_text_ru);
const subtitlesTranscription = ref(songData.ar_text_transcription);

// const parseSubtitleLine = (line) => {
//   // Проверяем, есть ли временная метка в формате [00:00]
//   const timeRegex = /^\[(\d{2}:\d{2}(?:\.\d{1,3})?)\](.*)/;
//   const match = line.match(timeRegex);
//
//   if (match) {
//     // Если есть метка, возвращаем объект с time и text
//     const [_, time, text] = match;
//     return { time, text: text.trim() };
//   } else {
//     // Если нет метки, считаем время 00:00
//     return { time: "00:00", text: line.trim() };
//   }
// };

const parseSubtitleLine = (line) => {
  // Проверяем, есть ли временная метка в формате [00:00]
  const timeRegex = /^\[(\d{2}:\d{2}(?:\.\d{1,3})?)\](.*)/;
  const match = line.match(timeRegex);

  if (match) {
    // Если есть метка, возвращаем объект с time и text
    const [_, time, text] = match;
    return { time, text: text }; // Не используем trim() здесь!
  } else {
    return { time: "00:00", text: line }; // Не используем trim() здесь!
  }
};

const changeForm = (formData) => {
  // Функция для обработки текста в массив объектов
  const processLines = (text) => {
    if (!text) return [];
    return text.split('\n').map((line) => { // Убираем filter()
      return parseSubtitleLine(line);
    });
  };

  // Получаем массивы для каждого языка
  const frLines = processLines(formData.text_fr);
  const ruLines = processLines(formData.text_ru);
  const transcriptionLines = processLines(formData.text_transcription);

  // Находим максимальную длину
  const maxLength = Math.max(
    frLines.length,
    ruLines.length,
    transcriptionLines.length
  );

  // Функция для дополнения массива пустыми объектами
  const padArray = (arr) => {
    const addElementsArray = Array(maxLength - arr.length).fill({ time: '00:00', text: '' });
    return [...arr, ...addElementsArray];
  };

  // Присваиваем выровненные массивы
  subtitlesFr.value = padArray(frLines);
  subtitlesRu.value = padArray(ruLines).map((item, key) => {
    item.time = subtitlesFr.value[key].time;
    return item;
  });
  subtitlesTranscription.value = padArray(transcriptionLines).map((item, key) => {
    item.time = subtitlesFr.value[key].time;
    return item;
  });
}

const arTextToStringForm = (arr) => {
  return arr.map(item => {
    const time = item.time || '00:00';
    // Явно сохраняем пустые строки и пробелы
    const text = item.text !== undefined ? item.text : '';
    return `[${time}]${text}`;
  }).join('\n');
}

// const arTextToStringForm = (arr) => {
//   const grouped = arr.reduce((acc, item) => {
//     if (!acc[item.text]) {
//       acc[item.text || ' '] = [];
//     }
//     acc[item.text || ' '].push(item.time);
//     return acc;
//   }, {});
//
//   // Форматируем результат
//   return Object.entries(grouped)
//     .map(([text, times]) => {
//       const timeBlocks = times.map(time => `[${time}]`).join('');
//       return `${timeBlocks}${text}`;
//     })
//     .join('\n');
// }

const changeMakerSubtitles = (subtitresData) => {
  songData.text_fr = arTextToStringForm(subtitresData.fr);
  songData.text_ru = arTextToStringForm(subtitresData.ru);
  songData.text_transcription = arTextToStringForm(subtitresData.tr);
};

const submit = (form) => {
  form.post(route('admin.song.store'), {
    onFinish: () => {},
    preserveScroll: true,
  });
};
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавить песню"
  >
    <Head>
      <title>Добавить песню - Панель администратора</title>
      <meta name="description" content="Добавить песню" />
      <meta property="og:title" content="Добавить песню" />
      <meta property="og:description" content="Добавить песню" />
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


