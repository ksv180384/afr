<script setup>
import { ref, computed, useSlots, reactive, nextTick, onMounted, onBeforeUnmount, getCurrentInstance, watch } from 'vue';
import useClickOutside from '@/Composables/useClickOutside.js';
import { Icon } from '@iconify/vue';
import { selectOptions, filterSelectOptions } from './selectOptions.js';

const model = defineModel();
const props = defineProps({
  placeholder: { type:String, default:'' },
  loading: { type:Boolean, default:false },
  native: { type:Boolean, default:false },
  searchable: { type:Boolean, default:false },
  searchPlaceholder: { type:String, default:'Поиск по списку…' },
  disabled: { type:Boolean, default:false },
  id: { type:String, default:null },
  name: { type:String, default:null },
  ariaLabel: { type:String, default:null },
});
const emits = defineEmits(['change']);
const slots = useSlots();
const refOptionsList = ref(null);
const refActiveItem = ref(null);
const isShowOptionsList = ref(false);
const activeItemBounding = reactive({min_width:'10px',left:'10px'});
const query = ref('');
const activeIndex = ref(-1);
const menuStyle = ref({});
const instanceId = 'afr-select-' + getCurrentInstance().uid;
const listId = computed(() => (props.id || instanceId) + '-options');
const options = computed(() => selectOptions(slots.default?.() ?? []));
const filteredOptions = computed(() => filterSelectOptions(options.value,query.value));
const activeItem = computed(() => options.value.find(option => String(option.value) === String(model.value))?.label ?? '');
const blocked = computed(() => props.disabled || props.loading);
const title = computed(() => props.placeholder ? `${props.placeholder} - ${activeItem.value}` : activeItem.value);
const arrowClass = computed(() => isShowOptionsList.value ? 'transform rotate-180' : '');

const positionSearchMenu = () => {
  if (!isShowOptionsList.value || !props.searchable || !refActiveItem.value) return;
  const rect = refActiveItem.value.getBoundingClientRect();
  const below = window.innerHeight - rect.bottom;
  const above = rect.top;
  const upwards = below < 180 && above > below;
  const width = Math.min(rect.width,window.innerWidth - 16);
  menuStyle.value = {position:'fixed',width:width+'px',left:Math.max(8,Math.min(rect.left,window.innerWidth-width-8))+'px',
    top:upwards ? 'auto' : rect.bottom+4+'px',bottom:upwards ? window.innerHeight-rect.top+4+'px' : 'auto',
    maxHeight:Math.max(80,Math.min(280,(upwards ? above : below)-12))+'px'};
};
const closeSearch = () => { isShowOptionsList.value=false; query.value=''; activeIndex.value=-1; };
const scrollToActive = () => nextTick(() => refOptionsList.value?.querySelector('[data-active="true"]')?.scrollIntoView({block:'nearest'}));
const openSearch = async () => {
  if (blocked.value || isShowOptionsList.value) return;
  query.value=''; isShowOptionsList.value=true;
  activeIndex.value=options.value.findIndex(option => String(option.value)===String(model.value) && !option.disabled);
  await nextTick(); positionSearchMenu(); scrollToActive();
};
const search = event => {
  query.value=event.target.value;
  activeIndex.value=filteredOptions.value.findIndex(option => !option.disabled);
  scrollToActive();
};
const moveSearch = async direction => {
  if (!isShowOptionsList.value) { await openSearch(); return; }
  const count=filteredOptions.value.length;
  const start=activeIndex.value<0 ? (direction>0 ? -1 : 0) : activeIndex.value;
  for (let step=1; step<=count; step++) {
    const index=(start+direction*step+count)%count;
    if (!filteredOptions.value[index].disabled) { activeIndex.value=index; scrollToActive(); break; }
  }
};
const chooseSearch = option => {
  if (!option || option.disabled || blocked.value) return;
  if (String(model.value)!==String(option.value)) { model.value=option.value; emits('change',option.value); }
  closeSearch();
};
const enterSearch = () => isShowOptionsList.value ? chooseSearch(filteredOptions.value[activeIndex.value]) : openSearch();
watch(blocked,value => { if (value) closeSearch(); });
onMounted(() => {
  if (props.searchable) { window.addEventListener('resize',positionSearchMenu); window.addEventListener('scroll',positionSearchMenu,true); }
});
onBeforeUnmount(() => { window.removeEventListener('resize',positionSearchMenu); window.removeEventListener('scroll',positionSearchMenu,true); });

