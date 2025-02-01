<script setup>
import { Link } from '@inertiajs/vue3';

import AfrButtonCircleIcon from '@/App/Components/Form/AfrButtonCircleIcon.vue';

const props = defineProps({
  title: { type: String, default: '', },
});
</script>

<template>
<div class="afr-auth-card">
  <div class="header">
    <Link :href="route('index')" class="btn-go-home-page" title="На главную">
      <afr-button-circle-icon>
        <svg
          aria-hidden="true"
          focusable="false"
          data-prefix="fas"
          data-icon="chevron-circle-left"
          class="svg-inline--fa fa-chevron-circle-left fa-w-16 "
          role="img"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 512 512"
        >
          <path
            fill="currentColor"
            d="M256 504C119 504 8 393 8 256S119 8 256 8s248 111 248 248-111 248-248 248zM142.1 273l135.5 135.5c9.4 9.4 24.6 9.4 33.9 0l17-17c9.4-9.4 9.4-24.6 0-33.9L226.9 256l101.6-101.6c9.4-9.4 9.4-24.6 0-33.9l-17-17c-9.4-9.4-24.6-9.4-33.9 0L142.1 239c-9.4 9.4-9.4 24.6 0 34z"
          ></path>
        </svg>
      </afr-button-circle-icon>
    </Link>
    {{ title }}
  </div>
  <div class="content">
    <slot></slot>
  </div>
</div>
</template>

<style scoped>
.afr-auth-card{
  @apply flex flex-col;
  background-color: rgba(230, 240, 246, .7);
  padding: 30px 0 20px;
  border-radius: 8px;
  box-shadow: 0 0 0 6px rgba(53, 105, 156, 0.35);
}

.header {
  @apply relative uppercase font-bold text-center border-b border-dashed border-white;
  color: #176093;
  padding-bottom: 20px;
}

.btn-go-home-page {
  position: absolute;
  left: 30px;
  font-size: 22px;
  color: #75a7ca;
  box-shadow: 0 0 1px 2px rgba(34, 36, 38, .15);
  border-radius: 50%;
  background: #fff;
  width: 22px;
  display: inline-block;
  height: 22px;
  line-height: 0;
}

.content {
  padding: 10px 30px 0 30px;
}
</style>
