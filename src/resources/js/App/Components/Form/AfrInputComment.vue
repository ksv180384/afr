<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
  placeholder: { type: String, default: '' },
});

const model = defineModel();
const refTextarea = ref(null); // Ссылка на элемент <textarea>

// Функция для автоматической корректировки высоты
const adjustHeight = () => {
  const el = refTextarea.value;
  if (el) {
    el.style.height = 'auto'; // Сбрасываем высоту
    el.style.height = `${el.scrollHeight}px`; // Устанавливаем высоту по содержимому
  }

};

// Устанавливаем начальную высоту при монтировании компонента
onMounted(() => {
  adjustHeight();
});
</script>

<template>
  <div class="comment-component">
    <textarea
      v-model="model"
      ref="refTextarea"
      @input="adjustHeight"
      rows="1"
      class="auto-resize-textarea"
      :placeholder="placeholder"
    ></textarea>
  </div>
</template>

<style>
.auto-resize-textarea {
  width: 100%; /* Поле занимает всю ширину контейнера */
  overflow: hidden; /* Скрываем полосу прокрутки */
  resize: none; /* Запрещаем изменение размера вручную */
  box-sizing: border-box; /* Учитываем padding и border в ширине/высоте */
  font-size: 16px; /* Размер шрифта */
  line-height: 1.5; /* Высота строки */
}

.comment-component textarea{
  @apply py-1 px-3;
}
</style>
