<script setup>
import { onBeforeUnmount, unref } from 'vue';

const props = defineProps({ notifications: { type: Object, required: true } });
const titles = { success: 'Готово', error: 'Не удалось выполнить действие', warning: 'Обратите внимание', info: 'Практика' };
const paths = { success: 'm6 12 4 4 8-8', error: 'm8 8 8 8M16 8l-8 8', warning: 'M12 7v6m0 4h.01', info: 'M12 11v6m0-10h.01' };
const actionsFor = item => unref(item.actions) ?? [];
const resume = (event, id) => {
  const element = event.currentTarget;
  queueMicrotask(() => { if (!element.matches(':hover, :focus-within')) props.notifications.resume(id); });
};
onBeforeUnmount(() => props.notifications.clear());
</script>

<template>
  <section class="practice-toasts" aria-label="Уведомления практики">
    <article v-for="item in notifications.items" :key="item.id" class="practice-toast" :class="'practice-toast--' + item.type"
      :role="item.type === 'error' ? 'alert' : 'status'" aria-atomic="true"
      @mouseenter="notifications.pause(item.id)" @mouseleave="resume($event, item.id)"
      @focusin="notifications.pause(item.id)" @focusout="resume($event, item.id)">
      <span class="practice-toast-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path :d="paths[item.type]" /></svg>
      </span>
      <div class="practice-toast-content">
        <p class="practice-toast-title">{{ titles[item.type] }}</p>
        <p class="practice-toast-message">{{ item.message }}</p>
        <div v-if="actionsFor(item).length" class="practice-toast-actions">
          <button v-for="action in actionsFor(item)" :key="action.label" type="button" :disabled="action.disabled" @click="action.run">{{ action.label }}</button>
        </div>
      </div>
      <button type="button" class="practice-toast-close" aria-label="Закрыть уведомление" @click="notifications.dismiss(item.id)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
      </button>
    </article>
  </section>
</template>

<style scoped>
.practice-toasts {
  position: fixed;
  right: max(20px, env(safe-area-inset-right));
  bottom: max(20px, env(safe-area-inset-bottom));
  z-index: 1100;
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: min(400px, calc(100vw - 40px));
  max-height: calc(100dvh - 90px);
  overflow-x: hidden;
  overflow-y: auto;
  pointer-events: none;
  padding: 3px;
}
.practice-toast {
  --toast-accent: #1967b2;
  display: flex;
  align-items: flex-start;
  gap: 11px;
  flex-shrink: 0;
  padding: 14px 10px 14px 14px;
  border: 1px solid #b9d7f2;
  border-left: 3px solid var(--toast-accent);
  border-radius: 4px;
  background: linear-gradient(120deg, #fff, #eff7ff);
  color: #17364d;
  box-shadow: 0 6px 24px #174c8324, 0 1px 4px #174c8314;
  pointer-events: auto;
  animation: practice-toast-appear .3s cubic-bezier(.16, 1, .3, 1) both;
}
.practice-toast--success { --toast-accent: #208252; }
.practice-toast--warning { --toast-accent: #a86b17; }
.practice-toast--error { --toast-accent: #c14d54; }
.practice-toast-icon { display: flex; flex-shrink: 0; padding-top: 1px; color: var(--toast-accent); }
.practice-toast-icon svg { width: 23px; height: 23px; }
.practice-toast-content { flex: 1; min-width: 0; }
.practice-toast-title { margin: 0 0 4px; color: #123e75; font-size: 14px; font-weight: 700; line-height: 1.4; }
.practice-toast-message { margin: 0; font-size: 13px; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; }
.practice-toast-close { display: grid; place-items: center; flex-shrink: 0; width: 32px; height: 32px; margin-top: -5px; border-radius: 3px; color: #567494; }
.practice-toast-close svg { width: 18px; height: 18px; }
.practice-toast-close:hover { background: #dbeafe; color: #123e75; }
.practice-toast-close:focus-visible, .practice-toast-actions button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.practice-toast-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.practice-toast-actions button { min-height: 36px; padding: 6px 10px; border: 1px solid #b9d7f2; border-radius: 3px; background: #dbeafe; color: #123e75; font-size: 12px; }
.practice-toast-actions button:hover { background: #c7e0fc; }
.practice-toast-actions button:disabled { opacity: .6; cursor: wait; }
@keyframes practice-toast-appear {
  from { opacity: 0; transform: translate3d(20px, 0, 0); }
  to { opacity: 1; transform: translate3d(0, 0, 0); }
}
@media (max-width: 600px) {
  .practice-toasts { left: max(10px, env(safe-area-inset-left)); right: max(10px, env(safe-area-inset-right)); bottom: max(10px, env(safe-area-inset-bottom)); width: auto; }
  .practice-toast { gap: 9px; padding: 12px 6px 12px 12px; }
  .practice-toast-close { width: 44px; height: 44px; margin-top: -8px; margin-bottom: -8px; }
}
@media (prefers-reduced-motion: reduce) { .practice-toast { animation: none; } }
</style>
