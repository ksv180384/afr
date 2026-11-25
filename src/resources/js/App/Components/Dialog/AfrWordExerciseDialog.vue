<script setup>
import { ref } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrDialog from './AfrDialog.vue';

defineProps({ title: { type: String, required: true } });
const open = ref(false);
</script>

<template>
  <div class="w-full">
    <afr-button type="primary" class="w-full py-2" aria-haspopup="dialog" @click="open = true">
      {{ title }}
    </afr-button>
    <afr-dialog v-model="open" width="32rem">
      <template #header>
        <h2 tabindex="-1" autofocus class="outline-none">{{ title }}</h2>
      </template>
      <slot />
    </afr-dialog>
  </div>
</template>
