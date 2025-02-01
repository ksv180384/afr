<script setup>
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
<div class="afr-textarea">
  <label v-if="label">{{ label }}</label>
  <div class="textarea-block">
    <textarea
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
