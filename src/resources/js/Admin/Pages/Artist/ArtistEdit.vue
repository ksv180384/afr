<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import AfrUploadImage from '@/App/Components/Form/AfrUploadImage.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  artist: { type: Object, required: true },
  lineups: { type: Array, default: () => [] },
});

const artistImage = ref(null);
const artistForm = useForm({
  description: props.artist.description ?? '',
  image: null,
  remove_image: false,
});

const lineupForms = reactive(Object.fromEntries(props.lineups.map((lineup) => [lineup.id, useForm({
  description: lineup.description ?? '',
  image: null,
  remove_image: false,
})])));

const setArtistImage = (image) => {
  artistImage.value = image;
  artistForm.image = image;
  artistForm.remove_image = false;
};

const removeArtistImage = () => {
  artistImage.value = null;
  artistForm.image = null;
  artistForm.remove_image = Boolean(props.artist.image_url);
};

const saveArtist = () => {
  artistForm.post(route('admin.artists.update', { id: props.artist.id }), {
    forceFormData: true,
    preserveScroll: true,
  });
};

const setLineupImage = (lineupId, image) => {
  lineupForms[lineupId].image = image;
  lineupForms[lineupId].remove_image = false;
};

const removeLineupImage = (lineup) => {
  lineupForms[lineup.id].image = null;
  lineupForms[lineup.id].remove_image = Boolean(lineup.image_url);
};

const saveLineup = (lineup) => {
  lineupForms[lineup.id].post(route('admin.artist-lineups.update', { id: lineup.id }), {
    forceFormData: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <admin-layout :auth-user="authUser" :title="'Исполнитель: ' + artist.name">
    <Head>
      <title>Редактирование исполнителя — {{ artist.name }}</title>
    </Head>

    <div class="p-4">
      <div class="mb-3 flex justify-end">
        <Link :href="route('admin.artists')">
          <el-button plain>К списку</el-button>
        </Link>
      </div>

      <section class="rounded border border-blue-200 bg-white p-4">
        <h2 class="mb-4 text-lg font-semibold">{{ artist.name }}</h2>
        <form class="flex flex-col gap-4 sm:flex-row" @submit.prevent="saveArtist">
          <afr-upload-image
            :model-value="artist.image_url"
            class="h-[312px] w-[312px] shrink-0"
            :loading="artistForm.processing"
            @change="setArtistImage"
            @remove="removeArtistImage"
          />

          <div class="flex min-w-0 flex-1 flex-col">
            <el-form-item label="Описание" label-position="top" :error="artistForm.errors.description">
              <el-input v-model="artistForm.description" :rows="10" type="textarea" maxlength="10000" show-word-limit />
            </el-form-item>
            <p v-if="artistForm.errors.image" class="mb-2 text-sm text-red-600">{{ artistForm.errors.image }}</p>
            <div class="mt-auto">
              <el-button native-type="submit" type="success" :loading="artistForm.processing">Сохранить исполнителя</el-button>
            </div>
          </div>
        </form>
      </section>

      <section class="mt-5">
        <h2 class="mb-3 text-lg font-semibold">Совместные исполнения</h2>
        <p v-if="!lineups.length" class="text-slate-500">У этого исполнителя пока нет песен с другими исполнителями.</p>

        <article v-for="lineup in lineups" :key="lineup.id" class="mb-4 rounded border border-blue-200 bg-white p-4">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="font-semibold">Состав:</span>
            <template v-for="(member, index) in lineup.artists" :key="member.id">
              <span v-if="index" class="text-slate-400">+</span>
              <Link :href="route('admin.artists.edit', { id: member.id })" class="flex items-center gap-1 rounded bg-blue-100 px-2 py-1 hover:bg-blue-200">
                <img
                  v-if="member.image_url"
                  :src="member.image_url"
                  :alt="member.name"
                  class="h-5 w-5 rounded object-cover"
                >
                {{ member.name }}
              </Link>
            </template>
          </div>

          <form class="flex flex-col gap-4 sm:flex-row" @submit.prevent="saveLineup(lineup)">
            <afr-upload-image
              :model-value="lineup.image_url"
              class="h-[312px] w-[312px] shrink-0"
              :loading="lineupForms[lineup.id].processing"
              @change="(image) => setLineupImage(lineup.id, image)"
              @remove="removeLineupImage(lineup)"
            />

            <div class="flex min-w-0 flex-1 flex-col">
              <el-form-item label="Описание совместного исполнения" label-position="top" :error="lineupForms[lineup.id].errors.description">
                <el-input v-model="lineupForms[lineup.id].description" :rows="6" type="textarea" maxlength="10000" show-word-limit />
              </el-form-item>
              <p v-if="lineupForms[lineup.id].errors.image" class="mb-2 text-sm text-red-600">{{ lineupForms[lineup.id].errors.image }}</p>

              <div class="mb-3">
                <div class="mb-1 font-medium">Песни этого состава</div>
                <ul class="list-inside list-disc text-sm text-slate-600">
                  <li v-for="song in lineup.songs" :key="song.id">
                    <Link :href="route('admin.song.edit', { id: song.id })" class="hover:underline">
                      {{ song.artist_name }} — {{ song.title }}
                    </Link>
                  </li>
                </ul>
              </div>

              <div class="mt-auto">
                <el-button native-type="submit" type="success" :loading="lineupForms[lineup.id].processing">Сохранить состав</el-button>
              </div>
            </div>
          </form>
        </article>
      </section>
    </div>
  </admin-layout>
</template>
