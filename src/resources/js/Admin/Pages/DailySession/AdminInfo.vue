<script setup>
import { InfoFilled } from '@element-plus/icons-vue';

defineProps({ label: String });
</script>

<template>
  <el-popover trigger="click" :width="340" :popper-style="{ maxWidth: 'calc(100vw - 24px)' }" placement="bottom-start">
    <template #reference>
      <el-button circle type="primary" plain :icon="InfoFilled" :aria-label="label" :title="label" />
    </template>
    <slot />
  </el-popover>
</template>
