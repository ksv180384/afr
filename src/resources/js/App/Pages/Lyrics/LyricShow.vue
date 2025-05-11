<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrCommentItem from '@/App/Components/Comment/AfrCommentItem.vue';
import AfrAddComment from '@/App/Components/Comment/AfrAddComment.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  song: { type: Object, default: {} },
  comments: { type: Array, default: null },
  errors: { type: Object, required: true },
});

const activeColumn = ref('fr');
const form = useForm({
  comment: '',
  song_id: props.song.id,
});

const changeColumn = (val) => {
  activeColumn.value = val;
}

const submitComment = () => {

  form.post(route('song-comment.store'), {
    // onFinish: (res) => {},
    preserveScroll: true,
    onSuccess: () => form.reset('comment'),
  });
}
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <Head>
      <title>{{ song.title }} - {{ song.artist_name }} перевод, транскрипция</title>
      <meta name="description" :content="`${song.title} - ${song.artist_name} - текст, перевод, транскрипция на русском`" />
      <meta property="og:title" :content="`${song.title} - ${song.artist_name}`" />
      <meta property="og:description" :content="`${song.title} - ${song.artist_name} - текст, перевод, транскрипция на русском`" />
    </Head>

    <div class="lyric-show-container">

      <h1 class="font-bold text-2xl text-center py-4">{{ song.artist_name }} - {{ song.title }}</h1>

      <div class="lyric-show-content">

        <div class="activate-column">
          <div class="btn-activate" :class="{ 'active': activeColumn === 'fr' }" @click="changeColumn('fr')">FR</div>
          <div class="btn-activate" :class="{ 'active': activeColumn === 'ru' }" @click="changeColumn('ru')">RU</div>
          <div class="btn-activate" :class="{ 'active': activeColumn === 'tr' }" @click="changeColumn('tr')">TR</div>
        </div>

        <table class="song-table">
          <tbody>
          <template v-for="key in Object.keys(song.text_fr)">
            <tr>
              <td :class="{ 'active': activeColumn === 'fr' }">{{ song.text_fr[key] === '' ? '&nbsp;' : song.text_fr[key] }}</td>
              <td :class="{ 'active': activeColumn === 'ru' }">{{ song.text_ru[key] === '' ? '&nbsp;' : song.text_ru[key] }}</td>
              <td :class="{ 'active': activeColumn === 'tr' }">{{ song.text_transcription[key] === '' ? '&nbsp;' : song.text_transcription[key] }}</td>
            </tr>
          </template>
          </tbody>
        </table>

      </div>

      <div class="px-8">
        <div v-if="authUser" class="p-2 bg-sky-50 rounded">
          <afr-add-comment
            v-model="form.comment"
            placeholder="Введите текст комментария"
            @submit="submitComment"
          />

          <div v-if="errors" class="mt-2">
            <afr-input-error-message v-for="error in errors">
              {{ error }}
            </afr-input-error-message>
          </div>
        </div>

        <div class="p-2 bg-sky-50 rounded">
          <template v-if="comments.length">
            <afr-comment-item
              v-for="comment in comments"
              :key="comments.id"
              :user="comment.user"
              :comment="comment"
            />
          </template>
        </div>

      </div>
    </div>
  </mini-layout>
</template>

<style scoped>
.lyric-show-container{
  @apply bg-sky-50 min-h-full;
}

.lyric-show-content{
  @apply px-4;
}

.song-table{
  @apply w-full;
}

.song-table tr{
  @apply hover:bg-white;
}
/* @apply hidden lg:block; */
.song-table tr td{
  @apply px-2 py-1 align-top hidden lg:table-cell;
}

.song-table tr td.active{
  @apply table-cell;
}

.activate-column{
  @apply fixed flex flex-col bg-white rounded shadow-md border border-white overflow-hidden
         top-1/2 -translate-y-1/2 right-6;
}

.btn-activate{
  @apply w-[40px] h-[40px] flex justify-center items-center font-medium cursor-pointer lg:hidden duration-300 ease-in-out;
}

.btn-activate.active{
  @apply bg-sky-700 text-white;
}

</style>
