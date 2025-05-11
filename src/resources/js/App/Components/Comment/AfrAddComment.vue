<script setup>

import AfrInputComment from '@/App/Components/Form/AfrInputComment.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';

const props = defineProps({
  placeholder: { type: String, default: '' },
});
const model = defineModel();
const emits = defineEmits(['submit']);

const sendComment = () => {
  if(!model.value){
    return;
  }
  emits('submit');
}
</script>

<template>
<div class="flex flex-col gap-2">
  <div>
    <afr-input-comment
      v-model="model"
      :placeholder="placeholder"
    />
  </div>
  <div>
    <afr-button
      type="primary"
      native-type="button"
      @click="sendComment"
    >
      Отправить
    </afr-button>
  </div>
</div>
</template>

<style scoped>

</style>
