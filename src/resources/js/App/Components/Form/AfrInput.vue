<script setup>
import { ref, computed, nextTick } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  size: {
    type: String,
    default: 'default',
    validator(value) {
      return ['large', 'default', 'small'].includes(value);
    }
  },
  nativeType: {
    type: String,
    default: 'text',
    validator(value) {
      return ['text', 'number', 'password', 'date'].includes(value);
    }
  },
  label: { type: String, default: null },
  placeholder: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
});
const emits = defineEmits(['update:modelValue']);

const refInput = ref(null);
const classSize = computed(() => `size-${props.size}`);
const onInput = (e) => {
  emits('update:modelValue', e.target.value);
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
  focus,
});
</script>

<template>
  <div class="afr-input-component">
    <template v-if="label">
      <label>{{ label }}</label>
    </template>
    <div class="afr-input" :class="[classSize]">
      <input
        ref="refInput"
        :type="nativeType"
        :placeholder="placeholder"
        :value="modelValue"
        :readonly="readonly"
        @input="onInput"
      />
    </div>
  </div>
</template>

<style scoped>
.afr-input-component{

}

.afr-input-component>label{
  @apply text-sm;
}

.afr-input{
  @apply border border-gray-300 rounded overflow-clip;
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

.afr-input input{
  @apply py-1 px-3 w-full;
}

.is-prepend .afr-mixed-prepend .afr-input{
  @apply rounded-r-none border-r-0;
}

.is-prepend .afr-mixed-main .afr-input{
  @apply rounded-l-none;
}

.is-append .afr-mixed-main .afr-input{
  @apply rounded-r-none;
}

.is-append .afr-mixed-append .afr-input{
  @apply rounded-l-none border-l-0;
}
</style>
