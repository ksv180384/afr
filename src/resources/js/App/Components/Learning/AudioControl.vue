<script setup>
import { inject, onBeforeUnmount, ref, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
const props = defineProps({ audio: { type: Object, required: true }, label: { type: String, default: 'Прослушать фразу' }, compact: Boolean });
const emit = defineEmits(['available', 'unavailable']);
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
    if (next.state === 'playing') emit('available');
    if (next.state === 'error') emit('unavailable');
  } });
};
const stopIfActive = () => { if (active()) player?.stop(); };
watch([() => props.audio.asset_id, () => props.audio.text, () => props.audio.lang], () => {
  stopIfActive();
  message.value = '';
});
onBeforeUnmount(stopIfActive);
</script>
<template>
  <div class="practice-audio" :class="{ compact }">
    <div class="audio-actions">
      <afr-button class="audio-play" :class="{ playing: active() }" :aria-label="active() ? 'Остановить звук' : label" :title="active() ? 'Остановить звук' : label" @click="active() ? player.stop() : play()">
        <daily-practice-icon :name="active() ? 'pause' : 'play'" />
      </afr-button>
      <afr-button class="audio-slow" aria-label="Прослушать медленнее" title="Прослушать медленнее" @click="play(true)">
        <daily-practice-icon name="clock" />
      </afr-button>
      <span class="sr-only" role="status">{{ status === 'loading' ? 'Подготовка звука…' : status === 'playing' ? 'Воспроизводится' : '' }}</span>
    </div>
    <p v-if="message && !feedback.enabled" class="audio-error" role="status">{{ message }}</p>
  </div>
</template>
<style scoped>
.practice-audio { display: inline-flex; flex-direction: column; flex-shrink: 0; }
.audio-actions { display: flex; flex-wrap: nowrap; align-items: center; gap: .25rem; }
.audio-actions :deep(button) { width: 40px; height: 40px; padding: 0; flex-shrink: 0; }
.audio-actions :deep(button) { max-width: 100%; white-space: normal; }
.audio-error { color: #92400e; font-size: .95rem; margin-top: .5rem; }
.audio-play { width: 48px; height: 48px; padding: 0; border-radius: 50%; background: #edf5ff; color: #165ca7; border-color: #c4d9ed; flex-shrink: 0; }
.audio-play :deep(svg) { margin: 0; width: 22px; height: 22px; }
.audio-play:not(.playing) :deep(svg) { fill: currentColor; stroke-width: 0; }
.audio-play.playing { background: #165ca7; color: #fff; border-color: #165ca7; }
.audio-slow { color: #64748b; border-color: transparent; background: transparent; font-size: .8rem; gap: .35rem; }
.audio-slow :deep(svg) { width: 17px; height: 17px; margin: 0; }
.compact .audio-actions {
  gap: .25rem;
}
.compact :deep(button) {
  width: 44px;
  height: 44px;
  padding: 0;
}
.compact :deep(button > svg) {
  margin: 0;
  width: 19px;
  height: 19px;
}
</style>
