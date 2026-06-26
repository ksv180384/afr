<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import api from '@/Admin/Services/Api/index.js';

const props = defineProps({
  authUser: { type: Object, default: null },
  user: { type: Object, default: {} },
});

const user = ref({ ...props.user });
const updatingToggleBanned = ref(false);
const updatingEmailVerification = ref(false);

const ban = async () => {
  try {
    updatingToggleBanned.value = true;

    const res = await api.user.ban({ id: user.value.id, ban: !user.value.is_ban });

    user.value = {
      ...user.value,
      rang: res.user.rang,
      rang_id: res.user.rang?.id ?? user.value.rang_id,
      is_ban: res.user.is_ban,
      is_admin: res.user.rang?.alias === 'administrator',
      is_moderator: res.user.rang?.alias === 'moderator',
    };
  }
  catch (e) {
    console.error(e);
  }
  finally {
    updatingToggleBanned.value = false;
  }
};

const toggleEmailVerification = async () => {
  try {
    updatingEmailVerification.value = true;

    const res = await api.user.toggleEmailVerification({
      id: user.value.id,
      verified: !user.value.email_verified_at,
    });

    user.value = {
      ...user.value,
      ...res.user,
    };
  }
  catch (e) {
    console.error(e);
  }
  finally {
    updatingEmailVerification.value = false;
  }
};

const showValue = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (typeof value === 'boolean') {
    return value ? 'Да' : 'Нет';
  }

  return value;
};

const mainFields = computed(() => [
  { label: 'ID', value: user.value.id },
  { label: 'Имя', value: user.value.name },
  { label: 'Email', value: user.value.email },
  { label: 'Email подтвержден', value: user.value.email_verified_at },
  { label: 'Письмо подтверждения отправлено', value: user.value.send_verified_email_at },
  { label: 'Дата рождения', value: user.value.birthday },
  { label: 'Пол', value: user.value.gender?.title },
  { label: 'Место жительства', value: user.value.residence },
  { label: 'Ранг', value: user.value.rang?.title },
  { label: 'Alias ранга', value: user.value.rang?.alias },
  { label: 'Флаг admin', value: user.value.admin },
  { label: 'Администратор', value: user.value.is_admin },
  { label: 'Модератор', value: user.value.is_moderator },
  { label: 'Забанен', value: user.value.is_ban },
  { label: 'Confirm token', value: user.value.confirm_token },
  { label: 'Создан', value: user.value.created_at },
  { label: 'Обновлен', value: user.value.updated_at },
]);

const profileFields = computed(() => [
  { label: 'Аватар', value: user.value.avatar },
  { label: 'О себе', value: user.value.info },
  { label: 'Подпись', value: user.value.signature },
]);

const contactFields = computed(() => [
  { label: 'Facebook', value: user.value.infos?.facebook },
  { label: 'Skype', value: user.value.infos?.skype },
  { label: 'Twitter', value: user.value.infos?.twitter },
  { label: 'VK', value: user.value.infos?.vk },
  { label: 'Одноклассники', value: user.value.infos?.odnoklassniki },
  { label: 'Telegram', value: user.value.infos?.telegram },
  { label: 'WhatsApp', value: user.value.infos?.whatsapp },
  { label: 'Viber', value: user.value.infos?.viber },
  { label: 'Instagram', value: user.value.infos?.instagram },
  { label: 'YouTube', value: user.value.infos?.youtube },
]);

