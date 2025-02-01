<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  styleClass: { type: String, default: '' },
  size: {
    type: String,
    default: 'default',
    validator(value) {
      return ['large', 'default', 'small'].includes(value);
    }
  },
});

const classSize = computed(() => `size-${props.size}`);
</script>

<template>
  <div
    class="afr-close-btn"
    :class="[{ styleClass: styleClass }, classSize]"
  >
    <Icon icon="jam:close" />
  </div>
</template>

<style scoped>
.afr-close-btn{
  @apply flex items-center justify-center cursor-pointer rounded hover:bg-red-500 select-none
         transition duration-200 hover:text-red-50;
  font-size: 22px;
  width: 32px;
  height: 32px;
}

.size-large{
  @apply text-xl w-[38px] h-[38px];
}
.size-default{
  @apply text-base w-[32px] h-[32px];
}
.size-small{
  @apply text-sm w-[24px] h-[24px];
}
</style>
