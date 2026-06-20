<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  userReferers: { type: Array, default: () => [] },
  pagination: { type: Object, default: null },
  sourceStatsToday: { type: Object, default: () => ({ google: 0, yandex: 0, other: 0 }) },
});

const sourceIconProps = {
  google: { icon: 'prime:google', width: 20, height: 20 },
  yandex: { icon: 'brandico:yandex-rect', width: 20, height: 20 },
  other: { icon: 'pajamas:severity-unknown', width: 20, height: 20, color: '#d99a12' },
};

const sourceLabels = {
  google: 'Google',
  yandex: 'Яндекс',
  other: 'Другие сайты',
};

const sourceStatItems = computed(() => ['google', 'yandex', 'other'].map((source) => ({
  source,
  label: sourceLabels[source],
  count: props.sourceStatsToday?.[source] ?? 0,
  icon: sourceIconProps[source],
})));

const getSourceIconProps = (source) => sourceIconProps[source] ?? sourceIconProps.other;

const getLandingIconProps = (url) => {
  if (!url) return null

  if (url.includes('lyrics')) {
    return { icon: 'fa6-solid:music', width: 20, height: 20 }
  }

  if (url.includes('grammar')) {
    return { icon: 'tabler:text-grammar', width: 20, height: 20 }
  }

  return { icon: 'pajamas:severity-unknown', width: 20, height: 20, color: '#d99a12' };
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Посещаемость"
    fixed-height
  >
    <Head>
      <title>Посещаемость</title>
      <meta name="description" content="Посещаемость" />
      <meta property="og:title" content="Посещаемость" />
      <meta property="og:description" content="Посещаемость" />
    </Head>

    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap items-center gap-2">
        <div
          v-for="item in sourceStatItems"
          :key="item.source"
          class="flex h-8 min-w-[138px] items-center justify-between gap-2 rounded border border-slate-200 bg-white px-2 text-sm shadow-sm"
        >
          <span class="flex items-center gap-1.5 text-slate-600">
            <Icon v-bind="item.icon" />
            {{ item.label }} сегодня
          </span>
          <span class="font-semibold text-slate-900">{{ item.count }}</span>
        </div>
      </div>

      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.referers'"
      />
    </div>

    <div class="min-h-0 min-w-0 flex-1 overflow-hidden">
      <div class="h-full overflow-x-auto">
        <el-table
          :data="userReferers"
          style="width: 100%;"
          class="min-w-0"
          height="100%"
        >
          <el-table-column fixed prop="icon" label="" width="50">
            <template #default="scope">

              <Icon v-bind="getSourceIconProps(scope.row.referer_source)" />

            </template>
          </el-table-column>
          <el-table-column prop="referer_url" label="Откуда" width="320" show-overflow-tooltip>
            <template #default="scope">

              {{ scope.row.referer_url }}

            </template>
          </el-table-column>
          <el-table-column prop="landing_page" label="Куда" width="340" show-overflow-tooltip>
            <template #default="scope">

              <div class="flex flex-row gap-2">
                <Icon
                  v-if="getLandingIconProps(scope.row.landing_page)"
                  v-bind="getLandingIconProps(scope.row.landing_page)"
                />
                {{ scope.row.landing_page }}
              </div>

            </template>
          </el-table-column>
          <el-table-column prop="ip_address" label="IP" width="140">
            <template #default="scope">

              {{ scope.row.ip_address }}

            </template>
          </el-table-column>
          <el-table-column prop="user_name" label="Имя пользователя" width="160">
            <template #default="scope">

              {{ scope.row.user?.name  }}

            </template>
          </el-table-column>
          <el-table-column prop="utm_source" label="UTM source" width="160">
            <template #default="scope">

              {{ scope.row.utm_source  }}

            </template>
          </el-table-column>
          <el-table-column prop="utm_medium" label="UTM medium" width="160">
            <template #default="scope">

              {{ scope.row.utm_medium  }}

            </template>
          </el-table-column>
          <el-table-column prop="utm_campaign" label="UTM campaign" width="160">
            <template #default="scope">

              {{ scope.row.utm_campaign  }}

            </template>
          </el-table-column>
          <el-table-column prop="utm_term" label="UTM term" width="160">
            <template #default="scope">

              {{ scope.row.utm_term  }}

            </template>
          </el-table-column>
          <el-table-column prop="utm_content" label="UTM content" width="160">
            <template #default="scope">

              {{ scope.row.utm_content  }}

            </template>
          </el-table-column>
          <el-table-column prop="user_agent" label="User agent" width="160" show-overflow-tooltip>
            <template #default="scope">

              {{ scope.row.user_agent  }}

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

    <div class="flex shrink-0 justify-end">
      <pagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :total="pagination.total"
        :route-name="'admin.referers'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>
