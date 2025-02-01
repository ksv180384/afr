<script setup>
import AfrHeader from '@/App/Components/Header/AfrHeader.vue';
import AfrFooter from '@/App/Components/Footer/AfrFooter.vue';


const props = defineProps({
  authUser: { type: Object, default: null },
  subMenu: { type: Array, default: null },
});

</script>
<template>
  <div class="mini-layout">

    <afr-header
      :auth-user="authUser"
      :sub-menu="subMenu"
    />

    <div class="mini-layout-container">
      <div class="content-container">

        <slot/>

      </div>
    </div>

    <afr-footer/>

  </div>
</template>

<style scoped>
.mini-layout{
  @apply flex min-h-screen pt-[57px] flex-col;
}

.mini-layout-container{
  @apply flex flex-1 w-[1440px] mx-auto bg-sky-50 max-w-full;
}

.content-container{
  @apply min-h-full w-full;
}
</style>

