<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({active:String});
</script>
<template>
  <nav class="practice-nav" aria-label="Навигация практики">
    <Link href="/practice" :aria-current="active==='catalog'?'page':null">Все задания по уровням</Link>
    <Link href="/daily" :aria-current="active==='today'?'page':null">Сегодня</Link>
    <Link href="/daily/vocabulary" :aria-current="active==='vocabulary'?'page':null">Мой словарь</Link>
    <Link href="/daily/progress" :aria-current="active==='progress'?'page':null">Прогресс</Link>
  </nav>
</template>
