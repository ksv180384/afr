<script setup>
import { useForm } from '@inertiajs/vue3';
import {reactive, ref} from "vue";
import AfrInputErrorMessage from "@/App/Components/Form/AfrInputErrorMessage.vue";

const props = defineProps({
  grammar: { type: Object, default: null },
  errors: { type: Object, default: {} },
});
const emits = defineEmits(['submit', 'delete']);

const refForm = ref(null);
const form = useForm({
  title: props.grammar?.title || '',
  description: props.grammar?.description || '',
  content: props.grammar?.content || '',
  source: props.grammar?.source || '',
});
const rules = reactive({
  title: [
    { required: true, message: 'Название не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Название должно быть не менее 2-х символов', trigger: 'blur' },
  ],
  description: [
    { required: true, message: 'Описание не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Описание должно быть не менее 2-х символов', trigger: 'blur' },
  ],
  content: [
    { required: true, message: 'Содержимое не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Содержимое должно быть не менее 2-х символов', trigger: 'blur' },
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
      <el-form-item label="Название грамматики" label-position="top" prop="title">
        <el-input
          v-model="form.title"
          placeholder="Введите название грамматики"
        />
      </el-form-item>

      <el-form-item label="Описание грамматики" label-position="top" prop="description">
        <el-input
          v-model="form.description"
          :rows="2"
          type="textarea"
          placeholder="Введите описание грамматики"
        />
      </el-form-item>

      <el-form-item label="Текст грамматики" label-position="top" prop="content">
        <el-input
          v-model="form.content"
          :rows="20"
          type="textarea"
          placeholder="Введите текст грамматики"
        />
      </el-form-item>
    </div>

    <el-form-item label="Источник" label-position="top" prop="source">
      <el-input
        v-model="form.source"
        :rows="2"
        type="textarea"
        placeholder="Введите Источник"
      />
    </el-form-item>

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
