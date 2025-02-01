<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  size: {
    type: String,
    default: 'default',
    validator(value) {
      return ['large', 'default', 'small'].includes(value);
    }
  },
  type: {
    type: String,
    default: 'default',
    validator(value) {
      return ['default', 'success', 'primary', 'info', 'warning', 'danger'].includes(value);
    }
  },
  nativeType: {
    type: String,
    default: 'button',
    validator(value) {
      return ['submit', 'reset', 'button'].includes(value);
    }
  },
  icon: { type: String, default: null },
  plain: { type: Boolean, default: false },
  link: { type: Boolean, default: false },
  text: { type: Boolean, default: false },
  bg: { type: Boolean, default: false },
  round: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});
const emits = defineEmits(['click']);

const classSize = computed(() => `size-${props.size}`);
const classType = computed(() => `type-${props.type}`);
const classPlain = computed(() => props.plain ? 'plain' : '');
const classLink = computed(() => props.link ? 'link' : '');
const classText = computed(() => props.text ? 'text' : '');
const classBg = computed(() => props.bg ? 'bg' : '');
const classRound = computed(() => props.round ? 'round' : '');
const classCircle = computed(() => props.circle ? 'circle' : '');
const classDisabled = computed(() => props.disabled ? 'is-disabled' : '');

const onClick = () => {
  if(props.loading || props.disabled){
    return true;
  }

  emits('click');
}
</script>

<template>
  <button
    :class="[classSize, classType, classPlain, classLink, classText, classBg, classRound, classCircle, classDisabled]"
    :disabled="disabled || loading"
    :type="nativeType"
    @click="onClick"
  >
    <Icon v-if="icon && !loading" :icon="icon" />
    <Icon v-if="loading" icon="svg-spinners:6-dots-rotate" />
    <slot>button</slot>
  </button>
</template>

<style scoped>


button{
  @apply inline-flex flex-row px-2 items-center transition duration-300 cursor-pointer rounded;
}

button{
  @apply flex justify-center items-center text-nowrap;
}

button>svg{
  @apply me-2;
}

.size-large{
  @apply text-lg py-3;
}
.size-default{
  @apply text-base py-1;
}
.size-small{
  @apply text-xs py-1;
}

button.is-disabled,
button.is-disabled button{
  @apply cursor-not-allowed;
}

.type-default{
  @apply bg-white text-gray-600 border border-gray-300 hover:bg-blue-50 hover:border hover:border-blue-300 hover:text-blue-500;
}

.type-default.is-disabled{
  @apply text-gray-400 border-gray-200 hover:bg-white;
}

.type-primary{
  @apply bg-blue-300 text-gray-50 hover:bg-blue-400;
}

.type-primary.is-disabled{
  @apply bg-blue-300;
}

.type-success{
  @apply bg-lime-600 text-lime-50 hover:bg-lime-500;
}

.type-success.is-disabled{
  @apply bg-lime-500;
}

.type-info{
  @apply bg-gray-500 text-gray-50 hover:bg-gray-400;
}

.type-info.is-disabled{
  @apply bg-gray-400;
}

.type-warning{
  @apply bg-amber-500 text-amber-50 hover:bg-amber-400;
}

.type-warning.is-disabled{
  @apply bg-amber-300;
}

.type-danger{
  @apply bg-red-500 text-red-50 hover:bg-red-400;
}

.type-danger.is-disabled{
  @apply bg-red-300;
}

/* --- plain ---*/

button.plain.type-default{
  @apply text-gray-600 border border-gray-300 bg-white hover:border-blue-400 hover:text-blue-400;
}

button.plain.type-default.is-disabled{
  @apply text-gray-400 border-gray-200 hover:bg-white;
}

button.plain.type-primary{
  @apply border text-blue-400 border-blue-400 bg-blue-50 hover:border-blue-400 hover:text-gray-50 hover:bg-blue-400;
}

button.plain.type-primary.is-disabled{
  @apply text-blue-300 border-blue-200 bg-blue-50;
}

