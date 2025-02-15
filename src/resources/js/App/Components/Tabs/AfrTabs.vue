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
<div class="afr-tabs">
  <div v-if="position === 'top'" class="afr-tabs-header">
    <template v-for="tab in slotsDefault">
      <div
        class="afr-tabs-header-btn"
        :class="{ 'is-active-tab': tab.props.name === model }"
        @click="changeTab(tab.props.name)"
      >
        {{ tab.props.label }}
      </div>
    </template>
  </div>
  <template v-for="afrTabPlane in slotsDefault">
    <component :is="afrTabPlane" v-if="model === afrTabPlane.props.name"></component>
  </template>
  <div v-if="position === 'bottom'" class="afr-tabs-footer">
    <template v-for="tab in slotsDefault">
      <div
        class="afr-tabs-header-btn"
        :class="{ 'is-active-tab': tab.props.name === model }"
        @click="changeTab(tab.props.name)"
      >
        {{ tab.props.label }}
      </div>
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

.afr-tabs-header-btn:not(:last-child){
  @apply  border-r border-r-blue-100;
}

.is-active-tab{
  @apply bg-blue-200;
}

.afr-tabs-footer{
  @apply flex -mt-[40px];
}
</style>
