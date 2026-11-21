<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  value: { type: String, default: '' },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});
const emits = defineEmits(['update:modelValue', 'change']);

const blocked = computed(() => props.disabled || props.loading);

const onChange = () => {
  if (blocked.value) return;
  const newVal = !props.modelValue;
  emits('update:modelValue', newVal);
  emits('change', newVal);
}
</script>

<template>
  <div
    class="afr-checkbox"
    :class="{ 'is-disabled': disabled, 'is-loading': loading }"
    role="checkbox"
    :aria-checked="modelValue"
    :aria-disabled="blocked"
    :aria-busy="loading || undefined"
    :tabindex="disabled ? -1 : 0"
    @click="onChange"
    @keydown.space.prevent="onChange"
    @keydown.enter.prevent="onChange"
  >
    <div>
      <input
        :checked="modelValue"
        :id="id"
        type="checkbox"
        :name="name"
        :disabled="blocked"
        hidden="hidden"
      />
      <span v-if="loading" class="afr-checkbox-spinner" aria-hidden="true"></span>
      <span v-else class="afr-checkbox-icon"></span>
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
.afr-checkbox.is-loading, .afr-checkbox.is-loading label { @apply cursor-wait; }
.afr-checkbox:focus-visible { @apply outline outline-2 outline-offset-2 outline-blue-400 rounded-sm; }

.afr-checkbox label{
  @apply text-sm cursor-pointer;
}

.afr-checkbox-icon{
  @apply flex justify-center items-center w-[14px] h-[14px] border border-gray-500 rounded-sm bg-white;
}

.afr-checkbox-spinner {
  @apply block w-[14px] h-[14px] rounded-full border-2 border-blue-500 border-r-blue-100;
}

@media (prefers-reduced-motion: no-preference) {
  .afr-checkbox-spinner { animation: checkbox-spin 1s linear infinite; }
}

@keyframes checkbox-spin {
  to { transform: rotate(1turn); }
}

.afr-checkbox input:checked+.afr-checkbox-icon{
  @apply border-blue-500;
}

.afr-checkbox input:checked+.afr-checkbox-icon:after{
  @apply flex items-center justify-center w-[10px] h-[10px] bg-blue-500 rounded-sm;
  content: '';
}
</style>
