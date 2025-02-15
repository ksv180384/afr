<script setup>
import { useForm } from '@inertiajs/vue3';
import {reactive, ref} from "vue";
import AfrInputErrorMessage from "@/App/Components/Form/AfrInputErrorMessage.vue";

const props = defineProps({
  lesson: { type: Object, default: null },
  errors: { type: Object, default: {} },
});
const emits = defineEmits(['submit', 'delete']);

const refForm = ref(null);
const form = useForm({
  title: props.lesson?.title || '',
  description: props.lesson?.description || '',
  content: props.lesson?.content || '',
});
const rules = reactive({
  title: [
    { required: true, message: 'Название урока не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Название урока должно быть не менее 2-х символов', trigger: 'blur' },
  ],
  description: [
    { required: true, message: 'Описание не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Описание должно быть не менее 2-х символов', trigger: 'blur' },
  ],
  content: [
    { required: true, message: 'Содержимое урока не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Содержимое урока должно быть не менее 2-х символов', trigger: 'blur' },
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
  <el-form
    ref="refForm"
    :model="form"
    :rules="rules"
    class="flex flex-col"
    @submit.prevent="submit"
  >
    <div>
      <el-form-item label="Название урока" label-position="top" prop="title">
        <el-input
          v-model="form.title"
          placeholder="Введите название урока"
        />
      </el-form-item>

      <el-form-item label="Описание урока" label-position="top" prop="description">
        <el-input
          v-model="form.description"
          :rows="2"
          type="textarea"
          placeholder="Введите описание урока пословицы"
        />
      </el-form-item>

      <el-form-item label="Текст урока" label-position="top" prop="content">
        <el-input
          v-model="form.content"
          :rows="20"
          type="textarea"
          placeholder="Введите текст урока"
        />
      </el-form-item>
    </div>

    <div class="flex flex-row justify-between">
      <div>
        <el-button native-type="submit" type="success" plain>Сохранить</el-button>
      </div>
    </div>

    <afr-input-error-message v-if="errors.error">{{ errors.error }}</afr-input-error-message>
  </el-form>
</template>

<style scoped>

</style>
