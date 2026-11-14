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
          <Link class="link mx-10" :href="route('info.terms-user')">Правила пользовательского соглашения</Link>
          <Link class="link mx-10" :href="route('info.privacy-policy')">Политика по защите персональных данных</Link>
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
  @apply flex lg:block flex-col;
}
</style>
