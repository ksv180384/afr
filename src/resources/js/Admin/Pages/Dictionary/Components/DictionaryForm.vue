<script setup>
import { ref, reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { isObjectEmpty } from '@/Helpers/helper.js';
import api from '@/Admin/Services/Api/index.js';
import AfrInputErrorMessage from "@/App/Components/Form/AfrInputErrorMessage.vue";

const props = defineProps({
  word: { type: Object, default: null },
  partOfSpeeches: { type: Array, default: [] },
  errors: { type: Object, default: {} },
});
const emits = defineEmits(['submit', 'changeSearchItems']);

const refForm = ref(null);
const isLoadingSearch = ref(false);
let timeoutSearchId = null;
const form = useForm({
  id_part_of_speech: props.word?.id_part_of_speech || null,
  word: props.word?.word || null,
  translation: props.word?.translation || null,
  transcription: props.word?.transcription || null,
  example: props.word?.example || null,
});
const rules = reactive({
  word: [
    { required: true, message: 'Слово не должен быть пустым', trigger: 'submit' },
    { min: 2, message: 'Слово должен быть не менее 2-х символов', trigger: 'submit' },
  ],
  translation: [
    { required: true, message: 'Перевод не должен быть пустым', trigger: 'submit' },
    { min: 2, message: 'Перевод должен быть не менее 2-х символов', trigger: 'submit' },
  ],
  transcription: [
    { required: true, message: 'Транскрипция не должна быть пустой', trigger: 'submit' },
    { min: 2, message: 'Транскрипция должна быть не менее 2-х символов', trigger: 'submit' },
  ],
  example: [
    { required: true, message: 'Пример не должен быть пустым', trigger: 'submit' },
    { min: 2, message: 'Пример должен быть не менее 2-х символов', trigger: 'submit' },
  ],
})

const filterData = async (data) => {
  if(isObjectEmpty(data)){
    emits('changeSearchItems', []);
    return;
  }
  isLoadingSearch.value = true;
  try {
    const res = await api.dictionary.filter(data);
    emits('changeSearchItems', res.words);
  } catch (e) {
    console.error(e)
  } finally {
    isLoadingSearch.value = false;
  }
}

const handleInputSearch = (text) => {
  if (timeoutSearchId) {
    clearTimeout(timeoutSearchId);
  }
  timeoutSearchId = setTimeout(() => {
    console.log(text)
    filterData({ text: text })
  }, 300); // Задержка в 300 мс
};

const submit = async () => {
  const isFormValid = await refForm.value.validate((valid) => valid);

  if(!isFormValid){
    return true;
  }

  emits('submit', form);
}

watch(
  () => form.word,
  (newValue) => {
    if (newValue.length >= 2) { // Подгружаем данные только если введено более 2 символов
      handleInputSearch(newValue);
    }
    else{
      emits('change', { text: '' });
    }
  });
</script>

<template>
<el-form
  ref="refForm"
  :model="form"
  :rules="rules"
  class="p-4"
  @submit.prevent="submit"
>
  <afr-input-error-message v-if="Object.values(errors)[0]">{{ Object.values(errors)[0] }}</afr-input-error-message>
  <div class="flex flex-row gap-2">
    <el-form-item prop="word" label="Слово" label-position="top" class="form-item-input">
      <el-input v-model="form.word" placeholder="Слово"/>
    </el-form-item>
    <el-form-item prop="translation" label="Перевод" label-position="top" class="form-item-input">
      <el-input v-model="form.translation" placeholder="Перевод"/>
    </el-form-item>
    <el-form-item prop="transcription" label="Транскрипция" label-position="top" class="form-item-input">
      <el-input v-model="form.transcription" placeholder="Транскрипция"/>
    </el-form-item>
    <el-form-item prop="id_part_of_speech" label="Часть речи" label-position="top" class="form-item-input">
      <el-select
        v-model="form.id_part_of_speech"
        class="w-auto"
        placeholder="Часть речи"
        filterable
        clearable
      >
        <el-option
          v-for="item in partOfSpeeches"
          :key="item.id"
          :label="item.title"
          :value="item.id"
        />
      </el-select>
    </el-form-item>
  </div>
  <el-form-item prop="example">
    <el-input v-model="form.example" type="textarea" placeholder="Примеры" :rows="5"/>
  </el-form-item>

  <el-button
    type="success"
    native-type="submit"
    plain
  >
    Сохранить
  </el-button>
</el-form>
</template>

<style scoped>
.form-item-input{
  @apply flex-1;
}
</style>
