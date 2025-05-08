<script setup>
import { Head } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  userReferers: { type: Array, default: null },
  pagination: { type: Object, default: null },
});

const getIconProps = (url) => {

  if (!url) return null

  // откуда
  if (url.includes('google.com') || url.includes('google.ru')) {
    return { icon: 'prime:google', width: 20, height: 20 }
  }

  if (url.includes('yandex.ru') || url.includes('yandex.com')) {
    return { icon: 'brandico:yandex-rect', width: 20, height: 20 }
  }

  // куда
  if (url.includes('lyrics')) {
    return { icon: 'fa6-solid:music', width: 20, height: 20 }
  }

  if (url.includes('grammar')) {
    return { icon: 'tabler:text-grammar', width: 20, height: 20 }
  }

  return { icon: 'pajamas:severity-unknown', width: 20, height: 20 };
}
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Посещаемость"
  >
    <Head>
      <title>Посещаемость</title>
      <meta name="description" content="Посещаемость" />
      <meta property="og:title" content="Посещаемость" />
      <meta property="og:description" content="Посещаемость" />
    </Head>

    <div class="flex px-3 justify-between pt-2">
      <div class="flex justify-end">
        <pagination
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          :route-name="'admin.referers'"
        />
      </div>
    </div>

    <div class="min-w-0">
      <div class="overflow-x-auto">
        <el-table
          :data="userReferers"
          style="width: 100%;"
          class="min-w-0"
        >
          <el-table-column fixed prop="icon" label="" width="50">
            <template #default="scope">

              <Icon
                v-if="getIconProps(scope.row.referer_url)"
                v-bind="getIconProps(scope.row.referer_url)"
              />

            </template>
          </el-table-column>
          <el-table-column prop="referer_url" label="Откуда" width="300">
            <template #default="scope">

              {{ scope.row.referer_url }}

            </template>
          </el-table-column>
          <el-table-column prop="landing_page" label="Куда" width="300">
            <template #default="scope">

              <div class="flex flex-row gap-2">
                <Icon
                  v-if="getIconProps(scope.row.landing_page)"
                  v-bind="getIconProps(scope.row.landing_page)"
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
          <el-table-column prop="created_at" label="Дата" fixed="right" width="120">
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
        :route-name="'admin.referers'"
      />
    </div>
  </admin-layout>
</template>

<style scoped>

</style>


