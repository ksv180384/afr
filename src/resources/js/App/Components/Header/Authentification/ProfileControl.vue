<script setup>
import Dropdown from '@/App/Components/Dropdown.vue';
import DropdownLink from '@/App/Components/DropdownLink.vue';

defineProps({
  user: { type: Object, required: true },
});
</script>

<template>
  <Dropdown
    trigger-label="Меню профиля"
    width="64"
    content-classes="max-w-[calc(100vw-6rem)] lg:max-w-[calc(100vw-2rem)]"
  >
    <template #trigger="{ open }">
      <img class="trigger-avatar" :src="user.avatar_link" alt="" width="36" height="36" />
      <span class="trigger-chevron" :class="{ 'is-open': open }" aria-hidden="true" />
    </template>

    <template #head>
      <div class="profile-summary">
        <img class="summary-avatar" :src="user.avatar_link" alt="" width="40" height="40" loading="lazy" />
        <p class="profile-name">{{ user.name }}</p>
      </div>
    </template>

    <template #content>
      <nav class="profile-links" aria-label="Меню профиля">
        <DropdownLink :href="route('post.create')" icon="tabler:square-plus" tone="primary">
          Добавить пост
        </DropdownLink>
        <DropdownLink :href="route('user.posts')" icon="tabler:files">
          Мои посты
        </DropdownLink>
        <hr class="profile-divider" />
        <DropdownLink :href="route('profile.edit')" icon="tabler:user">
          Профиль
        </DropdownLink>
        <DropdownLink :href="route('users')" icon="tabler:users">
          Пользователи
        </DropdownLink>
        <DropdownLink v-if="user.is_admin" :href="route('admin.index')" icon="tabler:shield" full-reload>
          Панель администратора
        </DropdownLink>
        <hr class="profile-divider" />
        <DropdownLink :href="route('logout')" icon="tabler:logout" tone="danger" method="post" as="button">
          Выход
        </DropdownLink>
      </nav>
    </template>
  </Dropdown>
</template>

<style scoped>
.trigger-avatar {
  @apply h-9 w-9 shrink-0 rounded-full bg-blue-50 object-cover ring-2 ring-white;
}

.trigger-chevron {
  @apply me-1 h-1.5 w-1.5 -translate-y-px rotate-45 border-b-2 border-e-2 motion-safe:transition-transform;
  border-color: currentColor;
}

.trigger-chevron.is-open {
  @apply translate-y-px -rotate-[135deg];
}

.profile-summary {
  @apply flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3;
}

.summary-avatar {
  @apply h-10 w-10 shrink-0 rounded-full bg-blue-100 object-cover;
}

.profile-name {
  @apply min-w-0 break-words text-sm font-semibold leading-5 text-slate-900;
}

.profile-links {
  @apply p-1.5;
}

.profile-divider {
  @apply mx-2.5 my-1 border-slate-200;
}
</style>
