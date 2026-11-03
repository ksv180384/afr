<script setup>
import { inject, onBeforeUnmount, ref } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
const props = defineProps({ audio: { type: Object, required: true }, label: { type: String, default: 'Прослушать фразу' } });
const emit = defineEmits(['unavailable']);
const player = inject('practiceAudioPlayer');
const status = ref('idle');
const message = ref('');
const feedback = usePracticeFeedback({ error: message, errorType: 'warning' });
const active = () => ['loading', 'playing'].includes(status.value);
const play = (slow = false) => {
  message.value = '';
  if (!player) { message.value = 'Озвучивание недоступно.'; emit('unavailable'); return; }
  player.play(props.audio, { slow, onState: (next) => {
    status.value = next.state;
    message.value = next.message;
    if (next.state === 'error') emit('unavailable');
  } });
};
onBeforeUnmount(() => { if (active()) player?.stop(); });
</script>
<template>
  <div class="practice-audio">
    <div class="audio-actions">
      <afr-button :aria-label="active() ? 'Остановить звук' : label" @click="active() ? player.stop() : play()">{{ active() ? '■ Остановить' : '▶ ' + label }}</afr-button>
      <afr-button @click="play(true)">Медленнее</afr-button>
      <span v-if="status === 'loading'" role="status">Подготовка звука…</span>
      <span v-if="status === 'playing'" role="status">Воспроизводится</span>
    </div>
    <p v-if="message && !feedback.enabled" class="audio-error" role="status">{{ message }}</p>
  </div>
</template>
<style scoped>
.audio-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.audio-actions :deep(button) { max-width: 100%; white-space: normal; }
.audio-error { color: #92400e; font-size: .95rem; margin-top: .5rem; }
</style>
