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
  @apply w-full mb-2 flex flex-grow gap-2 h-[220px] px-4;
}

</style>
