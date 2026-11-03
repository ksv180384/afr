<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { isMenuItemActive } from '@/App/Store/menu.js';

const page = usePage();

const props = defineProps({
  menu: { type: Array, default: [] }
});
</script>

<template>
  <nav class="navigation-top">
    <ul>
      <template v-for="menuItem in menu">
        <li>
          <Link :href="route(menuItem.name)" :aria-current="isMenuItemActive(page.url, menuItem) ? 'page' : null">{{ menuItem.title }}</Link>
        </li>
      </template>
    </ul>
  </nav>
</template>

<style scoped>
.navigation-top ul{
  @apply flex flex-row;
}

.navigation-top ul>li{
  @apply text-xs font-bold uppercase text-sky-900;
}

.navigation-top ul>li a{
  @apply inline-block my-2 px-3 py-3 rounded-md hover:bg-gray-50 transition-colors duration-200 whitespace-nowrap;
}

.navigation-top ul>li a[aria-current="page"]{
  @apply bg-blue-200/70 text-blue-800;
}

.navigation-top ul>li a:focus-visible{
  @apply outline outline-2 outline-offset-2 outline-blue-400;
}
/*
nav{

}

nav ul{
    list-style: none;
}

nav ul li{
    display: inline-block;
}

nav a{
    display: inline-block;
    color: #176093;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    text-decoration: none;
    padding: 20px 10px;
    transition: all 0.5s ease 0s;
}

nav a:hover{
    background-color: #fff;
}

.sidebar{
    display: none;
    height: 100%;
    justify-content: start;
    align-items: center;
}

.sidebar-btn{
    color: #176093;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 30px;
    height: 30px;
    font-size: 18px;
    cursor: pointer;
}

.sidebar-block{
    width: 300px;
    position: fixed;
    bottom: 0;
    left: -304px;
    height: 100vh;
    background-color: #e9f1f7;
    box-shadow: 0 0 4px 1px rgba(0, 0, 0, .3);
    overflow: hidden;
    z-index: 3;
    transition: all 0.3s ease 0s;
}

.sidebar-block ul{
    position: absolute;
    width: 100%;
    bottom: 0;
    margin-bottom: 50px;
}

.sidebar-block ul li{
    display: block;
    padding: 15px 30px;
    border-bottom: 1px solid #fff;
    font-size: 18px;
}

.hide-sidebar-block{
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px;
    background-color: #8db3ce;
    box-shadow: 0 0 2px 1px rgba(0, 0, 0, .3);
}

.hide-sidebar-block span{
    font-weight: bold;
    color: #fff;
}

.hide-sidebar-btn{
    color: #176093;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 30px;
    height: 30px;
    font-size: 18px;
    cursor: pointer;
}

@media (max-width: 870px) {
    nav ul{
        display: none;
    }

    .sidebar{
        display: flex;
    }

    .sidebar-block{

    }
}
*/
</style>
