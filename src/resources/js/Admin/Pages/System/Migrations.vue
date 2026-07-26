<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  migrations: { type: Array, default: () => [] },
  statusError: { type: String, default: null },
  result: { type: Object, default: null },
});

const form = useForm({});

/** Подсчитывает количество миграций, которые ещё не запускались. */
const pendingCount = computed(() => props.migrations.filter((migration) => migration.status === 'pending').length);
/** Подсчитывает количество уже выполненных миграций. */
const completedCount = computed(() => props.migrations.length - pendingCount.value);

/** Запрашивает подтверждение и отправляет защищённый POST-запрос на запуск миграций. */
const runMigrations = () => {
  const message = pendingCount.value > 0
    ? `Будут запущены миграции: ${pendingCount.value}. Продолжить?`
    : 'Новых миграций не найдено. Всё равно выполнить проверку?';

  if (!window.confirm(message)) {
    return;
  }

  form.post(route('admin.migrations.run'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <admin-layout :auth-user="authUser" title="Миграции базы данных">
    <Head>
      <title>Миграции базы данных - Панель администратора</title>
    </Head>

    <div class="p-4 space-y-4">
      <el-alert
        title="Операция изменяет структуру базы данных"
        description="Запускайте миграции только после резервного копирования базы. Одновременно может выполняться только один запуск."
        type="warning"
        :closable="false"
        show-icon
      />

      <div class="flex flex-wrap items-center gap-3">
        <el-button
          type="primary"
          :loading="form.processing"
          :disabled="form.processing"
          @click="runMigrations"
        >
          {{ form.processing ? 'Миграции выполняются…' : 'Запустить миграции' }}
        </el-button>

        <el-tag type="success">Выполнено: {{ completedCount }}</el-tag>
        <el-tag :type="pendingCount > 0 ? 'warning' : 'info'">Ожидает: {{ pendingCount }}</el-tag>
      </div>

      <el-alert
        v-if="statusError"
        :title="statusError"
        type="error"
        :closable="false"
        show-icon
      />

      <div
        v-if="result"
        class="rounded border p-4"
        :class="result.success ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50'"
      >
        <div class="mb-2 flex flex-wrap items-center gap-2">
          <el-tag :type="result.success ? 'success' : 'danger'">
            {{ result.success ? 'Успешно' : 'Ошибка' }}
          </el-tag>
          <span>Код завершения: {{ result.exit_code ?? '—' }}</span>
          <span>Запущено: {{ result.started_at }}</span>
          <span>Время: {{ result.duration_ms }} мс</span>
        </div>
        <pre class="max-h-[420px] overflow-auto whitespace-pre-wrap rounded bg-slate-950 p-3 text-sm text-slate-100">{{ result.output }}</pre>
      </div>

      <el-table :data="migrations" border stripe class="w-full">
        <el-table-column prop="name" label="Миграция" min-width="520" />
        <el-table-column label="Статус" width="140">
          <template #default="scope">
            <el-tag :type="scope.row.status === 'completed' ? 'success' : 'warning'">
              {{ scope.row.status === 'completed' ? 'Выполнена' : 'Ожидает' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="batch" label="Пакет" width="90">
          <template #default="scope">
            {{ scope.row.batch ?? '—' }}
          </template>
        </el-table-column>
      </el-table>
    </div>
  </admin-layout>
</template>
