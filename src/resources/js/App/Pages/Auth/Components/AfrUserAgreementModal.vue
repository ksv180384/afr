<script setup>
import { ref, watch } from 'vue';

import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrTermsUserContent from '@/App/Components/Info/AfrTermsUserContent.vue';

const emits = defineEmits(['update:modelValue']);
const model = defineModel();
const isOpenModel = ref(model.value);

const onClose = () => {
  emits('update:modelValue', false);
}

watch(
  () => model.value,
  (newValue) => {
    isOpenModel.value = newValue;
  }
)
</script>

<template>
  <afr-dialog
    v-model="isOpenModel"
    width="500px"
    full-screen
    @close="onClose"
  >
    <template #header>
      Правила пользовательского соглашения
    </template>

    <afr-terms-user-content/>
  </afr-dialog>
</template>

<style scoped>

</style>
