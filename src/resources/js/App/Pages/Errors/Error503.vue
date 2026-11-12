<script setup>
import ErrorLayout from '@/Components/ErrorLayout.vue';

defineProps({
  admin: { type: Boolean, default: false },
});
</script>

<template>
  <error-layout
    title="503: Сервис недоступен"
    description="Извините, сервис временно недоступен из-за технических работ. Попробуйте зайти позже."
    :admin="admin"
  />
</template>