const privacyFields = computed(() => [
  { label: 'День рождения', value: user.value.config?.day_birthday },
  { label: 'Год рождения', value: user.value.config?.yar_birthday },
  { label: 'Email', value: user.value.config?.email?.title },
  { label: 'Facebook', value: user.value.config?.facebook?.title },
  { label: 'Skype', value: user.value.config?.skype?.title },
  { label: 'Twitter', value: user.value.config?.twitter?.title },
  { label: 'VK', value: user.value.config?.vk?.title },
  { label: 'Одноклассники', value: user.value.config?.odnoklassniki?.title },
  { label: 'Telegram', value: user.value.config?.telegram?.title },
  { label: 'WhatsApp', value: user.value.config?.whatsapp?.title },
  { label: 'Viber', value: user.value.config?.viber?.title },
  { label: 'Instagram', value: user.value.config?.instagram?.title },
  { label: 'YouTube', value: user.value.config?.youtube?.title },
  { label: 'О себе', value: user.value.config?.info?.title },
  { label: 'Место жительства', value: user.value.config?.residence?.title },
  { label: 'Пол', value: user.value.config?.sex?.title },
]);
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    :title="`Пользователь: ${user.name}`"
  >
    <Head>
      <title>{{ `Пользователь ${user.name}` }}</title>
      <meta name="description" :content="`Данные пользователя ${user.name}`" />
      <meta property="og:title" :content="`Пользователь ${user.name}`" />
      <meta property="og:description" :content="`Данные пользователя ${user.name}`" />
    </Head>

    <div class="user-show-wrapper">
      <div class="page-actions">
        <Link :href="route('admin.users')">
          <el-button
            type="primary"
            plain
          >
            К списку пользователей
          </el-button>
        </Link>
      </div>

      <el-card shadow="never" class="profile-card">
        <div class="profile-header">
          <el-avatar :src="user.avatar_link" :size="96" shape="square" />
          <div class="profile-title">
            <div class="profile-name">{{ user.name }}</div>
            <div class="profile-meta">
              <el-tag v-if="user.rang" type="info">{{ user.rang.title }}</el-tag>
              <el-tag v-if="user.is_ban" type="danger">Забанен</el-tag>
              <el-tag v-if="user.is_admin" type="success">Администратор</el-tag>
              <el-tag v-if="user.is_moderator" type="warning">Модератор</el-tag>
            </div>
            <div class="profile-actions">
              <el-popconfirm
                class="box-item"
                title="Вы уверены?"
                hide-icon
                confirm-button-text="Да"
                cancel-button-text="Нет"
                placement="bottom-start"
                @confirm="ban"
              >
                <template #reference>
                  <el-button
                    v-if="user.is_ban"
                    type="success"
                    plain
                    :loading="updatingToggleBanned"
                  >
                    Разбанить
                  </el-button>
                  <el-button
                    v-else
                    type="danger"
                    plain
                    :loading="updatingToggleBanned"
                  >
                    Забанить
                  </el-button>
                </template>
              </el-popconfirm>
              <el-popconfirm
                class="box-item"
                title="Вы уверены?"
                hide-icon
                confirm-button-text="Да"
                cancel-button-text="Нет"
                placement="bottom-start"
                @confirm="toggleEmailVerification"
              >
                <template #reference>
                  <el-button
                    v-if="user.email_verified_at"
                    type="warning"
                    plain
                    :loading="updatingEmailVerification"
                  >
                    Снять подтверждение email
                  </el-button>
                  <el-button
                    v-else
                    type="success"
                    plain
                    :loading="updatingEmailVerification"
                  >
                    Подтвердить email
                  </el-button>
                </template>
              </el-popconfirm>
            </div>
          </div>
        </div>
      </el-card>

      <el-card shadow="never">
        <template #header>Основные данные</template>
        <el-descriptions :column="2" border>
          <el-descriptions-item
            v-for="field in mainFields"
            :key="field.label"
            :label="field.label"
          >
            {{ showValue(field.value) }}
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card shadow="never">
        <template #header>Профиль</template>
        <el-descriptions :column="1" border>
          <el-descriptions-item
            v-for="field in profileFields"
            :key="field.label"
            :label="field.label"
          >
            <span class="preserve-text">{{ showValue(field.value) }}</span>
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card shadow="never">
        <template #header>Контакты</template>
        <el-descriptions :column="2" border>
          <el-descriptions-item
            v-for="field in contactFields"
            :key="field.label"
            :label="field.label"
          >
            {{ showValue(field.value) }}
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card shadow="never">
        <template #header>Настройки видимости</template>
        <el-descriptions :column="2" border>
          <el-descriptions-item
            v-for="field in privacyFields"
            :key="field.label"
            :label="field.label"
          >
            {{ showValue(field.value) }}
          </el-descriptions-item>
        </el-descriptions>
      </el-card>
    </div>
  </admin-layout>
</template>

<style scoped>
.user-show-wrapper{
  @apply bg-sky-50 min-h-full p-4 flex flex-col gap-4;
}

.page-actions{
  @apply flex justify-end;
}

.profile-card{
  @apply border-blue-100;
}

.profile-header{
  @apply flex items-center gap-4;
}

.profile-title{
  @apply flex flex-col gap-2;
}

.profile-name{
  @apply text-2xl font-semibold text-gray-800;
}

.profile-meta{
  @apply flex flex-wrap gap-2;
}

.profile-actions{
  @apply flex flex-wrap gap-2;
}

.preserve-text{
  @apply whitespace-pre-wrap break-words;
}
</style>
