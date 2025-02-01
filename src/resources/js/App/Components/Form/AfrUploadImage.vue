<script setup>
import { ref } from 'vue';
import { Icon } from '@iconify/vue';
import { useDropZone } from '@vueuse/core';

const model = defineModel();
const props = defineProps({
  loading: { type: Boolean, default: false },
});
const emits = defineEmits(['change', 'remove']);

const imageSrc = ref(model.value || '');
const refInputFile = ref(null);
const refDropZone = ref(null);

const onClick = () => {
  refInputFile.value.click();
}

const onChange = (e) => {
  if(e.target?.files?.[0]){
    model.value = e.target.files[0];
    showFile(e.target.files[0]);
    emits('change', e.target.files[0]);
  }
  else {
    model.value = null;
    imageSrc.value = '';
    emits('remove');
  }
}

const onDrop = (files) => {
  const file = files[0];
  const validImageTypes = [
    'image/jpeg',   // JPEG
    'image/png',    // PNG
    'image/gif',    // GIF
    'image/webp',   // WebP
    'image/svg+xml', // SVG
    'image/apng',   // APNG
    'image/avif',   // AVIF
    'image/bmp',    // BMP
    'image/tiff',   // TIFF
    'image/x-icon'  // ICO
  ];
  if(!validImageTypes.includes(file.type)){
    return;
  }

  model.value = file;
  showFile(file);
  emits('change', file);
}
const { isOverDropZone } = useDropZone(refDropZone, {
  onDrop,
});

const showFile = (file) => {
  const reader = new FileReader();
  reader.onload = (e) => {
    imageSrc.value = e.target.result; // Устанавливаем src изображения
  };
  reader.readAsDataURL(file); // Читаем файл как Data URL
}

// watch(
//   () => model.value,
//   (newVal) => {
//     if(typeof newVal === 'string'){
//       imageSrc.value = newVal;
//     }
//   }
// );
</script>

<template>
<div
  ref="refDropZone"
  class="afr-upload-image"
  :class="{ 'is-over-drop-zone': isOverDropZone }"
  @click="onClick"
>
  <template v-if="loading">
    <div class="absolute flex justify-center items-center inset-0 m-auto w-[40px] h-[40px] bg-gray-900 bg-opacity-60 rounded z-10">
      <Icon
        icon="svg-spinners:6-dots-rotate"
        width="24px"
        height="24px"
      />
    </div>
  </template>
  <div v-if="imageSrc" class="img-block">
    <Icon icon="ic:baseline-delete" width="24px" height="24px" @click.stop="onChange"/>
    <img :src="imageSrc" alt="Загрузка картинки"/>
  </div>
  <Icon v-else icon="flowbite:plus-outline" width="36px" height="36px" />
  <input ref="refInputFile" type="file" accept="image/*" hidden="hidden" @change="onChange">
</div>
</template>

<style scoped>
.afr-upload-image{
  @apply border-blue-400 border rounded-lg border-dashed relative flex items-center justify-center text-gray-300
         bg-white transition duration-300 overflow-hidden min-w-[40px] min-h-[40px];
}

.is-over-drop-zone{
  @apply bg-sky-50 border-sky-400;
}

.img-block{
  @apply w-full h-full relative;
}

.img-block svg{
  @apply absolute right-1 top-1 cursor-pointer opacity-80 lg:opacity-0 transition duration-300 text-red-400 bg-white rounded;
}

.img-block:hover svg{
  @apply opacity-70;
}

.img-block img{
  @apply w-full h-full object-cover object-top;
}
</style>
