<script setup>
import { reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AfrInputErrorMessage from "@/App/Components/Form/AfrInputErrorMessage.vue";

const props = defineProps({
  artists: { type: Array, default: [] },
  song: { type: Object, default: null },
  errors: { type: Object, default: null },
});
const emits = defineEmits(['submit']);

const refForm = ref(null);
const form = useForm({
  artist_id: props.song?.artist?.id || null,
  title: props.song?.title || '',
  text_fr: props.song?.text_fr || '',
  text_ru: props.song?.text_ru || '',
  text_transcription: props.song?.text_transcription || '',
  hidden: props.song?.hidden || true,
});
const rules = reactive({
  artist_id : [
    { required: true, message: 'Выберите исполнителя', trigger: 'change' },
  ],
  title: [
    { required: true, message: 'Название не должно быть пустым', trigger: 'change' },
    { min: 2, message: 'Название должно быть не менее 2-х символов', trigger: 'change' },
  ],
  text_fr: [
    { required: true, message: 'Текст песни не должен быть пустым', trigger: 'change' },
  ],
  text_ru: [
    { required: true, message: 'Перевод не должен быть пустым', trigger: 'change' },
  ],
  text_transcription: [
    { required: true, message: 'Транскрипция не должна быть пуста', trigger: 'change' },
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
      <div class="flex flex-row gap-1">
        <div class="flex-1">
          <el-form-item label="Название песни" label-position="top" prop="title">
            <el-input
              v-model="form.title"
              placeholder="Введите название песни"
            />
          </el-form-item>
        </div>
        <div class="flex-1">
          <el-form-item label="Исполнитель" label-position="top" prop="title">
            <el-select
              v-model="form.artist_id"
              class="w-auto"
              placeholder="Исполнитель"
              filterable
            >
              <el-option
                v-for="artists in artists"
                :key="artists.id"
                :label="artists.name"
                :value="artists.id"
              />
            </el-select>
          </el-form-item>
        </div>
      </div>
      <div class="flex flex-row gap-1">
        <div class="flex-1">
          <el-form-item label="Текст песни" label-position="top" prop="text_fr">
            <el-input
              v-model="form.text_fr"
              :rows="20"
              type="textarea"
              placeholder="Введите текст песни"
            />
          </el-form-item>
        </div>

        <div class="flex-1">
          <el-form-item label="Перевод песни" label-position="top" prop="text_ru">
            <el-input
              v-model="form.text_ru"
              :rows="20"
              type="textarea"
              placeholder="Введите перевод песни"
            />
          </el-form-item>
        </div>

        <div class="flex-1">
          <el-form-item label="Транскрипция песни" label-position="top" prop="text_transcription">
            <el-input
              v-model="form.text_transcription"
              :rows="20"
              type="textarea"
              placeholder="Введите транскрипцию песни"
            />
          </el-form-item>
        </div>
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
