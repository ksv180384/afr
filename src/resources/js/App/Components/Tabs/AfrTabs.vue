<script setup>
import { useSlots, computed } from 'vue';

const model = defineModel();
defineProps({
  position: { type: String, default: 'top' },
});
const slots = useSlots();
const slotsDefault = computed(() => {
  return slots.default().filter(item => item.props);
});

const changeTab = (tabName) => {
  model.value = tabName;
}
</script>

<template>
<div class="afr-tabs" :class="{ 'afr-tabs--bottom': position === 'bottom' }">
  <div v-if="position === 'top'" class="afr-tabs-header" role="group" aria-label="Разделы">
    <template v-for="tab in slotsDefault">
      <button
        type="button" class="afr-tabs-header-btn"
        :class="{ 'is-active-tab': tab.props.name === model }"
        :aria-pressed="tab.props.name === model"
        @click="changeTab(tab.props.name)"
      >
        {{ tab.props.label }}
      </button>
    </template>
  </div>
  <template v-for="afrTabPlane in slotsDefault">
    <component :is="afrTabPlane" v-if="model === afrTabPlane.props.name"></component>
  </template>
  <div v-if="position === 'bottom'" class="afr-tabs-footer" role="group" aria-label="Разделы">
    <template v-for="tab in slotsDefault">
      <button
        type="button" class="afr-tabs-header-btn"
        :class="{ 'is-active-tab': tab.props.name === model }"
        :aria-pressed="tab.props.name === model"
        @click="changeTab(tab.props.name)"
      >
        {{ tab.props.label }}
      </button>
    </template>
  </div>
</div>
</template>

<style scoped>
.afr-tabs{
  @apply h-full;
}

.afr-tabs-header{
  @apply flex rounded-t overflow-hidden;
}

.afr-tabs-header-btn{
  @apply cursor-pointer flex-1 text-center bg-blue-100 py-2 hover:bg-blue-200 text-sky-700 transition duration-300;
}

.afr-tabs-header-btn { min-height:44px; min-width:0; overflow-wrap:anywhere; }
.afr-tabs-header-btn:focus-visible { outline:3px solid #195bb2; outline-offset:-3px; }

.afr-tabs-header-btn:not(:last-child){
  @apply  border-r border-r-blue-100;
}

.is-active-tab{
  @apply bg-blue-200;
}

.afr-tabs--bottom { display:flex; flex-direction:column; }
.afr-tabs--bottom :deep(.afr-tab-plane) { flex:1; min-height:0; height:auto; }

.afr-tabs-footer{
  @apply flex;
}
</style>
