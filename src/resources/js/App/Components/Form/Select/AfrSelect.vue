<script setup>
import { ref, computed, useSlots, reactive } from 'vue';
import { useElementBounding } from '@vueuse/core';
import useClickOutside from '@/Composables/useClickOutside.js';
import { Icon } from '@iconify/vue';

const model = defineModel();
const props = defineProps({
  placeholder: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});
const emits = defineEmits(['change']);

// const { placeholder } = defineProps();
const slots = useSlots();
const activeItemBounding = reactive({
  min_width: '10px',
  left: '10px',
});

const refOptionsList = ref(null);
const refActiveItem = ref(null);

const activeItem = computed(() => {
  const sl = slots.default?.()?.filter(item => item.children === null);
  const child = slots.default?.()?.find(item => item.children !== null).children || [];
  const slotsList = [...sl, ...child];
  return slotsList.find(item => String(item.props?.value) === String(model.value))?.props?.label || '';
});
const arrowClass = computed(() => {
  return isShowOptionsList.value ? 'transform rotate-180' : '';
});

const title = computed(() => props.placeholder ? `${props.placeholder} - ${activeItem.value}` : activeItem.value)
const isShowOptionsList = ref(false);

const toggleOptionsList = () => {
  isShowOptionsList.value = !isShowOptionsList.value;

  // const { x, y, top, right, bottom, left, width, height } = useElementBounding(refActiveItem);
  const activeItem = useElementBounding(refActiveItem);

  // Получаем родительский элемент с position: relative
  const parentElement = refActiveItem.value?.offsetParent;
  let parentOffset = 0; // если у родительского элемента left позиция отлична от нуля, то корректируем позицию списка

  if(parentElement){
    const parentRect = parentElement.getBoundingClientRect();
    parentOffset = parentRect.left;
  }

  activeItemBounding.min_width = `${(activeItem.right.value - activeItem.left.value) + 8}px`;
  activeItemBounding.left = `${activeItem.x.value - parentOffset - 4}px`;
}

const clickSelectOption = (e) => {
  if (e.target.closest('[data-option]')) {
    selectOption(e.target.closest('[data-option]'));
  }
}

const selectOption = (selectOptionValue) => {
  const currentVal = model.value?.toString();
  const newVal = selectOptionValue.value?.toString();
  if(currentVal !== newVal){
    model.value = selectOptionValue.value;
    emits('change', model.value);
  }
  isShowOptionsList.value = false;
}


useClickOutside(
  refOptionsList,
  () => {
    isShowOptionsList.value = false;
  },
  refActiveItem
);
</script>

<template>
  <div class="afr-select max-w-full" :title="title">
    <div :class="{ 'flex': slots.prepend}">
      <div v-if="slots.prepend" class="prepend-block">
        <div class="max-w-full truncate"><slot name="prepend"/></div>
      </div>
      <div ref="refActiveItem" class="afr-active-item" @click="toggleOptionsList">
        <template v-if="activeItem">
          <div class="max-w-full truncate">{{ activeItem }}</div>
        </template>
        <template v-else-if="placeholder">
          <div class="text-gray-400 truncate">{{ placeholder }}</div>
        </template>
        <template v-else>
          &nbsp;
        </template>

        <div class="w-[20px] ms-1">
          <Icon v-if="!loading" icon="iconamoon:arrow-down-2-bold" width="16px" height="16px" class="transition-transform duration-200" :class="arrowClass" />
          <Icon v-else icon="svg-spinners:6-dots-rotate" width="16px" height="16px" class="transition-transform duration-200" />
        </div>
      </div>
    </div>
    <transition name="slide-fade">
      <div
        v-if="isShowOptionsList"
        ref="refOptionsList"
        class="afr-options-list"
        :style="`min-width: ${activeItemBounding.min_width}; left: ${activeItemBounding.left};`"
        @click="clickSelectOption"
      >
        <template v-for="option in slots.default()">
          <component :is="option"></component>
        </template>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.afr-select{
  @apply bg-white border border-gray-300 rounded block;
}

.afr-options-list{
  @apply absolute bg-white drop-shadow-lg mt-1 rounded transition-transform transform duration-300;
         z-index: 2;
  min-width: calc(100% + 4px);
  margin-left: -2px;
}

.afr-active-item{
  @apply flex flex-1 justify-between items-center px-2 py-1 cursor-pointer overflow-hidden;
}

.is-prepend .afr-mixed-prepend .afr-select{
  @apply rounded-r-none border-r-0;
}

.is-prepend .afr-mixed-main .afr-select{
  @apply rounded-l-none;
}

.is-append .afr-mixed-main .afr-select{
  @apply rounded-r-none;
}

.is-append .afr-mixed-append .afr-select{
  @apply rounded-l-none border-l-0;
}

.prepend-block{
  @apply flex items-center px-2 border-r text-nowrap max-w-full truncate min-w-[60px];
}

.slide-fade-enter-active, .slide-fade-leave-active{
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.slide-fade-enter-from, .slide-fade-leave-to{
  opacity: 0;
  transform: translateY(-20px);
}
</style>
