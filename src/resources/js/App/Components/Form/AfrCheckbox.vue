<script setup>

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  value: { type: String, default: '' },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});
const emits = defineEmits(['update:modelValue', 'change']);

// const checked = ref(props.modelValue);

const onChange = () => {
  if (props.disabled) return;
  const newVal = !props.modelValue;
  emits('update:modelValue', newVal);
  emits('change', newVal);
}
</script>

<template>
  <div class="afr-checkbox" :class="{ 'is-disabled': disabled }" role="checkbox" :aria-checked="modelValue" :aria-disabled="disabled" :tabindex="disabled ? -1 : 0" @click="onChange" @keydown.space.prevent="onChange" @keydown.enter.prevent="onChange">
    <div>
      <input
        :checked="modelValue"
        :id="id"
        type="checkbox"
        :name="name"
        :disabled="disabled"
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
.afr-checkbox.is-disabled { @apply cursor-not-allowed opacity-70; }
.afr-checkbox.is-disabled label { cursor: not-allowed; }
.afr-checkbox:focus-visible { @apply outline outline-2 outline-offset-2 outline-blue-400 rounded-sm; }

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
