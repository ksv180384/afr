<script setup>
import {reactive, watch} from 'vue';
import { Search } from '@element-plus/icons-vue';

const props = defineProps({
  partOfSpeeches: { type: Array, default: [] },
  loading: { type: Boolean, default: false },
});
const emits = defineEmits(['change']);

let timeoutSearchId = null;
const filterForm = reactive({
  text: '',
  part_of_speeches: null,
});

const clearSearchText = () => {
  emits('change', filterForm);
}

const handleInputSearch = () => {
  if (timeoutSearchId) {
    clearTimeout(timeoutSearchId);
  }
  timeoutSearchId = setTimeout(() => {
    emits('change', filterForm);
  }, 300); // Задержка в 300 мс
};

const onChangePartOfSpeeches = () => {
  emits('change', filterForm);
}

watch(
  () => filterForm.text,
  (newValue) => {
  if (newValue.length >= 2) { // Подгружаем данные только если введено более 2 символов
    handleInputSearch();
  }
  else{
    emits('change', { ...filterForm, text: '' });
  }
});
</script>

<template>
  <div class="flex gap-2">
    <el-input
      v-model="filterForm.text"
      clearable
      @clear="clearSearchText"
    >
      <template #prepend>
        <el-button
          :icon="Search"
          :loading="loading"
        />
      </template>
    </el-input>

    <el-select
      v-model="filterForm.part_of_speeches"
      class="w-auto"
      placeholder="Часть речи"
      filterable
      clearable
      @change="onChangePartOfSpeeches"
      @clear="onChangePartOfSpeeches"
    >
      <el-option
        v-for="item in partOfSpeeches"
        :key="item.id"
        :label="item.title"
        :value="item.id"
      />
    </el-select>
  </div>
</template>

<style scoped>

</style>
