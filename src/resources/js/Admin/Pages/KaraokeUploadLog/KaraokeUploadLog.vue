<script setup>
import { ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { Head, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Hide, View } from '@element-plus/icons-vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  logs: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const fileDialogVisible = ref(false);
const selectedLog = ref(null);
const excludeAdmin = ref(Boolean(props.filters.exclude_admin));
const isMobile = useMediaQuery('(max-width: 640px)');

const applyFilters = () => {
  router.get(
    route('admin.karaoke-upload-logs'),
    {
      exclude_admin: excludeAdmin.value ? 1 : undefined,
      page: 1,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const showFile = (log) => {
  selectedLog.value = log;
  fileDialogVisible.value = true;
};

const deleteFile = async (log) => {
  try {
    await ElMessageBox.confirm(
      `Удалить файл «${log.file_name}»? Восстановить его будет невозможно.`,
      'Удаление аудиофайла',
      { type: 'warning', confirmButtonText: 'Удалить', cancelButtonText: 'Отмена' },
    );
    router.delete(route('admin.karaoke-upload-logs.file.delete', { log: log.id }), {
      preserveScroll: true,
      onSuccess: () => {
        fileDialogVisible.value = false;
        selectedLog.value = null;
        ElMessage.success('Аудиофайл удалён');
      },
    });
  } catch (_) {}
};
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

    <div class="admin-toolbar">
      <label
        class="admin-filter"
        :class="{ 'admin-filter--active': excludeAdmin }"
      >
        <span class="admin-filter__icon">
          <el-icon>
            <Hide v-if="excludeAdmin" />
            <View v-else />
          </el-icon>
        </span>

        <span class="admin-filter__title admin-filter__title--desktop">Скрыть записи Admin</span>
        <span class="admin-filter__title admin-filter__title--mobile">Без Admin</span>

        <el-switch
          v-model="excludeAdmin"
          aria-label="Скрыть записи пользователя Admin"
          class="admin-filter__switch"
          @change="applyFilters"
        />
      </label>

      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.karaoke-upload-logs'"
        :compact="isMobile"
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
          <el-table-column label="Аудиофайл" width="210">
            <template #default="scope">
              <div v-if="scope.row.has_file" class="flex items-center gap-2">
                <el-button size="small" type="primary" plain @click="showFile(scope.row)">
                  Просмотреть
                </el-button>
                <el-button size="small" type="danger" plain @click="deleteFile(scope.row)">
                  Удалить
                </el-button>
              </div>
              <span v-else class="text-slate-400">—</span>
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

    <el-dialog v-model="fileDialogVisible" title="Загруженный аудиофайл" width="min(560px, 92vw)">
      <template v-if="selectedLog">
        <div class="mb-3">
          <div class="font-medium text-slate-800">{{ selectedLog.song_artist }} — {{ selectedLog.song_title }}</div>
          <div class="text-sm text-slate-500">
            {{ selectedLog.file_name }}<template v-if="selectedLog.file_size_formatted"> · {{ selectedLog.file_size_formatted }}</template>
          </div>
        </div>
        <audio :key="selectedLog.id" controls class="w-full" :src="selectedLog.file_url" />
        <div class="mt-4 flex justify-end">
          <el-button type="danger" plain @click="deleteFile(selectedLog)">
            Удалить файл
          </el-button>
        </div>
      </template>
    </el-dialog>
  </admin-layout>
</template>

<style scoped>
.admin-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  padding: 0;
}

.admin-filter {
  display: flex;
  min-width: 225px;
  height: 26px;
  box-sizing: border-box;
  align-items: center;
  gap: 0.4rem;
  padding: 0 0.45rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.55rem;
  background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
  cursor: pointer;
  transition:
    border-color 160ms ease,
    background 160ms ease,
    box-shadow 160ms ease;
}

.admin-filter:hover {
  border-color: #cbd5e1;
  box-shadow: 0 2px 8px rgb(15 23 42 / 6%);
}

.admin-filter:focus-within {
  outline: 2px solid rgb(59 130 246 / 14%);
  outline-offset: 1px;
}

.admin-filter--active {
  border-color: #bfdbfe;
  background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
}

.admin-filter__icon {
  display: inline-flex;
  width: 1.125rem;
  height: 1.125rem;
  flex: 0 0 1.125rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.35rem;
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.78rem;
  transition: color 160ms ease, background 160ms ease;
}

.admin-filter--active .admin-filter__icon {
  background: #dbeafe;
  color: #2563eb;
}

.admin-filter__title {
  flex: 1;
  color: #475569;
  font-size: 0.76rem;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}

.admin-filter__title--mobile {
  display: none;
}

.admin-filter__switch {
  flex: 0 0 auto;
  transform: scale(0.86);
  transform-origin: right center;
  --el-switch-on-color: #2563eb;
  --el-switch-off-color: #cbd5e1;
}

@media (max-width: 640px) {
  .admin-toolbar {
    flex-wrap: nowrap;
    gap: 0.5rem;
  }

  .admin-filter {
    min-width: 0;
  }

  .admin-filter__title--desktop {
    display: none;
  }

  .admin-filter__title--mobile {
    display: block;
  }

  .admin-toolbar :deep(.pagination) {
    flex: 0 0 auto;
    margin-left: auto;
  }
}

</style>
