<script setup>

defineProps({
  menu: { type: Array, default: null },
});
</script>

<template>
  <div class="menu">

      <slot/>
<!--      <li-->
<!--        v-for="menuItem in menu"-->
<!--        :key="menuItem.id"-->
<!--        :class="{ 'active': route().current('grammar.show', { id: menuItem.id }) }"-->
<!--      >-->
<!--        <Link-->
<!--          :href="route('grammar.show', { id: menuItem.id })"-->
<!--          :title="menuItem.title"-->
<!--        >-->
<!--          {{ menuItem.title }}-->
<!--        </Link>-->
<!--      </li>-->

  </div>
</template>

<style scoped>
.menu{
  height: 100vh;
  overflow-y: auto;
  padding-bottom: 40px;
}

.menu ul{
  @apply px-3 py-2;
}
</style>
