<script setup>
import { computed, getCurrentInstance, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';

const props = defineProps({ popover: Boolean, compact: Boolean });
const player = inject('practiceAudioPlayer', null);
const root = ref(null);
const trigger = ref(null);
const fieldId = 'audio-preferences-' + getCurrentInstance().uid;
const voices = ref([]);
const voice = ref('');
const pace = ref('normal');
const selectedVoice = computed(() => voices.value.some(option => option.id === voice.value) ? voice.value : '');
let unwatch;
const close = () => {
  if (root.value) root.value.open = false;
  trigger.value?.focus({ preventScroll: true });
};
const onOutside = event => {
  if (props.popover && root.value?.open && !root.value.contains(event.target)) root.value.open = false;
};
onMounted(() => {
  if (!player) return;
  ({voice: voice.value, pace: pace.value} = player.preferences());
  unwatch = player.watchVoices(options => { voices.value = options; });
  document.addEventListener('pointerdown', onOutside);
});
onBeforeUnmount(() => { unwatch?.(); document.removeEventListener('pointerdown', onOutside); });
const setVoice = value => { voice.value = value; player.configure({voice: value}); };
const setPace = value => { pace.value = value; player.configure({pace: value}); };
</script>

<template>
  <details v-if="player" ref="root" class="audio-preferences" :class="{ popover, compact }" @keydown.esc.stop.prevent="close">
    <summary ref="trigger" :aria-label="compact ? 'Голос и темп' : undefined" :title="compact ? 'Голос и темп' : undefined"><daily-practice-icon name="clock" /><span v-if="!compact">Голос и темп</span><daily-practice-icon v-if="!compact" name="chevron" class="settings-chevron" /></summary>
    <div class="audio-preference-panel">
      <header class="settings-header">
        <h2>Голос и темп</h2>
        <button type="button" class="settings-close" aria-label="Закрыть настройки озвучивания" @click="close"><daily-practice-icon name="close" /></button>
      </header>
      <div class="settings-content">
        <div class="voice-field">
          <label :for="fieldId + '-voice'">Французский голос</label>
          <div class="voice-select">
            <select :id="fieldId + '-voice'" :value="selectedVoice" :disabled="!voices.length" @change="setVoice($event.target.value)">
              <option value="">Автоматический выбор</option>
              <option v-for="option in voices" :key="option.id" :value="option.id">{{ option.name }} · {{ option.lang }}</option>
            </select>
            <daily-practice-icon name="chevron" />
          </div>
          <p v-if="!voices.length" class="voice-note">В этом браузере пока нет французского голоса.</p>
        </div>
        <fieldset class="pace-field">
          <legend>Темп речи</legend>
          <div class="pace-options">
            <label v-for="option in [{ value: 'normal', title: 'Обычный', caption: 'Естественный ритм' }, { value: 'deliberate', title: 'Размеренный', caption: 'Чуть медленнее' }]" :key="option.value" class="pace-option" :class="{ selected: pace === option.value }">
              <input class="sr-only" type="radio" :name="fieldId + '-pace'" :value="option.value" :checked="pace === option.value" @change="setPace(option.value)">
              <span class="pace-title">{{ option.title }}<daily-practice-icon name="check" :class="{ concealed: pace !== option.value }" /></span>
              <span class="pace-caption">{{ option.caption }}</span>
            </label>
          </div>
        </fieldset>
      </div>
      <footer class="settings-footer"><span>Сохраняется автоматически</span><button type="button" @click="close">Готово</button></footer>
    </div>
  </details>
</template>

<style scoped>
.audio-preferences { position: relative; margin: .65rem 0; }
.compact > summary { display: grid; place-items: center; width: var(--vocabulary-control-size, 34px); min-height: var(--vocabulary-control-size, 34px); padding: 0; }
.compact > summary > svg:first-child { display: block; width: 18px; height: 18px; }
summary { display: inline-flex; align-items: center; gap: .4rem; min-height: 44px; padding: .5rem .65rem; border-radius: .6rem; color: #51718e; font-size: .8rem; cursor: pointer; list-style: none; white-space: nowrap; }
summary::-webkit-details-marker { display: none; }
summary::marker { content: ''; }
summary:hover, details[open] > summary { background: #e8f1fa; color: #165ca7; }
summary > svg { width: 16px; height: 16px; }
summary .settings-chevron { width: 13px; transform: rotate(90deg); }
details[open] > summary .settings-chevron { transform: rotate(-90deg); }
.audio-preference-panel { margin-top: .4rem; width: 100%; border: 1px solid #d4e0ec; border-radius: 1rem; background: #fff; color: #18344c; overflow: hidden; }
.popover .audio-preference-panel { position: absolute; z-index: 12; top: 100%; right: 0; width: min(360px, calc(100vw - 2.5rem)); max-height: calc(100svh - 6rem); overflow-y: auto; box-shadow: 0 16px 48px #18344c24, 0 3px 10px #18344c0a; }
.settings-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1rem .85rem; border-bottom: 1px solid #e9eff5; }
.settings-header h2 { font-size: .95rem; line-height: 1.4; font-weight: 700; }
.settings-close { display: grid; place-items: center; width: 36px; min-height: 36px; flex-shrink: 0; padding: 0; color: #72879b; border-radius: .5rem; }
.settings-close > svg { width: 18px; height: 18px; }
.settings-close:hover { background: #edf3f9; }
.settings-content { display: grid; gap: 1.1rem; padding: 1rem; }
.voice-field { display: grid; gap: .4rem; min-width: 0; }
.voice-field > label, legend { font-size: .8rem; font-weight: 650; color: #4a657e; }
.voice-select { position: relative; min-width: 0; }
.voice-select select { display: block; width: 100%; min-width: 0; min-height: 44px; padding: .6rem 2rem .6rem .75rem; appearance: none; background: #f8fafc; background-image: none; border: 1px solid #d2deea; border-radius: .6rem; color: #284b6b; font-size: .85rem; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; }
.voice-select select:disabled { color: #8b9bab; background: #f5f7fa; }
.voice-select > svg { position: absolute; right: .75rem; top: 50%; width: 14px; height: 14px; transform: translateY(-50%) rotate(90deg); color: #72879b; pointer-events: none; }
.voice-note { color: #7b8d9f; font-size: .75rem; line-height: 1.5; }
.pace-field { min-width: 0; }
.pace-options { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .5rem; margin-top: .45rem; }
.pace-option { display: grid; gap: .35rem; padding: .7rem .65rem; border: 1px solid #d8e2ec; border-radius: .65rem; cursor: pointer; background: #fff; }
.pace-option.selected { background: #edf5ff; border-color: #83acd5; }
.pace-option:hover { border-color: #83acd5; }
.pace-option:has(input:focus-visible) { outline: 3px solid #195bb2; outline-offset: 2px; }
.pace-title { display: flex; align-items: center; justify-content: space-between; gap: .2rem; font-size: .8rem; font-weight: 650; }
.pace-title > svg { width: 15px; height: 15px; color: #165ca7; }
.pace-caption { font-size: .68rem; color: #71869b; line-height: 1.4; }
.concealed { visibility: hidden; }
.settings-footer { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem 1rem; border-top: 1px solid #e9eff5; background: #f8fafc; }
.settings-footer span { color: #7b8d9f; font-size: .7rem; }
.settings-footer button { flex: 0 0 auto; width: auto; min-height: 36px; padding: .4rem .75rem; border-radius: .5rem; background: #165ca7; color: white; font-size: .8rem; font-weight: 650; white-space: nowrap; }
@media (max-width: 420px) { summary { gap: .3rem; padding-inline: .35rem; } summary > svg:first-child { display: none; } .settings-content { padding: .85rem; } }
</style>
