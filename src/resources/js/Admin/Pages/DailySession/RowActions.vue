<script setup>
import { MoreFilled } from '@element-plus/icons-vue';

defineProps({ label: String, disabled: Boolean });
</script>

<template>
  <el-dropdown trigger="click" placement="bottom-end">
    <el-button circle text :icon="MoreFilled" :disabled="disabled" :aria-label="label" />
    <template #dropdown>
      <el-dropdown-menu>
        <slot />
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>
