<script setup>
import menu from '@/App/Store/menuMyPosts.js';

import AfrHeader from '@/App/Components/Header/AfrHeader.vue';
import AfrFooter from '@/App/Components/Footer/AfrFooter.vue';
import AfrMenuSimple from '@/App/Components/Menu/MenuSimple/AfrMenuSimple.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

</script>
<template>
  <div class="my-posts-layout">

    <afr-header :auth-user="authUser"/>

    <div class="container-max my-posts-layout-content">
      <div v-if="menu" class="left-container">

        <div class="menu-header">
          Мои посты
        </div>

        <div class="sticky top-2.5">
          <afr-menu-simple :menu="menu"/>
        </div>

      </div>
      <div class="content-container">

        <slot/>

      </div>
    </div>

    <afr-footer/>

  </div>
</template>

<style scoped>
.my-posts-layout{
  @apply flex min-h-screen pt-[57px] flex-col;
}

.my-posts-layout-content{
  @apply flex flex-1 gap-0.5;
}

.left-container{
  @apply hidden lg:flex flex-col gap-0.5 bg-sky-50;
  width: 280px;
}

.content-container{
  @apply flex-1 overflow-hidden flex flex-col gap-0.5 min-h-full bg-sky-50;
}

.menu-header{
  @apply text-center bg-blue-100 font-semibold py-2 rounded-sm;
}
</style>
