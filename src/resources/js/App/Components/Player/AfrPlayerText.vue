<script setup>
import AfrPlayerTextBlock from '@/App/Components/Player/AfrPlayerTextBlock.vue';

const props = defineProps({
  fr: { type: Array, default: [] },
  ru: { type: Array, default: [] },
  transcription: { type: Array, default: [] },
  offset: { type: Number, default: 0 },
  hRow: { type: Number, default: 28 },
});
</script>

<template>
<div class="afr-player-text">
  <AfrPlayerTextBlock :text="transcription" :offset="offset" :h-row="hRow"/>
  <AfrPlayerTextBlock :text="fr" :offset="offset" :h-row="hRow"/>
  <AfrPlayerTextBlock :text="ru" :offset="offset" :h-row="hRow"/>
</div>
</template>

<style scoped>
.afr-player-text{
  @apply w-full mb-2 flex flex-col lg:flex-row flex-grow gap-2 lg:h-[220px] h-[calc(100vh-300px)];
}

</style>
