<script setup>

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  value: { type: String, default: '' },
  label: { type: String, default: '' },
});
const emits = defineEmits(['update:modelValue', 'change']);

// const checked = ref(props.modelValue);

const onChange = () => {
  const newVal = !props.modelValue;
  emits('update:modelValue', newVal);
  emits('change', newVal);
}
</script>

<template>
  <div class="afr-checkbox" @click="onChange">
    <div>
      <input
        :checked="modelValue"
        :id="id"
        type="checkbox"
        :name="name"
        hidden="hidden"
      />
      <span class="afr-checkbox-icon"></span>
    </div>
    <label>
      <template v-if="label">
        {{ label }}
      </template>
      <template v-else>
        <slot></slot>
      </template>
    </label>
  </div>
</template>

<style scoped>
.afr-checkbox{
  @apply flex gap-2 items-center select-none cursor-pointer;
}

.afr-checkbox label{
  @apply text-sm cursor-pointer;
}

.afr-checkbox-icon{
  @apply flex justify-center items-center w-[14px] h-[14px] border border-gray-500 rounded-sm bg-white;
}

.afr-checkbox input:checked+.afr-checkbox-icon{
  @apply border-blue-500;
}

.afr-checkbox input:checked+.afr-checkbox-icon:after{
  @apply flex items-center justify-center w-[10px] h-[10px] bg-blue-500 rounded-sm;
  content: '';
}
</style>
