<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import GrammarForm from '@/Admin/Pages/Grammar/Components/GrammarForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  errors: { type: Object, default: {} },
});

const submit = async (form) => {
  form.post(route('admin.grammars.store'), {
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
      <title>Добавление урока</title>
      <meta name="description" content="Добавление урока" />
      <meta property="og:title" content="Добавление урока" />
      <meta property="og:description" content="Добавление урока" />
    </Head>

    <div class="flex flex-col">
      <div class="flex justify-end px-4 py-2">
        <Link :href="route('admin.grammars')">
          <el-button plain>
            Назад
          </el-button>
        </Link>
      </div>
      <div class="px-4">

        <GrammarForm
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
