<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  gender: { type: Object, required: true },
  size: { type: Number, default: 24 },
  color: { type: String, default: '' },
});

const colorMale = computed(() => {
  return props.color ? props.color : '#0ea5e9';
});
const colorFemale = computed(() => {
  return props.color ? props.color : '#ec4899';
});
</script>

<template>
  <span
    v-if="gender && gender.id === 1"
    class="inline-block"
    title="Мужчина"
  >
    <Icon
      icon="tabler:gender-male"
      :width="size"
      :height="size"
      :color="colorMale"
    />
  </span>
  <span
    v-else-if="gender && gender.id === 2"
    class="inline-block"
    title="Женщина"
  >
    <Icon
      icon="tabler:gender-female"
      :width="size"
      :height="size"
      :color="colorFemale"
    />
  </span>
</template>

<style scoped>

</style>
