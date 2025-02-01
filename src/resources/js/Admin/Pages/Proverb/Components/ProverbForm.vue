<script setup>
import { ref, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';

import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';

const props = defineProps({
  proverb: { type: Object, default: null },
  errors: { type: Object, default: {} },
});
const emits = defineEmits(['submit', 'delete']);

const refForm = ref(null);
const form = useForm({
  text: props.proverb?.text || '',
  translation: props.proverb?.translation || '',
});
const rules = reactive({
  text: [
    { required: true, message: 'Текст не должен быть пустым', trigger: 'blur' },
    { min: 2, message: 'Текст должен быть не менее 2-х символов', trigger: 'blur' },
  ],
  translation: [
    { required: true, message: 'Перевод не должен быть пустым', trigger: 'blur' },
    { min: 2, message: 'Перевод должен быть не менее 2-х символов', trigger: 'blur' },
  ],
})

const submit = async () => {
  const isFormValid = await refForm.value.validate((valid) => valid);

  if(!isFormValid){
    return true;
  }

  emits('submit', form);
}
</script>

<template>
  <div class="p-4">
    <el-form
      ref="refForm"
      :model="form"
      :rules="rules"
      class="flex flex-col"
      @submit.prevent="submit"
    >
      <div>
        <el-form-item label="Текст пословицы" label-position="top" prop="text">
          <el-input
            v-model="form.text"
            :rows="5"
            type="textarea"
            placeholder="Введите текст пословицы"
          />
        </el-form-item>
      </div>
      <div>
        <el-form-item label="Перевод пословицы" label-position="top" prop="translation">
          <el-input
            v-model="form.translation"
            :rows="5"
            type="textarea"
            placeholder="Введите перевод пословицы"
          />
        </el-form-item>
      </div>
      <div class="flex flex-row justify-between">
        <div>
          <el-button native-type="submit">Сохранить</el-button>
        </div>
      </div>

      <afr-input-error-message v-if="errors.error">{{ errors.error }}</afr-input-error-message>
    </el-form>
  </div>
</template>

<style scoped>

</style>

