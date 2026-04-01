<script setup>
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import AfrPlayer from '@/App/Components/Player/AfrPlayer.vue';

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
      title="Плеер французских песен"
      description="Слушайте французские песни с текстом, переводом и транскрипцией"
      :no-index="true"
    />
    <div class="widget-player">
      <afr-player class="w-full py-4"/>
    </div>
  </mini-layout>
</template>

<style scoped>
.widget-player{
  @apply flex h-full;
}
</style>
