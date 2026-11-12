<script setup>
import { ref } from 'vue';
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import PracticeNavigation from '@/App/Components/Learning/PracticeNavigation.vue';
defineProps({authUser:{type:Object,default:null},title:String,active:String,navigation:{type:Boolean,default:true},focused:Boolean});
const navHeight = ref(0);
const content = ref(null);
const revealFocusedControl = ({target}) => {
  if (!target.matches(':focus-visible') || target.closest('.practice-nav, dialog')) return;
  if (!navHeight.value || !window.matchMedia('(max-width: 800px)').matches) return;
  const edge = window.innerHeight - navHeight.value;
  if (target.getBoundingClientRect().bottom > edge) target.scrollIntoView({block: 'end'});
};
</script>
<template>
  <mini-layout :auth-user="authUser" :focused="focused" class="practice-layout"
    :class="{ 'practice-with-navigation': navigation && !focused }" :style="{ '--practice-nav-height': navHeight + 'px' }" @focusin="revealFocusedControl">
    <main class="practice-shell" :class="{ 'practice-columns': navigation && !focused, 'practice-shell--focused': focused }">
      <a v-if="navigation && !focused" href="#practice-content" class="practice-skip-link" @click.prevent="content?.focus()">К содержимому практики</a>
      <practice-navigation v-if="navigation && !focused" :active="active" @resize="navHeight = $event" />
      <div ref="content" id="practice-content" class="practice-content" tabindex="-1">
        <h1 v-if="!focused" class="practice-heading">{{ title }}</h1>
        <slot />
      </div>
    </main>
  </mini-layout>
</template>
