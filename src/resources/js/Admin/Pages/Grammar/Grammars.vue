<script setup>
import { Head, Link } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  grammars: { type: Array, default: null },
});
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Грамматика"
  >
    <Head>
      <title>Грамматика</title>
      <meta name="description" content="Грамматика" />
      <meta property="og:title" content="Грамматика" />
      <meta property="og:description" content="Грамматика" />
    </Head>

    <div class="flex flex-col">
      <div class="flex justify-end px-4 py-2">
        <Link :href="route('admin.grammars.create')">
          <el-button type="success" plain>
            Добавить
          </el-button>
        </Link>
      </div>
      <div class="flex flex-col">
        <template v-for="grammar in grammars">
          <Link class="link" :href="route('admin.grammars.edit', { 'id': grammar.id })">
            {{ grammar.title }}
          </Link>
        </template>
      </div>
    </div>

  </admin-layout>
</template>

<style scoped>
.link{
  @apply px-3 py-1.5 hover:bg-blue-100;
}
</style>
