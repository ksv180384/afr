<script setup>
import { computed, useSlots } from 'vue';

import AfrInput from '@/App/Components/Form/AfrInput.vue';

const model = defineModel();
const props = defineProps({
  nativeType: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
});

const slots = useSlots();
const appendSlot = slots.append ? slots.append()[0] : null;
const prependSlot = computed(() => slots.prepend ? slots.prepend() : null);

const prependTitle = computed(() => slots.prepend?.()?.find(item => typeof item.children === 'string')?.children || '');

const mixedInputTitle = computed(() => `${prependTitle?.value?.trim()}${props.placeholder.trim()}`);
const appendTitle = computed(() => !appendSlot?.props?.placeholder && appendSlot?.type?.__name === 'AfrSelect' ? `${mixedInputTitle.value}` : null);

if(appendTitle.value){
  appendSlot.props.placeholder = appendTitle.value;
}

const hasNonSvgChildren = computed(() => {
  // Если потомки иконка или строка
  return prependSlot.value.some(node => {
    return (node.props?.icon || typeof node.children === 'string')
  });
});
</script>

<template>
<div class="afr-mixed-input" :class="[{ 'is-prepend': slots.prepend }, { 'is-append': slots.append }]" :title="mixedInputTitle">
  <div v-if="slots.prepend" class="afr-mixed-prepend" :title="prependTitle">
    <div class="max-w-full truncate" :class="{ 'afr-mixed-prepend-not-children': hasNonSvgChildren }"><slot name="prepend"/></div>
  </div>
  <div class="main afr-mixed-main">
    <afr-input
      v-model="model"
      class="w-full"
      :native-type="nativeType"
      :placeholder="placeholder"
      :readonly="readonly"
    />
  </div>
  <div v-if="appendSlot" class="afr-mixed-append">
    <slot name="append"/>
<!--    <component :is="appendSlot"/>-->
  </div>
</div>
</template>

<style scoped>
.afr-mixed-input{
  @apply flex max-w-full bg-white;
}

.main{
  @apply flex-1;
}

.afr-mixed-prepend,
.afr-mixed-main,
.afr-mixed-append {
  /*@apply flex items-center text-nowrap truncate;*/
  @apply flex items-center;
}

.afr-mixed-prepend{
  @apply overflow-hidden;
}

:deep(.afr-mixed-prepend>div>svg){
  @apply !inline-block;
}

/*
.afr-mixed-prepend>div:not(:has(*)) {
  @apply border border-gray-300 rounded px-3 table-cell h-full py-1 min-w-[60px];
}
*/

/*
.is-prepend .afr-mixed-prepend>div:not(:has(*)) {
  @apply border-r-0 rounded-r-none;
}
*/

.afr-mixed-prepend-not-children {
  @apply border border-gray-300 rounded px-3 table-cell h-full py-1 min-w-[20px];
  padding-top: 2px;
  padding-bottom: 2px;
}

.is-prepend .afr-mixed-prepend-not-children {
  @apply border-r-0 rounded-r-none;
}

.afr-mixed-main:not(:has(*)) {
  @apply border border-gray-300 rounded px-3;
}

.afr-mixed-append:not(:has(*)) {
  @apply border border-gray-300 rounded px-3;
}

.is-append .afr-mixed-append:not(:has(*)) {
  @apply border-l-0 rounded-l-none;
}

.afr-mixed-main{
  @apply flex-grow;
}

.afr-mixed-main .afr-input{
  @apply flex-grow;
}

.append{

}
</style>
