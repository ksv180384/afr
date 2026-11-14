<script setup>
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { clearMetrikaStorage } from '@/App/Services/cookiePreferences.js';

const visible = ref(false);
const saving = ref(false);
const error = ref(false);
const heading = ref(null);
const preference = ref(null);
let counterId;
let loadingMetrika = false;
let channel;
let trigger;

function startMetrika() {
  if (!counterId || loadingMetrika || preference.value !== true) return;

  loadingMetrika = true;
  const disableFlag = 'disableYaCounter' + counterId;
  window[disableFlag] = false;
  window.ym = window.ym ?? function (...args) {
    (window.ym.a ??= []).push(args);
  };
  window.ym.l = Date.now();

  const script = document.createElement('script');
  script.src = 'https://mc.yandex.ru/metrika/tag.js';
  script.async = true;
  script.onerror = () => {
    loadingMetrika = false;
    script.remove();
  };
  script.onload = () => {
    if (preference.value !== true) return;
    window.ym(counterId, 'init', {
      accurateTrackBounce: true,
      trackLinks: false,
      clickmap: false,
      webvisor: false,
      sendTitle: false,
    });
  };
  document.head.append(script);
}

function applyChoice(analytics) {
  preference.value = analytics;
  visible.value = analytics === null;
  if (analytics === true) {
    startMetrika();
  } else {
    if (counterId) window['disableYaCounter' + counterId] = true;
    clearMetrikaStorage();
    // Reload to remove the initialized counter and its listeners.
    if (loadingMetrika) window.location.reload();
  }
  if (!visible.value) closeSettings();
}

function closeSettings() {
  visible.value = false;
  trigger?.focus({ preventScroll: true });
  trigger = null;
}

async function save(analytics) {
  saving.value = true;
  error.value = false;
  try {
    const { data } = await axios.post('/cookie-preferences', { analytics });
    channel?.postMessage(data.analytics);
    applyChoice(data.analytics);
  } catch {
    error.value = true;
  } finally {
    saving.value = false;
  }
}

async function openSettings() {
  trigger = document.activeElement;
  error.value = false;
  visible.value = true;
  await nextTick();
  heading.value?.focus({ preventScroll: true });
}

onMounted(() => {
  counterId = Number(document.querySelector('meta[name="afr-metrika-counter"]')?.content);
  const choice = document.querySelector('meta[name="afr-cookie-preferences"]')?.content;
  applyChoice(choice === 'analytics' ? true : choice === 'essential' ? false : null);

  window.addEventListener('afr:cookie-preferences', openSettings);
  if (typeof BroadcastChannel !== 'undefined') {
    channel = new BroadcastChannel('afr-cookie-preferences');
    channel.onmessage = ({ data }) => {
      if (typeof data === 'boolean') applyChoice(data);
    };
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('afr:cookie-preferences', openSettings);
  channel?.close();
});
</script>

<template>
  <section v-if="visible" class="cookie-notice" aria-labelledby="cookie-notice-title">
    <div class="cookie-heading">
      <h2 id="cookie-notice-title" ref="heading" tabindex="-1">Файлы cookie</h2>
      <button v-if="preference !== null" type="button" aria-label="Закрыть настройки cookie" :disabled="saving" @click="closeSettings">×</button>
    </div>
    <p>
      Необходимые cookie обеспечивают вход в аккаунт, безопасность форм и сохранение вашего выбора.
      С вашего согласия мы используем cookie для подсчёта просмотров и анализа посещений, в том числе с помощью Яндекс Метрики.
    </p>
    <Link href="/privacy-policy#cookies" @click="preference !== null && closeSettings()">Подробнее об использовании cookie</Link>
    <p v-if="error" class="cookie-error" role="alert">Не удалось сохранить выбор. Попробуйте ещё раз.</p>
    <div class="cookie-actions" :aria-busy="saving">
      <button type="button" :disabled="saving" @click="save(false)">Только необходимые</button>
      <button type="button" :disabled="saving" @click="save(true)">Принять все</button>
    </div>
  </section>
</template>

<style scoped>
.cookie-notice {
  position: fixed;
  z-index: 45;
  inset-inline-end: 1rem;
  inset-block-end: max(1rem, env(safe-area-inset-bottom));
  width: min(29rem, calc(100% - 2rem));
  max-height: calc(100dvh - 2rem);
  overflow: auto;
  padding: 1.125rem;
  border: 1px solid #bfd1e4;
  border-radius: .9rem;
  background: #fff;
  box-shadow: 0 8px 30px #153b6729;
  color: #1e364d;
  font-size: .875rem;
  line-height: 1.55;
}
.cookie-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  margin-bottom: .4rem;
}
.cookie-heading button {
  width: 32px;
  height: 32px;
  border-radius: .4rem;
  background: #eef5fd;
  font-size: 1.25rem;
}
.cookie-notice h2 {
  font-size: 1.05rem;
  font-weight: 700;
}
.cookie-notice a {
  display: inline-block;
  margin-top: .4rem;
  color: #165ca7;
  text-decoration: underline;
}
.cookie-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .55rem;
  margin-top: .85rem;
}
.cookie-actions button {
  min-height: 44px;
  padding: .4rem;
  border: 1px solid #8aa8c8;
  border-radius: .5rem;
  background: #eef5fd;
  color: #154578;
  font-weight: 650;
}
.cookie-actions button:hover { background: #dbeafa; }
.cookie-actions button:disabled { opacity: .6; }
.cookie-error {
  margin-top: .5rem;
  color: #a32424;
}
@media (max-width: 800px) {
  :global(body:has(.practice-with-navigation)) .cookie-notice {
    inset-block-end: calc(5rem + env(safe-area-inset-bottom));
    max-height: calc(100dvh - 6rem - env(safe-area-inset-bottom));
  }
}
</style>
