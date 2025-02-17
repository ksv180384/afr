<script setup>
import api from '@/App/Services/Api';
import { Icon } from '@iconify/vue';

// import Dropdown from '@/Components/Dropdown/AfrDropdown.vue';

import Dropdown from '@/App/Components/Dropdown.vue';
import DropdownLink from "@/App/Components/DropdownLink.vue";

const props = defineProps({
  user: { type: Object, default: {} },
});

const logout = async () => {
  try{
    await api.auth.logout();
  } catch (e) {
    console.error(e);
  } finally {

  }
}

</script>

<template>
  <div>
    <Dropdown align="right" width="50">
      <template #trigger>
        <div class="cursor-pointer" :title="user.name">
          <img class="inline-block h-10 w-10 rounded-full ring-2 ring-white object-cover" :src="user.avatar_link"/>
        </div>
      </template>

      <template #head>
        <div class="dropdown-header">
          {{ user.name }}
        </div>
      </template>

      <template #content>
        <a v-if="user.is_admin" :href="route('admin.index')" class="px-4 py-2 block text-nowrap hover:bg-blue-50">
          Панель администратора
        </a>
        <DropdownLink :href="route('post.create')">
          <span class="text-green-600 flex flex-row text-nowrap items-center gap-2"><Icon icon="material-symbols:add-box" width="24" height="24" /> Добавить пост</span>
        </DropdownLink>
        <DropdownLink :href="route('user.posts')">
          Мои посты
        </DropdownLink>
        <DropdownLink :href="route('profile.edit')">
          Профиль
        </DropdownLink>
        <DropdownLink :href="route('users')">
          Пользователи
        </DropdownLink>
        <DropdownLink :href="route('logout')" method="post" as="button">
          Выход
        </DropdownLink>
      </template>
    </Dropdown>
  </div>
</template>

<style scoped>
.dropdown-header{
  @apply text-lg font-bold pt-2 pb-1 px-2 relative text-center;
}

.dropdown-header:after{
  @apply absolute left-0 h-px my-8 bg-gray-200 border-0 dark:bg-gray-700 w-full;
  content: '';
}
</style>
