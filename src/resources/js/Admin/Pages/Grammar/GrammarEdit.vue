<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import GrammarForm from '@/Admin/Pages/Grammar/Components/GrammarForm.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  grammar: { type: Object, default: null },
  errors: { type: Object, default: {} },
});

const submit = async (form) => {
  form.post(route('admin.grammars.update', { id: props.grammar.id }), {
    onFinish: (res) => {
      // form.reset('password')
    },
  });
};
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Добавление грамматики"
  >
    <Head>
      <title>Редактирование - {{ grammar.title }}</title>
      <meta name="description" :content="`Редактирование - ${props.grammar.title}`" />
      <meta property="og:title" :content="`Редактирование - ${props.grammar.title}`" />
      <meta property="og:description" :content="`Редактирование - ${props.grammar.title}`" />
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
          :grammar="grammar"
          :errors="errors"
          @submit="submit"
        />

      </div>
    </div>

  </admin-layout>
</template>

<style scoped>

</style>

