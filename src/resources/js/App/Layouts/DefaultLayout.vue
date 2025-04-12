<script setup>
// import { onMounted } from 'vue';
// import { speak } from '@/Helpers/helper';

import AfrHeader from '@/App/Components/Header/AfrHeader.vue';
import AfrFooter from '@/App/Components/Footer/AfrFooter.vue';
import AfrCard from '@/App/Components/Card/AfrCard.vue';
import AfrPlayer from '@/App/Components/Player/AfrPlayer.vue';
import AfrWords from '@/App/Components/Words/AfrWords.vue';
import AfrProverb from "@/App/Components/Proverb/AfrProverb.vue";
import AfrAnecdote from '@/App/Components/Anecdote/AfrAnecdote.vue';
import AfrLearningWrite from '@/App/Components/AfrLearningWrite.vue';
import AfrCheckYourself from '@/App/Components/CheckYourself/AfrCheckYourself.vue';

const props = defineProps({
  words: { type: Array, default: [] },
  proverb: { type: Object, default: null },
  authUser: { type: Object, default: null },
});

// Запускаем пустую функцию проговаривания текста для подгрузки массива
// со списком поддерживаемых языков
// onMounted(() => {
//   speak('');
// });
</script>
<template>
  <div class="layout-two">

    <afr-header :auth-user="authUser"/>

    <div class="container-max layout-two-center">
      <div class="left-container">

        <afr-card header="Плеер">
          <afr-player/>
        </afr-card>

        <afr-card>
          <div class="flex gap-2">
            <afr-learning-write/>
            <afr-check-yourself/>
          </div>
        </afr-card>

        <afr-card header="Слова">
          <afr-words :words="words"/>
        </afr-card>

        <afr-card header="Анекдот">
          <afr-anecdote/>
        </afr-card>

      </div>
      <div class="content-container">

        <template v-if="proverb">
          <afr-card header="Пословица" class="proverb-container">
            <afr-proverb :proverb="proverb"/>
          </afr-card>
        </template>

        <slot/>

      </div>
    </div>

    <afr-footer/>

  </div>
</template>

<style scoped>
.layout-two{
  @apply flex min-h-screen pt-[57px] flex-col;
}

.layout-two-center{
  @apply flex flex-1 gap-0.5;
}

.left-container{
  @apply hidden lg:flex flex-col gap-0.5;
  width: 280px;
}

.content-container{
  @apply flex-1 flex flex-col gap-0.5 min-h-full;
}

.proverb-container{
  @apply hidden lg:block;
}
</style>
