<script setup>
import { useAttrs } from 'vue';
defineOptions({ inheritAttrs: false });
const attrs = useAttrs();
const textareaAttrs = () => Object.fromEntries(Object.entries(attrs).filter(([key]) => !['class', 'style'].includes(key)));
const model = defineModel();
const props = defineProps({
  label: { type: String, default: '' },
  cols: { type: String, default: '' },
  rows: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});
</script>

<template>
<div class="afr-textarea" :class="attrs.class" :style="attrs.style">
  <label v-if="label" :for="attrs.id">{{ label }}</label>
  <div class="textarea-block">
    <textarea
      v-bind="textareaAttrs()"
      v-model="model"
      autocomplete="off"
      :cols="cols"
      :rows="rows"
      :placeholder="placeholder"
      :readonly="readonly"
      :disabled="disabled"
    />
  </div>
</div>
</template>

<style scoped>
.afr-textarea{

}

.afr-textarea label{
  @apply px-2;
}

.afr-textarea .textarea-block{
  @apply border border-gray-300 rounded relative overflow-hidden;
}

.afr-textarea textarea{
  @apply w-full h-full px-2 align-top;
}
</style>
