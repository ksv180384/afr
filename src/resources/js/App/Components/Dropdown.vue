<script setup>
import { computed, ref, useId } from 'vue';
import { vOnClickOutside } from '@vueuse/components';

const props = defineProps({
  align: {
    type: String,
    default: 'right',
  },
  width: {
    type: String,
    default: '48',
  },
  contentClasses: {
    type: String,
    default: '',
  },
  triggerLabel: String,
});

const panelId = useId();
const trigger = ref(null);
const open = ref(false);

const widthClass = computed(() => {
  return {
    48: 'w-48',
    64: 'w-64',
  }[props.width.toString()];
});

const alignmentClasses = computed(() => {
  if (props.align === 'left') {
    return 'ltr:origin-top-left rtl:origin-top-right start-0';
  } else if (props.align === 'right') {
    return 'ltr:origin-top-right rtl:origin-top-left end-0';
  } else {
    return 'origin-top';
  }
});

const close = () => {
  open.value = false;
};

const closeAndFocusTrigger = () => {
  close();
  trigger.value?.focus({ preventScroll: true });
};

const toggle = () => {
  open.value = !open.value;
  if (open.value) trigger.value?.focus({ preventScroll: true });
};

const onEscape = (event) => {
  if (!open.value) return;

  event.stopPropagation();
  closeAndFocusTrigger();
};

const onFocusOut = ({ currentTarget, relatedTarget }) => {
  if (relatedTarget && !currentTarget.contains(relatedTarget)) close();
};

const onSelect = (event) => {
  if (event.target.closest('a[href], button')) closeAndFocusTrigger();
};
</script>

<template>
  <div v-on-click-outside="close" class="relative" @keydown.esc="onEscape" @focusout="onFocusOut">
    <button
      ref="trigger"
      type="button"
      class="dropdown-trigger"
      :aria-label="triggerLabel"
      :aria-expanded="open"
      :aria-controls="panelId"
      @click="toggle"
    >
      <slot name="trigger" :open="open" />
    </button>

    <Transition
      enter-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      leave-active-class="motion-safe:transition motion-safe:duration-100 motion-safe:ease-in"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        :id="panelId"
        v-show="open"
        class="dropdown-panel"
        :class="[widthClass, alignmentClasses, contentClasses]"
        :inert="!open"
        @click="onSelect"
      >
        <slot name="head" />
        <slot name="content" />
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.dropdown-trigger {
  @apply flex min-h-11 items-center gap-2 rounded-full px-1.5 py-1 text-slate-600
    hover:bg-white/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
    focus-visible:outline-blue-600 motion-safe:transition-colors;
}

.dropdown-trigger[aria-expanded='true'] {
  @apply bg-white text-blue-700 shadow-sm;
}

.dropdown-panel {
  @apply absolute z-50 mt-2 overflow-y-auto overscroll-contain rounded-2xl border border-slate-200
    bg-white shadow-xl shadow-slate-900/15;
  max-height: calc(100vh - 4.75rem);
  max-height: calc(100dvh - 4.75rem);
}
</style>