button.plain.type-success{
  @apply text-lime-600 border border-lime-500 bg-lime-50 hover:border-lime-600 hover:text-lime-50 hover:bg-lime-600;
}

button.plain.type-success.is-disabled{
  @apply text-lime-500 border-lime-400 bg-lime-50;
}

button.plain.type-info{
  @apply text-gray-500 border border-gray-500 bg-gray-50 hover:bg-gray-500 hover:text-gray-50;
}

button.plain.type-info.is-disabled{
  @apply text-gray-400 border-gray-300 bg-gray-50;
}

button.plain.type-warning{
  @apply text-amber-500 border border-amber-500 bg-amber-50 hover:bg-amber-500 hover:text-amber-50;
}

button.plain.type-warning.is-disabled{
  @apply text-amber-400 border-amber-200 bg-amber-50;
}

button.plain.type-danger{
  @apply text-red-500 border border-red-500 bg-red-50 hover:bg-red-500 hover:text-red-50;
}

button.plain.type-danger.is-disabled{
  @apply text-red-300 border-red-200 bg-red-50;
}

/* --- link ---*/

button.link.type-default{
  @apply text-gray-600 border border-transparent bg-transparent hover:text-blue-500;
}

button.link.type-default.is-disabled{
  @apply text-gray-400;
}

button.link.type-primary{
  @apply text-blue-400 border border-transparent bg-transparent hover:text-blue-400;
}

button.link.type-primary.is-disabled{
  @apply text-blue-300;
}

button.link.type-success{
  @apply text-lime-600 border border-transparent bg-transparent hover:text-lime-500;
}

button.link.type-success.is-disabled{
  @apply text-lime-400;
}

button.link.type-info{
  @apply text-gray-600 border border-transparent bg-transparent hover:text-gray-500;
}

button.link.type-info.is-disabled{
  @apply text-gray-400;
}

button.link.type-warning{
  @apply text-amber-500 border border-transparent bg-transparent hover:text-amber-400;
}

button.link.type-warning.is-disabled{
  @apply text-amber-300;
}

button.link.type-danger{
  @apply text-red-500 border border-transparent bg-transparent hover:text-red-400;
}

button.link.type-danger.is-disabled{
  @apply text-red-300;
}

/* --- text ---*/

button.text.type-default{
  @apply text-gray-700 border border-transparent bg-transparent hover:bg-blue-50;
}

button.text.bg.type-default{
  @apply border border-blue-50 bg-blue-50;
}

button.text.is-disabled.type-default{
  @apply text-gray-400 border border-transparent bg-transparent;
}

button.text.type-primary{
  @apply text-blue-400 border border-transparent bg-transparent hover:bg-blue-50;
}

button.text.bg.type-primary{
  @apply border border-blue-50 bg-blue-50;
}

button.text.is-disabled.type-primary{
  @apply text-blue-300;
}

button.text.type-success{
  @apply text-lime-600 border border-transparent bg-transparent hover:bg-blue-50;
}

button.text.bg.type-success{
  @apply border border-blue-50 bg-blue-50;
}

button.text.is-disabled.type-success{
  @apply text-lime-400;
}

button.text.type-info{
  @apply text-gray-500 border border-transparent bg-transparent hover:bg-blue-50;
}

button.text.bg.type-info{
  @apply border border-blue-50 bg-blue-50;
}

button.text.is-disabled.type-info{
  @apply text-gray-300;
}

button.text.type-warning{
  @apply text-amber-600 border border-transparent bg-transparent hover:bg-blue-50;
}

button.text.bg.type-warning{
  @apply border border-blue-50 bg-blue-50;
}

button.text.is-disabled.type-warning{
  @apply text-amber-300;
}

button.text.type-danger{
  @apply text-red-500 border border-transparent bg-transparent hover:bg-blue-50;
}

button.text.bg.type-danger{
  @apply border border-blue-50 bg-blue-50;
}

button.text.is-disabled.type-danger{
  @apply text-red-300;
}

/* --- round ---*/

button.round{
  @apply rounded-full;
}
</style>
