<script setup>
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import PracticeNavigation from '@/App/Components/Learning/PracticeNavigation.vue';
defineProps({authUser:{type:Object,default:null},title:String,active:String,navigation:{type:Boolean,default:true}});
</script>
<template>
  <mini-layout :auth-user="authUser">
    <main class="practice-shell">
      <header class="practice-section-header">
        <practice-navigation v-if="navigation" :active="active" />
        <h1 class="practice-heading">{{ title }}</h1>
      </header>
      <slot />
    </main>
  </mini-layout>
</template>
