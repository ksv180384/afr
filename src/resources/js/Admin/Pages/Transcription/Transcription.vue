<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import api from '@/Admin/Services/Api';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

const isLoadingTrain = ref(false);
const isLoadingTranscribe = ref(false);
const text = ref('');

const trainModel = async () => {
  try {
    isLoadingTrain.value = true;

    const res = await api.transcription.train();

  }
  catch (e) {
    console.error(e);
  }
  finally {
    isLoadingTrain.value = false;
  }
}

const transcribe = async () => {
  try {
    isLoadingTranscribe.value = true;

    const res = await api.transcription.transcribe({ text: text.value });

  }
  catch (e) {
    console.error(e);
  }
  finally {
    isLoadingTranscribe.value = false;
  }
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Генерация транскрипции"
  >
    <Head>
      <title>Панель администратора</title>
      <meta name="description" content="Панель администратора" />
      <meta property="og:title" content="Панель администратора" />
      <meta property="og:description" content="Панель администратора" />
    </Head>

    <div class="px-2">
      <div class="mt-2">
        <el-input
          v-model="text"
          placeholder="Введите текст на Fr"
          :rows="4"
          type="textarea"
        />
      </div>
      <div class="mt-4">
        <el-button @click="transcribe">Получить транскрипцию на ру</el-button>
      </div>
    </div>

    <div>
      <div>
        <el-button
          :loading="isLoadingTrain"
          @click="trainModel"
        >
          Обучить модель
        </el-button>
      </div>
      <div>

      </div>
    </div>
  </admin-layout>
</template>

<style scoped>

</style>

