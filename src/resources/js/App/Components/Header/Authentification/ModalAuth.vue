<script setup>
import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrLoginForm from '@/App/Components/Form/AfrLoginForm.vue';

const open = defineModel({ type: Boolean, default: false });
</script>

<template>
  <afr-dialog v-model="open" width="408px">
    <template #header>
      <div class="login-heading">
        <span class="login-heading-icon" aria-hidden="true">
          <svg
            viewBox="0 0 24 24"
            class="size-5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            focusable="false"
          >
            <circle cx="12" cy="8" r="3.25" />
            <path d="M5 19v-1a4.5 4.5 0 0 1 4.5-4.5h5A4.5 4.5 0 0 1 19 18v1" />
          </svg>
        </span>
        <h2 tabindex="-1" autofocus>Вход в аккаунт</h2>
      </div>
    </template>

    <afr-login-form :registration-url="route('register')" @success="open = false" />
  </afr-dialog>
</template>

<style scoped>
.login-heading {
  @apply flex items-center gap-3;
}

.login-heading h2 {
  @apply min-w-0 text-lg leading-tight outline-none sm:text-xl;
}

.login-heading-icon {
  @apply grid size-10 shrink-0 place-items-center rounded-lg border border-sky-100 bg-white text-sky-700;
}
</style>
