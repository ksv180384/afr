<script setup>
import { ref, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';

import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';

const props = defineProps({
  postStatus: { type: Object, default: null },
  errors: { type: Object, default: {} },
});
const emits = defineEmits(['submit', 'delete']);

const refForm = ref(null);
const form = useForm({
  title: props.postStatus?.title || '',
  alias: props.postStatus?.alias || '',
  for_create: props.postStatus?.for_create || false,
});
const rules = reactive({
  title: [
    { required: true, message: 'Название статуса не должно быть пустым', trigger: 'blur' },
    { min: 2, message: 'Название статуса должно быть не менее 2-х символов', trigger: 'blur' },
  ],
})

const submit = async () => {
  const isFormValid = await refForm.value.validate((valid) => valid);

  if(!isFormValid){
    return true;
  }

  emits('submit', form);
}

const deleteItem = () => {
  const deleteForm = useForm({
    id: props.postStatus?.id,
  });
  emits('delete', deleteForm);
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
        <el-form-item prop="title">
          <el-input
            v-model="form.title"
            label="Название статуса"
            placeholder="Введите название статуса"
          />
        </el-form-item>
      </div>
      <div>
        <el-form-item prop="title">
          <el-input
            v-model="form.alias"
            label="Псевдоним статуса"
            placeholder="Введите псевдоним статуса"
            :readonly="!!postStatus"
          />
        </el-form-item>
      </div>
      <div>
        <el-checkbox
          v-model="form.for_create"
          id="statusForCreate"
        >
          Статус используется при добавлении поста
        </el-checkbox>
      </div>
      <div class="flex flex-row justify-between">
        <div>
          <el-button native-type="submit">Сохранить</el-button>
        </div>
        <div>
          <el-button
            v-if="!!postStatus"
            type="danger"
            native-type="button"
            @click="deleteItem"
          >
            Удалить
          </el-button>
        </div>
      </div>

      <afr-input-error-message v-if="errors.error">{{ errors.error }}</afr-input-error-message>
    </el-form>
  </div>
</template>

<style scoped>

</style>
