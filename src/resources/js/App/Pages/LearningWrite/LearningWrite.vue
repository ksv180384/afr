<script setup>
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import AfrLearningWrite from '@/App/Components/AfrLearningWrite.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  menu: { type: Array, default: [] },
});
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <seo-head
      title="Тренировка написания французских слов"
      description="Учитесь писать французские слова правильно"
      :no-index="true"
    />
    <div class="widget-player">
      <afr-learning-write />
    </div>
  </mini-layout>
</template>

<style scoped>
.widget-player{
  @apply flex h-full items-center px-8;
}
</style>
