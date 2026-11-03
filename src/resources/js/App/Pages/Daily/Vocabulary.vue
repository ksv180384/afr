<script setup>
import { Head } from '@inertiajs/vue3';
import PracticeLayout from '@/App/Layouts/PracticeLayout.vue';

import VocabularyPanel from '@/App/Components/Learning/VocabularyPanel.vue';
defineProps({authUser:Object,enabled:Boolean});
</script>
<template>
  <Head title="Мой словарь"><meta name="robots" content="noindex, nofollow"></Head>
  <practice-layout :auth-user="authUser" title="Мой словарь" active="vocabulary" :navigation="enabled"><div class="vocabulary-page">
    <p>Изученные слова и выражения — с произношением и повторением.</p>
    <vocabulary-panel v-if="enabled" :auth-user="authUser" />
    <p v-else role="status">Раздел ещё не открыт для прохождения.</p>
  </div></practice-layout>
</template>

