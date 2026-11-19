<script setup>
import { getCurrentInstance, onMounted, ref } from 'vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
defineProps({ title: String, full: Boolean, tone: String, closeLabel: { type: String, default: 'Вернуться к вопросу' } });
const emit = defineEmits(['close']);
const heading = ref(null);
const titleId = 'question-overlay-' + getCurrentInstance().uid;
onMounted(() => heading.value?.focus({ preventScroll: true }));
</script>
<template>
  <section class="question-overlay" :class="[tone, { full }]" role="dialog" :aria-labelledby="titleId" @keydown.esc.stop.prevent="emit('close')">
    <header class="overlay-header">
      <h2 :id="titleId" ref="heading" tabindex="-1"><daily-practice-icon v-if="tone" :name="tone === 'success' ? 'check' : tone === 'skipped' ? 'dash' : 'close'" />{{ title }}</h2>
      <button type="button" class="overlay-close" aria-label="Закрыть" @click="emit('close')"><daily-practice-icon name="close" /></button>
    </header>
    <div class="overlay-content"><slot /></div>
    <footer class="overlay-footer"><button type="button" @click="emit('close')">{{ closeLabel }}</button></footer>
  </section>
</template>
<style scoped>
.question-overlay { position: absolute; z-index: 5; inset: auto 3px 3px; display: flex; flex-direction: column; max-height: calc(100% - 6px); border: 1px solid #cad8e6; border-radius: .85rem; background: #fff; box-shadow: 0 -8px 36px #17365420; overflow: hidden; }
.question-overlay.full { inset: 3px; }
.overlay-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-shrink: 0; padding: .5rem .75rem .5rem 1.1rem; border-bottom: 1px solid #e3eaf1; }
h2 { display: flex; align-items: center; gap: .6rem; font-size: 1.1rem; font-weight: 700; }
.overlay-close { display: grid; place-items: center; width: 40px; height: 40px; padding: 0; flex-shrink: 0; color: #62768a; border-radius: .5rem; }
.overlay-close:hover { background: #e5edf5; }
.overlay-content { min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: .85rem 1.1rem; }
.full .overlay-content { flex: 1; }
.overlay-footer { display: flex; justify-content: flex-end; flex-shrink: 0; padding: .25rem .75rem; border-top: 1px solid #e3eaf1; }
.overlay-footer button { color: #165ca7; font-size: .85rem; padding: .4rem; min-height: 36px; }
.success { background: #f5fcf8; border-color: #badfc9; }
.success .overlay-header { color: #226343; border-color: #d7eade; }
.review { background: #fffcf6; border-color: #e8d5b2; }
.review .overlay-header { color: #8a521b; border-color: #efe4d0; }
.skipped { background: #f8fafc; }
@media (max-width: 480px) { .overlay-content { padding: .75rem; } .overlay-header { padding-left: .75rem; } }
</style>
