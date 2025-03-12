<script setup>
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import AfrCheckYourself from '@/App/Components/CheckYourself/AfrCheckYourself.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  menu: { type: Array, default: [] },
});
</script>

<template>
  <mini-layout
    :auth-user="authUser"
  >
    <div class="check-yourself">
      <afr-check-yourself />
    </div>
  </mini-layout>
</template>

<style scoped>
.check-yourself{
  @apply flex h-full items-center px-8;
}
</style>

