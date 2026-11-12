<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';

const player = inject('practiceAudioPlayer', null);
const voices = ref([]);
const voice = ref('');
const pace = ref('normal');
const selectedVoice = computed(() => voices.value.some(option => option.id === voice.value) ? voice.value : '');
let unwatch;
onMounted(() => {
  if (!player) return;
  ({voice: voice.value, pace: pace.value} = player.preferences());
  unwatch = player.watchVoices(options => { voices.value = options; });
});
onBeforeUnmount(() => unwatch?.());
const setVoice = value => { voice.value = value; player.configure({voice: value}); };
const setPace = value => { pace.value = value; player.configure({pace: value}); };
</script>

<template>
  <details v-if="player" class="audio-preferences">
    <summary>Голос и темп</summary>
    <div class="audio-preference-fields">
      <label>
        <span>Французский голос</span>
        <afr-select native :model-value="selectedVoice" :disabled="!voices.length" @update:model-value="setVoice">
          <afr-option value="" label="Автовыбор" />
          <afr-option v-for="option in voices" :key="option.id" :value="option.id" :label="option.name + ' · ' + option.lang" />
        </afr-select>
      </label>
      <label>
        <span>Темп</span>
        <afr-select native :model-value="pace" @update:model-value="setPace">
          <afr-option value="normal" label="Обычный" />
          <afr-option value="deliberate" label="Размеренный" />
        </afr-select>
      </label>
    </div>
    <p v-if="!voices.length" class="voice-note">Французский голос пока недоступен в этом браузере.</p>
  </details>
</template>

<style scoped>
.audio-preferences { margin:.65rem 0; }
summary { cursor:pointer; min-height:44px; display:list-item; padding:.6rem 0; color:#155e91; }
.audio-preference-fields { display:flex; flex-wrap:wrap; gap:.55rem 1rem; }
label { display:grid; gap:.25rem; font-size:.875rem; min-width:0; max-width:100%; }
label :deep(select) { min-height:44px; max-width:100%; }
.voice-note { color:#475569; margin-top:.4rem; font-size:.875rem; }
</style>
