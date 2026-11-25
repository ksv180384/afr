<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import AfrCloseBtn from '@/App/Components/Form/AfrCloseBtn.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  width: { type: String, default: null },
  center: { type: Boolean, default: true },
  fullScreen: { type: Boolean, default: false },
  classStyle: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'open', 'opened', 'close', 'closed']);

const mounted = ref(false);
const dialog = ref(null);
const titleId = useId();
let opener = null;
let pressedBackdrop = false;

onMounted(() => {
  mounted.value = true;
});

watch([dialog, () => props.modelValue], ([element, visible]) => {
  if (!element || element.open === visible) return;

  if (!visible) {
    element.close();
    return;
  }

  opener = document.activeElement;
  pressedBackdrop = false;
  element.showModal();
  emit('open');
  nextTick(() => {
    if (element.open) emit('opened');
  });
}, { flush: 'post' });

const close = () => dialog.value?.close();

const onClose = () => {
  if (dialog.value?.open) return;

  opener?.focus({ preventScroll: true });
  opener = null;
  if (props.modelValue) emit('update:modelValue', false);
  emit('close');
  emit('closed');
};

const isBackdrop = (event) => {
  if (event.target !== dialog.value) return false;

  const { left, top, right, bottom } = dialog.value.getBoundingClientRect();
  return event.clientX < left || event.clientX > right || event.clientY < top || event.clientY > bottom;
};

onBeforeUnmount(() => {
  if (dialog.value?.open) close();
});
</script>

<template>
  <Teleport v-if="mounted" to="body">
    <dialog
      ref="dialog"
      class="afr-dialog mobile-dialog-sheet"
      :class="[classStyle, { 'afr-dialog--top': !center, 'afr-dialog--full-screen': fullScreen }]"
      :style="{ '--dialog-width': width || '50rem' }"
      :aria-labelledby="$slots.header ? titleId : undefined"
      :aria-label="$slots.header ? undefined : 'Диалог'"
      v-bind="$attrs"
      @cancel.prevent="close"
      @close="onClose"
      @pointerdown="pressedBackdrop = isBackdrop($event)"
      @click.self="pressedBackdrop && isBackdrop($event) && close()"
    >
      <template v-if="modelValue">
        <div class="dialog-header">
          <div v-if="$slots.header" :id="titleId" class="dialog-title">
            <slot name="header" />
          </div>
          <afr-close-btn class="dialog-close" size="large" @click="close" />
        </div>
        <div class="dialog-content">
          <slot />
          <div v-if="$slots.footer" class="dialog-footer">
            <slot name="footer" />
          </div>
        </div>
      </template>
    </dialog>
  </Teleport>
</template>

<style scoped>
:global(html:has(.afr-dialog[open])) {
  overflow: hidden;
  scrollbar-gutter: stable;
}

.afr-dialog {
  --dialog-radius: .875rem;
  --dialog-gutter: clamp(1rem, 5vw, 1.5rem);
  --dialog-padding-start: max(var(--dialog-gutter), env(safe-area-inset-left, 0px));
  --dialog-padding-end: max(var(--dialog-gutter), env(safe-area-inset-right, 0px));
  @apply fixed border border-sky-100 bg-white p-0 text-slate-700 shadow-xl;
}

.afr-dialog::backdrop {
  @apply bg-slate-900/40;
}

.afr-dialog.afr-dialog--top {
  margin-block-start: 0;
}

.afr-dialog.afr-dialog--full-screen {
  inset: 0;
  width: 100%;
  height: 100dvh;
  max-width: none;
  max-height: none;
  margin: 0;
  border-radius: 0;
}

.dialog-header {
  @apply grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 border-b border-sky-100 bg-sky-50 py-3;
  padding-inline: var(--dialog-padding-start) var(--dialog-padding-end);
}

.dialog-title {
  @apply min-w-0 break-words text-lg font-bold leading-snug text-sky-950;
}

.afr-dialog .dialog-close {
  @apply col-start-2 row-start-1 rounded-lg text-sky-700 hover:bg-sky-100 hover:text-sky-900
    focus-visible:-outline-offset-2;
}

.dialog-content {
  padding-inline: var(--dialog-padding-start) var(--dialog-padding-end);
  padding-block: 1.25rem calc(1rem + env(safe-area-inset-bottom, 0px));
}

.dialog-footer {
  @apply mt-4 border-t border-sky-100 pt-3;
}
</style>
