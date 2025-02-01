<script setup>
import { ref, watch } from 'vue';

import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrPrivacyPolicyContent from '@/App/Components/Info/AfrPrivacyPolicyContent.vue';

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
      Политика по защите персональных данных
    </template>

    <afr-privacy-policy-content/>
  </afr-dialog>
</template>

<style scoped>

</style>