const toggleOptionsList = () => {
  if (blocked.value) return;
  isShowOptionsList.value=!isShowOptionsList.value;
  const rect=refActiveItem.value.getBoundingClientRect();
  const parent=refActiveItem.value.offsetParent;
  activeItemBounding.min_width=rect.width+8+'px';
  activeItemBounding.left=rect.left-(parent?.getBoundingClientRect().left ?? 0)-4+'px';
};
const clickSelectOption = event => {
  const option=event.target.closest('[data-option]');
  if (!option || option.disabled || blocked.value) return;
  if (String(model.value)!==String(option.value)) { model.value=option.value; emits('change',model.value); }
  isShowOptionsList.value=false;
};
if (!props.native || props.searchable) useClickOutside(refOptionsList,() => props.searchable ? closeSearch() : (isShowOptionsList.value=false),refActiveItem);
</script>
<template>
  <div class="afr-select max-w-full" :class="{ 'is-native':native && !searchable, 'is-searchable':searchable, 'is-disabled':blocked }" :title="title" :id="native || searchable ? undefined : id">
    <div v-if="searchable" ref="refActiveItem" class="afr-search-control">
      <input :id="id || instanceId" role="combobox" :aria-label="ariaLabel" aria-autocomplete="list" :aria-expanded="isShowOptionsList" :aria-controls="listId" :aria-activedescendant="isShowOptionsList && activeIndex>=0 ? listId+'-'+activeIndex : undefined"
        :value="isShowOptionsList ? query : activeItem" :placeholder="isShowOptionsList ? searchPlaceholder : placeholder" :disabled="blocked" autocomplete="off" :spellcheck="false"
        @focus="openSearch" @click="openSearch" @input="search" @blur="closeSearch" @keydown.down.prevent="moveSearch(1)" @keydown.up.prevent="moveSearch(-1)" @keydown.enter.prevent="enterSearch" @keydown.esc.stop.prevent="closeSearch" />
      <input v-if="name" type="hidden" :name="name" :value="model" :disabled="blocked" />
      <svg class="afr-native-arrow" :class="{ 'rotate-180':isShowOptionsList }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 8 4 4 4-4" /></svg>
      <Teleport to="body">
        <div v-if="isShowOptionsList" ref="refOptionsList" class="afr-search-options" :style="menuStyle">
          <ul :id="listId" role="listbox" :aria-label="ariaLabel || placeholder || 'Варианты'">
            <li v-for="(option,index) in filteredOptions" :id="listId+'-'+index" :key="String(option.value)" role="option" :aria-selected="String(model)===String(option.value)" :aria-disabled="option.disabled" :data-active="index===activeIndex"
              @mousedown.prevent @mouseenter="activeIndex=index" @click="chooseSearch(option)">{{ option.label }}</li>
          </ul>
          <p v-if="!filteredOptions.length" class="afr-select-empty" role="status">Ничего не найдено</p>
        </div>
      </Teleport>
    </div>
    <div v-else-if="native" class="afr-native-select">
      <select :id="id" :name="name" :aria-label="ariaLabel" :disabled="blocked" :value="model" @change="model=$event.target.value; emits('change',model)"><slot /></select>
      <svg class="afr-native-arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 8 4 4 4-4" /></svg>
    </div>
    <div v-else :class="{flex:slots.prepend}">
      <div v-if="slots.prepend" class="prepend-block"><div class="max-w-full truncate"><slot name="prepend" /></div></div>
      <div ref="refActiveItem" class="afr-active-item" @click="toggleOptionsList">
        <div v-if="activeItem" class="max-w-full truncate">{{ activeItem }}</div>
        <div v-else-if="placeholder" class="text-gray-400 truncate">{{ placeholder }}</div><template v-else>&nbsp;</template>
        <div class="w-[20px] ms-1"><Icon v-if="!loading" icon="iconamoon:arrow-down-2-bold" width="16px" height="16px" class="transition-transform duration-200" :class="arrowClass" /><Icon v-else icon="svg-spinners:6-dots-rotate" width="16px" height="16px" /></div>
      </div>
    </div>
    <transition v-if="!native && !searchable" name="slide-fade">
      <div v-if="isShowOptionsList" ref="refOptionsList" class="afr-options-list" :style="`min-width:${activeItemBounding.min_width};left:${activeItemBounding.left};`" @click="clickSelectOption">
        <template v-for="option in slots.default?.() ?? []"><component :is="option" /></template>
      </div>
    </transition>
  </div>
</template>
<style scoped>
.afr-select { @apply bg-white border border-gray-300 rounded block; }
.afr-native-select,.afr-search-control { @apply relative; }
.afr-native-select select,.afr-search-control>input[role="combobox"] { @apply block w-full min-w-0 appearance-none border-0 rounded bg-transparent pl-2 pr-7 py-1 text-base; background-image:none; text-overflow:ellipsis; }
.afr-native-select select:focus,.afr-search-control>input[role="combobox"]:focus { outline:none; box-shadow:none; }
.afr-native-arrow { @apply absolute right-2 top-1/2 w-4 h-4 -translate-y-1/2 pointer-events-none; }
.afr-select.is-native:focus-within,.afr-select.is-searchable:focus-within { @apply border-blue-400 ring-1 ring-blue-200; }
.afr-select.is-disabled { @apply opacity-70; }
.afr-search-options { @apply rounded border border-gray-300 bg-white shadow-lg overflow-y-auto; z-index:50; overscroll-behavior:contain; }
.afr-search-options ul { margin:0; padding:4px; list-style:none; }
.afr-search-options li { padding:6px 8px; border-radius:3px; font-size:14px; line-height:1.45; cursor:pointer; overflow-wrap:anywhere; }
.afr-search-options li[data-active="true"] { @apply bg-blue-50; }
.afr-search-options li[aria-selected="true"] { @apply text-blue-700 bg-blue-100; }
.afr-search-options li[aria-disabled="true"] { @apply text-gray-400 cursor-not-allowed; }
.afr-select-empty { padding:10px 12px; font-size:14px; color:#64748b; }
.afr-options-list { @apply absolute bg-white drop-shadow-lg mt-1 rounded transition-transform transform duration-300; z-index:2; min-width:calc(100% + 4px); margin-left:-2px; }
.afr-active-item { @apply flex flex-1 justify-between items-center px-2 py-1 cursor-pointer overflow-hidden; }
.is-prepend .afr-mixed-prepend .afr-select { @apply rounded-r-none border-r-0; }
.is-prepend .afr-mixed-main .afr-select { @apply rounded-l-none; }
.is-append .afr-mixed-main .afr-select { @apply rounded-r-none; }
.is-append .afr-mixed-append .afr-select { @apply rounded-l-none border-l-0; }
.prepend-block { @apply flex items-center px-2 border-r text-nowrap max-w-full truncate min-w-[60px]; }
.slide-fade-enter-active,.slide-fade-leave-active { transition:opacity .3s ease,transform .3s ease; }
.slide-fade-enter-from,.slide-fade-leave-to { opacity:0; transform:translateY(-20px); }
</style>
