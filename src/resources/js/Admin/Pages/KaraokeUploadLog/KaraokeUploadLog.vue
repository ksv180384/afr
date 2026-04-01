<script setup>
import { Head } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  logs: { type: Array, default: null },
  pagination: { type: Object, default: null },
});
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Логи загрузки караоке"
  >
    <Head>
      <title>Логи загрузки караоке</title>
      <meta name="description" content="Логи загрузки караоке" />
      <meta property="og:title" content="Логи загрузки караоке" />
      <meta property="og:description" content="Логи загрузки караоке" />
    </Head>

    <div class="flex justify-end">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.karaoke-upload-logs'"
      />
    </div>

    <div class="min-w-0">
      <div class="overflow-x-auto">
        <el-table
          :data="logs"
          style="width: 100%;"
          class="min-w-0"
          height="calc(100vh - 110px)"
        >
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="song_title" label="Название песни" width="200" show-overflow-tooltip />
          <el-table-column prop="song_artist" label="Исполнитель" width="180" show-overflow-tooltip />
          <el-table-column prop="file_name" label="Файл" width="220" show-overflow-tooltip />
          <el-table-column prop="file_duration_formatted" label="Длит. файла" width="100">
            <template #default="scope">
              {{ scope.row.file_duration_formatted }}
            </template>
          </el-table-column>
          <el-table-column prop="db_duration_formatted" label="Длит. БД" width="100">
            <template #default="scope">
              {{ scope.row.db_duration_formatted ?? '—' }}
            </template>
          </el-table-column>
          <el-table-column prop="duration_matched" label="Совпадение" width="110">
            <template #default="scope">
              <el-tag :type="scope.row.duration_matched ? 'success' : 'danger'" size="small">
                {{ scope.row.duration_matched ? 'Да' : 'Нет' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="user" label="Пользователь" width="160">
            <template #default="scope">
              {{ scope.row.user?.name ?? 'Гость' }}
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="Дата" fixed="right" width="130">
            <template #default="scope">
              <span :title="scope.row.created_at">{{ scope.row.created }}</span>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </div>

    <div class="flex justify-end">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.karaoke-upload-logs'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
