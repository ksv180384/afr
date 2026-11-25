<script setup>
import { arrow, autoUpdate, computePosition, flip, offset, shift, size } from '@floating-ui/dom';
import { ref, shallowRef, useId, watch } from 'vue';
import DailyPracticeIcon from '@/App/Components/Learning/DailyPracticeIcon.vue';

defineProps({ label: { type: String, required: true } });

const panelId = useId();
const trigger = ref(null);
const panel = ref(null);
const pointer = ref(null);
const open = ref(false);
const position = shallowRef(null);

watch(open, (visible, _, onCleanup) => {
  position.value = null;
  if (!visible) return;

  const anchor = trigger.value;
  const floating = panel.value;
  const viewport = { padding: 12 };
  let active = true;
  const stop = autoUpdate(anchor, floating, async () => {
    const result = await computePosition(anchor, floating, {
      strategy: 'fixed',
      placement: 'top-end',
      middleware: [
        offset(7),
        flip(viewport),
        shift(viewport),
        size({ ...viewport, apply({ availableHeight }) {
          floating.style.setProperty('--help-max-height', `${Math.max(0, availableHeight - 2)}px`);
        } }),
        arrow({ element: pointer.value, padding: 12 }),
      ],
    });
    if (active) position.value = result;
  });
  onCleanup(() => {
    active = false;
    stop();
  });
}, { flush: 'post' });
</script>

<template>
  <div class="help-popover">
    <button
      ref="trigger"
      type="button"
      class="help-trigger"
      :aria-label="label"
      :aria-expanded="open"
      :aria-controls="panelId"
      :popovertarget="panelId"
    >
      <span><daily-practice-icon name="info" /></span>
    </button>
    <div
      :id="panelId"
      ref="panel"
      class="help-panel"
      popover="auto"
      :data-side="position?.placement.split('-')[0]"
      :style="position && { top: `${position.y}px`, left: `${position.x}px` }"
      @toggle.self="open = $event.newState === 'open'"
    >
      <span ref="pointer" class="help-arrow" aria-hidden="true" :style="position && { left: `${position.middlewareData.arrow.x}px` }" />
      <div class="help-content"><slot /></div>
    </div>
  </div>
</template>

<style scoped>
.help-popover { flex: 0 0 auto; }
.help-trigger {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: .5rem;
  color: #315d89;
}
.help-trigger > span {
  display: grid;
  place-items: center;
  width: 1.65rem;
  height: 1.65rem;
  border: 1px solid #d4e2f0;
  border-radius: .6rem;
  background: #f1f6fc;
}
.help-trigger:hover > span, .help-trigger[aria-expanded='true'] > span {
  border-color: #adc7e3;
  background: #e8f1fc;
}
.help-trigger:focus-visible { outline: 2px solid #155e91; outline-offset: 2px; }
.help-trigger :deep(svg) { width: 1rem; height: 1rem; }
.help-panel {
  position: fixed;
  inset: auto;
  width: min(24rem, calc(100vw - 24px));
  margin: 0;
  padding: 0;
  overflow: visible;
  border: 1px solid #cfdeed;
  border-radius: .75rem;
  background: #f7fbff;
  box-shadow: 0 12px 36px #18344c29, 0 2px 8px #18344c12;
  color: #304760;
  font: inherit;
  font-size: .875rem;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.help-panel:not([data-side]) { visibility: hidden; }
.help-content { max-height: var(--help-max-height, calc(100vh - 24px)); padding: .7rem .85rem; overflow: auto; overscroll-behavior: contain; }
.help-arrow {
  position: absolute;
  width: 9px;
  height: 9px;
  transform: rotate(45deg);
  background: inherit;
  border: solid #cfdeed;
  pointer-events: none;
}
.help-panel[data-side='top'] > .help-arrow { bottom: -5px; border-width: 0 1px 1px 0; }
.help-panel[data-side='bottom'] > .help-arrow { top: -5px; border-width: 1px 0 0 1px; }
</style>
