<script setup>
import { ref, shallowRef, toRaw } from 'vue';
import { Head } from '@inertiajs/vue3';
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import SessionPlayer from '@/App/Components/Learning/SessionPlayer.vue';
import { createPreviewAdapter } from '@/App/Learning/playerAdapter.js';

const props = defineProps({ sessions: { type: Array, required: true }, authUser: { type: Object, default: null } });
const adapter = shallowRef(null);
const playerKey = ref(0);
const open = (session) => {
  adapter.value = createPreviewAdapter({ loadSession: async () => toRaw(session) });
  playerKey.value++;
};
</script>
<template>
  <Head title="Предпросмотр занятия"><meta name="robots" content="noindex, nofollow" /></Head>
  <mini-layout :auth-user="authUser">
    <session-player v-if="adapter" :key="playerKey" :adapter="adapter" @exit="adapter = null" />
    <section v-else class="preview-picker">
      <h1>Предпросмотр занятия</h1>
      <p>Локальные образцы для проверки проигрывателя. Ответы хранятся в памяти вкладки; учебный прогресс и личный словарь не изменяются.</p>
      <ul v-if="sessions.length">
        <li v-for="session in sessions" :key="session.id">
          <h2>{{ session.title }}</h2><p>{{ session.description }}</p>
          <p>{{ session.tasks.length }} шагов · Около {{ session.duration_minutes }} мин</p>
          <afr-button @click="open(session)">Открыть: {{ session.title }}</afr-button>
        </li>
      </ul>
      <p v-else role="status">Доступных занятий пока нет.</p>
    </section>
  </mini-layout>
</template>
<style scoped>
.preview-picker { max-width: 880px; margin: auto; padding: 2rem 1rem; color: #12344d; }
h1 { font-size: 1.6rem; font-weight: bold; }
p { margin: .75rem 0; line-height: 1.6; }
ul { display: grid; gap: 1rem; margin-top: 1.5rem; }
li { background: white; padding: 1.25rem; border: 1px solid #cbd5e1; border-radius: .25rem; }
h2 { font-size: 1.2rem; font-weight: bold; }
:deep(button) { min-height: 44px; white-space: normal; }
:deep(button:focus-visible) { outline: 3px solid #19679b; outline-offset: 3px; }
</style>
