<script setup>
import { onMounted, ref, shallowRef } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrCard from '@/App/Components/Card/AfrCard.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import PracticeErrorNotice from './PracticeErrorNotice.vue';
import SessionPlayer from './SessionPlayer.vue';
import { trainingSession } from '@/App/Learning/training.js';
import { completedGuestSessions } from '@/App/Learning/learningAccess.js';
import { createLocalPlayerAdapter } from '@/App/Learning/playerAdapter.js';
import { practiceRouteTransport } from '@/App/Services/Api/daily_practice.js';

const props = defineProps({ owner: Number, unit: { type: String, required: true }, mode: { type: Object, required: true }, guestRepository: Object });
const plan = shallowRef(null);
const active = shallowRef(null);
const busy = ref(false);
const error = ref('');
let storageKey;
const read = () => JSON.parse(localStorage.getItem(storageKey) || '{}');
const load = async () => {
  busy.value = true;
  error.value = '';
  plan.value = null;
  try {
    const guestState = props.owner ? null : await props.guestRepository?.read();
    const ownerKey = props.owner ? 'account.' + props.owner : 'guest.' + (guestState?.guest_id ?? 'new');
    storageKey = 'afr.training.v1.' + ownerKey + '.' + props.unit + '.' + props.mode.id;
    const saved = read();
    if (saved.session) {
      plan.value = saved;
    } else {
      const progress = props.owner ? {} : {
        completed: [...new Set(completedGuestSessions(guestState))],
        current_level: guestState?.current_level,
      };
      const {items} = await practiceRouteTransport.training({unit: props.unit, mode: props.mode.id, previous: saved.previous ?? [], ...progress});
      if (items.length) plan.value = {session: trainingSession(props.mode, items), revision: saved.revision};
    }
  } catch (failure) {
    error.value = failure.response?.status === 403
      ? failure.response.data?.message || 'Тренировки этой темы пока недоступны.'
      : 'Не удалось загрузить тренировку. Проверьте соединение и доступ к хранилищу браузера.';
  } finally { busy.value = false; }
};
const start = async () => {
  const {state} = plan.value;
  const session = {...plan.value.session, title: props.mode.title, description: props.mode.description};
  let revision = plan.value.revision;
  const adapter = createLocalPlayerAdapter({mode: 'training', loadSession: () => session, saved: state,
    persist(next) {
      if (revision !== read().revision) throw new Error('Тренировка изменилась в другой вкладке. Откройте её заново.');
      const data = next.finished ? {previous: session.tasks.slice(0, 100).map(task => task.id)} : {session, state: next};
      const nextRevision = crypto.randomUUID();
      localStorage.setItem(storageKey, JSON.stringify({...data, revision: nextRevision}));
      revision = nextRevision;
    },
  });
  try {
    await adapter.start();
    active.value = {adapter, session};
  } catch { error.value = 'Не удалось сохранить тренировку в этом браузере.'; }
};
const exit = () => { active.value = null; load(); };
onMounted(load);
</script>
<template>
  <session-player v-if="active" :adapter="active.adapter" :audio-urls="active.session.audioUrls" @exit="exit" />
  <template v-else>
    <afr-card class="training-option" :aria-busy="busy">
      <h3><daily-practice-icon :name="mode.icon" />{{ mode.title }}</h3>
      <p>{{ mode.description }}</p>
      <afr-button v-if="plan" class="training-start" type="primary" :disabled="busy" @click="start">
        {{ plan.state ? 'Продолжить' : 'Начать' }}
        <daily-practice-icon name="chevron" />
      </afr-button>
      <small v-else-if="!busy && !error">В этой теме пока нет заданий для этой тренировки.</small>
    </afr-card>
    <p v-if="busy" role="status">Подбираем задания…</p>
    <practice-error-notice v-if="error" :message="error" />
  </template>
</template>
<style scoped>
.training-option :deep(.body) {
  padding: clamp(1rem, 3vw, 1.5rem);
  border: 1px solid var(--practice-border);
  border-radius: var(--practice-radius);
  background: #f5f9fd;
}
.training-option h3 { display: flex; align-items: center; gap: .6rem; font-weight: 700; }
.training-option h3 svg { color: #487363; }
.training-option p { margin: .5rem 0 1rem; color: var(--practice-muted); }
.training-option small { color: var(--practice-muted); }
.training-start { min-width: 8.75rem; padding-inline: 1rem; gap: .5rem; font-weight: 700; }
.training-start :deep(svg) { width: 1rem; height: 1rem; margin: 0; }
</style>
