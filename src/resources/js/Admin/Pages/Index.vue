<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Общая информация"
  >
    <Head title="Панель администратора" />

    <div class="flex flex-col gap-1">
      test
      <el-button>test</el-button>
    </div>
  </admin-layout>
</template>

<style scoped>

</style>

