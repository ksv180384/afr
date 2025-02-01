<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { declensionYears } from '@/Helpers/helper.js';
import { Icon } from '@iconify/vue';
import dayjs from '@/Plugins/dayjsRu.js';

import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrGender from '@/App/Components/AfrGender.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  user: { type: Object, default: {} },
});

const dayBirthday = computed(() => {
  if(!props.user.day_birthday){
    return '';
  }
  const d = dayjs(props.user.day_birthday, 'DD.MM');
  return d.format('D MMMM');
});

const age = computed(() => {
  if(!props.user.yar_birthday){
    return '';
  }

  const birth = props.user.day_birthday ? dayjs(`${props.user.day_birthday}.${props.user.yar_birthday}`, 'DD.MM.YYYY') : dayjs(props.user.yar_birthday, 'YYYY');
  const today = dayjs(); // Текущая дата
  return today.diff(birth, 'year');
});
</script>

<template>
  <default-layout
    :auth-user="authUser"
    :words="words"
    :proverb="proverb"
  >
    <Head>
      <title>Пользователь {{ user.name }}</title>
      <meta name="description" :content="`Пользователь ${user.name}`" />
      <meta property="og:title" :content="`Пользователь ${user.name}`" />
      <meta property="og:description" :content="`Пользователь ${user.name}`" />
    </Head>

    <div class="user-wrapper">

      <div class="flex flex-col lg:flex-row gap-2 lg:gap-4">
        <div class="flex flex-col items-center">
          <div class="img-block">
            <img :src="user.avatar_link" :alt="user.name">
          </div>
        </div>
        <div class="flex flex-col gap-1 flex-1">
          <div class="flex items-center gap-2 justify-center lg:justify-start">
            <span class="font-bold text-2xl">{{ user.name }}</span> <afr-gender v-if="user.gender" :gender="user.gender"/>
          </div>
          <div class="text-sm mb-2 -mt-1.5 flex justify-center lg:justify-start">
            {{ user.rang.title }}
          </div>
          <div v-if="user.email">
            Email: <span class="font-bold">{{ user.email }}</span>
          </div>
          <div v-if="dayBirthday || age">
            Дата рождения: <span class="font-bold">{{ dayBirthday }} <span v-if="age" :title="props.user.yar_birthday" :class="{ 'brackets': dayBirthday }">{{ declensionYears(age) }}</span></span>
          </div>
          <div v-if="user.residence">
            Город: <span class="font-bold">{{ user.residence }}</span>
          </div>
          <div class="contacts-container">
            <div class="contacts-links">
              <a v-if="user.ok" :href="`https://ok.ru/${user.ok}`" target="_blank" rel="nofollow noopener">
                <Icon icon="fa-brands:odnoklassniki-square" width="28px" height="28px" color="#ee8308"/>
              </a>
              <a v-if="user.vk" :href="`https://vk.com/${user.vk}`" target="_blank" rel="nofollow noopener">
                <Icon icon="ri:vk-fill" width="28px" height="28px" color="#5181b8"/>
              </a>
              <a v-if="user.youtube" :href="`https://youtube.com/${user.youtube}`" target="_blank" rel="nofollow noopener">
                <Icon icon="mdi:youtube" width="28px" height="28px" color="red"/>
              </a>
              <a v-if="user.telegram" :href="`https://t.me/${user.telegram}`">
                <Icon icon="ic:baseline-telegram" width="28px" height="28px" color="#039be5"/>
              </a>
            </div>
            <span v-if="user.skype" class="contact-item">
              <Icon icon="mdi:skype" width="28px" height="28px" color="#00a8ef"/> {{ user.skype }}
            </span>
            <span v-if="user.whatsapp" class="contact-item">
              <Icon icon="ic:baseline-whatsapp" width="28px" height="28px" color="#00a858"/> {{ user.whatsapp }}
            </span>
          </div>
          <div v-if="user.info" class="my-4">
            {{ user.info }}
          </div>
          <div>
            Дата регистрации: <span class="font-bold">{{ user.created_at }}</span>
          </div>
        </div>
      </div>
    </div>
  </default-layout>
</template>

<style scoped>
.user-wrapper{
  @apply bg-sky-50 min-h-full px-2 py-4 lg:p-10;
}

.img-block{
  @apply h-[240px] w-[180px] rounded-lg overflow-hidden;
}

.img-block img{
  @apply w-full h-full object-cover object-top;
}

.brackets{

}

.brackets:before{
  content: '(';
}

.brackets:after{
  content: ')';
}

.contacts-container{
  @apply flex flex-col justify-start gap-1 w-full mt-4;
}

.contacts-links{
  @apply flex gap-2;
}

.contacts-container .contact-item{
  @apply flex flex-nowrap gap-1;
}
</style>
