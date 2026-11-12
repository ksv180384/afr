<script setup>
import ErrorLayout from '@/Components/ErrorLayout.vue';

defineProps({
  admin: { type: Boolean, default: false },
});
</script>

<template>
  <error-layout
    title="403: Доступ запрещён"
    description="У вас нет доступа к этой странице. Если это ошибка, свяжитесь с поддержкой."
    :admin="admin"
  />
</template>
