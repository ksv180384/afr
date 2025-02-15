<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import LessonForm from '@/Admin/Pages/Lesson/Components/LessonForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  lesson: { type: Object, default: null },
  errors: { type: Object, default: {} },
});

const submit = async (form) => {
  form.post(route('admin.lessons.update', { id: props.lesson.id }), {
    onFinish: (res) => {
      // form.reset('password')
    },
  });
};
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавление урока"
  >
    <Head>
      <title>Редактирование урока - {{ lesson.title }}</title>
      <meta name="description" :content="`Редактирование урока - ${props.lesson.title}`" />
      <meta property="og:title" :content="`Редактирование урока - ${props.lesson.title}`" />
      <meta property="og:description" :content="`Редактирование урока - ${props.lesson.title}`" />
    </Head>

    <div class="flex flex-col">
      <div class="flex justify-end px-4 py-2">
        <Link :href="route('admin.lessons')">
          <el-button plain>
            Назад
          </el-button>
        </Link>
      </div>
      <div class="px-4">

        <LessonForm
          :lesson="lesson"
          :errors="errors"
          @submit="submit"
        />

      </div>
    </div>

  </admin-layout>
</template>

<style scoped>
.link{
  @apply px-3 py-1.5 hover:bg-blue-100;
}
</style>

