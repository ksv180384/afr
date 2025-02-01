<script setup>
import { computed, ref, nextTick } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  size: {
    type: String,
    default: 'default',
    validator(value) {
      return ['large', 'default', 'small'].includes(value);
    },
    values: ['large', 'default', 'small'],
  },
  nativeType: {
    type: String,
    default: 'text',
    validator(value) {
      return ['text', 'number', 'password'].includes(value);
    }
  },
  label: { type: String, default: null },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  autocomplete: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
});
const emits = defineEmits(['update:modelValue', 'input', 'change']);

const refInput = ref(null);

// const value = ref(props.modelValue);
const classSize = computed(() => `size-${props.size}`);

const onInput = (e) => {
  emits('update:modelValue', e.target.value);
  emits('input', e.target.value);
}

const onChange = (e) => {
  emits('change', e.target.value);
}

const getCursorPosition = () => {
  return {
    start: refInput.value.selectionStart,
    end: refInput.value.selectionEnd
  }
}

const focus = (position = null) => {
  refInput.value.focus();
  if(position){
    nextTick(() => {
      refInput.value.selectionStart = position;
      refInput.value.selectionEnd = position;
    });
  }
}

defineExpose({
  getCursorPosition,
  focus
});
</script>

<template>
  <div class="afr-input-label">
    <div class="afr-input-section">
      <input
        ref="refInput"
        :class="{ 'on-text': modelValue.length > 0 }"
        :value="modelValue"
        :type="nativeType"
        :placeholder="placeholder"
        :required="required"
        :autocomplete="autocomplete"
        :readonly="readonly"
        @input="onInput"
        @change="onChange"
      />
      <span :data-placeholder="label"></span>
    </div>
  </div>
</template>

<style scoped>
.afr-input-label{

}

.afr-input-section{
  @apply relative text-left border-b-2 border-b-sky-50;
}

.afr-input-section > input{
  @apply w-full text-sky-600 bg-transparent p-2 outline-none box-border;
}

.afr-input-section span:before{
  @apply absolute top-1/2 transform -translate-y-1/2 left-5 pointer-events-none text-sm;
  transition: .3s;
}

.afr-input-section span:before{
  content: attr(data-placeholder);
  @apply text-sky-800 text-nowrap;
}

.afr-input-section > input.on-text + span:before,
.afr-input-section > input:focus + span:before{
  @apply left-[4px] -top-[4px] text-xs;
}

.afr-input-section > input:invalid + span:before{
  @apply text-sm top-1/2;
}

.afr-input-section > span::after{
  content: ' ';
  @apply absolute w-0 h-[2px] bg-sky-800 left-1/2 -bottom-[2px] transform -translate-x-1/2 opacity-0;
  transition: .3s;
}

.afr-input-section > input:focus + span::after{
  @apply w-full opacity-100;
}

.size-large{
  @apply text-xl;
}
.size-default{
  @apply text-base;
}
.size-small{
  @apply text-sm;
}
</style>
