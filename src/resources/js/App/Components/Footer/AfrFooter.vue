<script setup>
import { Link } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { openCookiePreferences } from '@/App/Services/cookiePreferences.js';
</script>

<template>
  <footer class="afr-footer">
    <div class="afr-footer-content">
      <ul class="text-center">
        <li>© 2010 - {{ dayjs().format('YYYY') }} гг ApprendreFr.ru</li>
        <li>e-mail: admin@apprendrefr.ru</li>
        <li class="infos-block">
          <Link class="link" :href="route('info.terms-user')">Правила сайта</Link>
          <Link class="link" :href="route('info.privacy-policy')">Политика конфиденциальности</Link>
          <Link class="link" :href="route('info.personal-data-consent')">Согласие на обработку персональных данных</Link>
        </li>
        <li><button type="button" class="link" @click="openCookiePreferences">Настройки cookie</button></li>
      </ul>
    </div>
  </footer>
</template>

<style scoped>
.afr-footer{
  @apply bg-blue-50 mt-0.5 text-sm;
}

.afr-footer-content{
  @apply py-4;
}

.afr-footer-content ul li{
  @apply py-1;
}

.infos-block{
  @apply flex flex-wrap items-center justify-center gap-x-6 gap-y-2 px-4;
}
</style>
