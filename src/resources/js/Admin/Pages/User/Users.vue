<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@element-plus/icons-vue';

import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import Pagination from '@/App/Components/Pagination/Pagination.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  users: { type: Array, default: [] },
  pagination: { type: Object, default: {} },
  filters: { type: Object, default: {} },
});

const search = ref(props.filters.search ?? '');
const sort = ref(props.filters.sort ?? null);
const direction = ref(props.filters.direction ?? null);

const defaultSort = computed(() => ({
  prop: sort.value,
  order: direction.value === 'asc' ? 'ascending' : (direction.value === 'desc' ? 'descending' : null),
}));

const loadUsers = (extra = {}) => {
  router.get(
    route('admin.users'),
    {
      search: search.value || undefined,
      sort: sort.value || undefined,
      direction: direction.value || undefined,
      ...extra,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const submitSearch = () => {
  loadUsers({ page: 1 });
};

const clearSearch = () => {
  search.value = '';
  loadUsers({ page: 1 });
};

const handleSortChange = ({ prop, order }) => {
  sort.value = order ? prop : null;
  direction.value = order === 'ascending' ? 'asc' : (order === 'descending' ? 'desc' : null);
  loadUsers({ page: 1 });
};

const columnHeader = (label) => label;
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Пользователи"
    fixed-height
  >
    <Head>
      <title>Список пользователей</title>
      <meta name="description" content="Список пользователей" />
      <meta property="og:title" content="Список пользователей" />
      <meta property="og:description" content="Список пользователей" />
    </Head>

    <div class="users-wrapper">
      <el-card shadow="never" class="users-card">
        <div class="toolbar">
          <div>
            <div class="toolbar-title">Пользователи</div>
            <div class="toolbar-subtitle">
              Найдено: {{ pagination.total ?? users.length }}
            </div>
          </div>

          <div class="search-controls">
            <el-input
              v-model="search"
              clearable
              placeholder="Поиск по имени или email"
              class="search-input"
              @keyup.enter="submitSearch"
              @clear="clearSearch"
            />
            <el-button
              type="primary"
              class="search-button"
              aria-label="Найти"
              @click="submitSearch"
            >
              <el-icon class="search-button-icon">
                <Search />
              </el-icon>
              <span class="search-button-text">
              Найти
              </span>
            </el-button>
          </div>
        </div>

        <el-table
          :data="users"
          :default-sort="defaultSort"
          height="100%"
          border
          stripe
          class="users-table"
          empty-text="Пользователи не найдены"
          @sort-change="handleSortChange"
        >
          <el-table-column
            prop="avatar"
            label="Аватар"
            width="96"
            align="center"
            sortable="custom"
          >
            <template #header>
              <el-tooltip :content="columnHeader('Аватар')" placement="top">
                <span class="column-title">{{ columnHeader('Аватар') }}</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <Link :href="route('admin.user.show', row.id)" class="avatar-link">
                <el-avatar :src="row.avatar_link" :size="48" shape="square" class="user-avatar" />
              </Link>
            </template>
          </el-table-column>

          <el-table-column
            prop="name"
            label="Имя"
            min-width="170"
            sortable="custom"
          >
            <template #header>
              <el-tooltip :content="columnHeader('Имя')" placement="top">
                <span class="column-title">{{ columnHeader('Имя') }}</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <Link
                :href="route('admin.user.show', row.id)"
                class="user-link"
              >
                {{ row.name }}
              </Link>
            </template>
          </el-table-column>

          <el-table-column
            prop="email"
            label="Емаил"
            min-width="220"
            sortable="custom"
            show-overflow-tooltip
          >
            <template #header>
              <el-tooltip :content="columnHeader('Емаил')" placement="top">
                <span class="column-title">{{ columnHeader('Емаил') }}</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <span class="muted-value truncate-cell">{{ row.email || '—' }}</span>
            </template>
          </el-table-column>

          <el-table-column
            prop="rang"
            label="Ранг"
            min-width="150"
            sortable="custom"
          >
            <template #header>
              <el-tooltip :content="columnHeader('Ранг')" placement="top">
                <span class="column-title">{{ columnHeader('Ранг') }}</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <el-tag
                :type="row.is_ban ? 'danger' : 'info'"
                effect="plain"
              >
                {{ row.rang?.title || '—' }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column
            prop="email_verified_at"
            label="Дата подтверждения регистрации"
            min-width="230"
            sortable="custom"
          >
            <template #header>
              <el-tooltip :content="columnHeader('Дата подтверждения регистрации')" placement="top">
                <span class="column-title">{{ columnHeader('Дата подтверждения регистрации') }}</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <span class="muted-value">{{ row.email_verified_at || '—' }}</span>
            </template>
          </el-table-column>

          <el-table-column
            prop="created_at"
            label="Дата регистрации"
            min-width="180"
            sortable="custom"
          >
            <template #header>
              <el-tooltip :content="columnHeader('Дата регистрации')" placement="top">
                <span class="column-title">{{ columnHeader('Дата регистрации') }}</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <span class="muted-value">{{ row.created_at || '—' }}</span>
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination-container">
          <pagination
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
            :per-page="pagination.per_page"
            :total="pagination.total"
            :route-name="'admin.users'"
          />
        </div>
      </el-card>
    </div>
  </admin-layout>
</template>

<style scoped>
.users-wrapper{
  @apply bg-sky-50 flex flex-col;
  height: calc(100vh - 56px);
  min-height: 0;
  overflow: hidden;
}

.users-card{
  @apply border-0 rounded-none flex-1 min-h-0;
}

.users-card :deep(.el-card__body){
  @apply h-full flex flex-col min-h-0 p-0;
}

.toolbar{
  @apply flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 p-4;
}

.toolbar-title{
  @apply text-xl font-semibold text-gray-800;
}

.toolbar-subtitle{
  @apply text-sm text-gray-500;
}

.search-controls{
  @apply flex flex-row gap-2 items-center w-full lg:w-auto;
}

.search-input{
  @apply flex-1 min-w-0 lg:w-[320px];
}

.search-button{
  @apply shrink-0 w-10 px-0 sm:w-auto sm:px-4;
}

.search-button-icon{
  @apply sm:hidden;
}

.search-button-text{
  @apply hidden sm:inline;
}

.users-table{
  @apply w-full flex-1 min-h-0;
}

.users-table :deep(th .caret-wrapper){
  display: none;
}

.users-table :deep(th .cell){
  @apply flex items-center min-w-0;
  white-space: nowrap;
}

.users-table :deep(th.ascending .caret-wrapper),
.users-table :deep(th.descending .caret-wrapper){
  display: inline-flex;
  flex: 0 0 auto;
}

.users-table :deep(th.ascending .sort-caret.descending),
.users-table :deep(th.descending .sort-caret.ascending){
  display: none;
}

.users-table :deep(th.ascending .sort-caret.ascending){
  border-bottom-color: var(--el-text-color-placeholder);
}

.users-table :deep(th.descending .sort-caret.descending){
  border-top-color: var(--el-text-color-placeholder);
}

.avatar-link{
  @apply inline-flex;
}

.column-title{
  @apply block truncate min-w-0;
}

.user-link{
  @apply font-semibold text-sky-700 transition hover:text-sky-900 hover:underline;
}

.user-avatar{
  @apply rounded-md;
}

.muted-value{
  @apply text-gray-700;
}

.truncate-cell{
  @apply block truncate;
}

.pagination-container{
  @apply flex justify-end p-4 shrink-0;
}
</style>
