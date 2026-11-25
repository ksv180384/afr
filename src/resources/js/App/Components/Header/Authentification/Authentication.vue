<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { prepareGuestAuthentication } from '@/App/Learning/guestSyncState.js';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import ModalAuth from '@/App/Components/Header/Authentification/ModalAuth.vue';
import ProfileControl from '@/App/Components/Header/Authentification/ProfileControl.vue';

defineProps({
  authUser: { type: Object, default: null },
});

const loginTrigger = ref(null);
const isShowDialogAuth = ref(false);
const preparing = ref(false);
const error = ref('');

const openLogin = async () => {
  if (preparing.value) return;

  error.value = '';
  preparing.value = true;
  try {
    if (await prepareGuestAuthentication()) {
      router.visit('/login?practice=1');
    } else if (loginTrigger.value) {
      loginTrigger.value.$el.focus({ preventScroll: true });
      isShowDialogAuth.value = true;
    }
  } catch (failure) {
    error.value = failure.message;
  } finally {
    preparing.value = false;
  }
};
</script>

<template>
  <div class="header-authentication">
    <template v-if="!authUser">
      <afr-button
        ref="loginTrigger"
        class="login-trigger"
        size="small"
        text
        aria-haspopup="dialog"
        :aria-expanded="isShowDialogAuth"
        :aria-busy="preparing"
        @click="openLogin"
      >
        <svg
          viewBox="0 0 24 24"
          class="size-4 shrink-0"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          focusable="false"
        >
          <path d="M13 5h5a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5M4 12h10m-4-4 4 4-4 4" />
        </svg>
        <span>Войти</span>
      </afr-button>

      <p v-if="error" class="login-trigger-error" role="alert">{{ error }}</p>
      <ModalAuth v-model="isShowDialogAuth"/>
    </template>
    <template v-else>
      <ProfileControl :user="authUser"/>
    </template>
  </div>
</template>

<style scoped>
.header-authentication {
  @apply relative;
}

.header-authentication .login-trigger[aria-haspopup='dialog'] {
  @apply min-h-11 gap-1.5 rounded-md px-3 text-sm font-semibold text-sky-900
    hover:bg-sky-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
    focus-visible:outline-sky-500;
}

.header-authentication .login-trigger[aria-expanded='true'] {
  @apply bg-sky-50;
}

.login-trigger-error {
  @apply absolute end-0 top-full mt-2 w-64 max-w-[calc(100vw-6rem)] rounded-lg border border-red-200
    bg-white p-3 text-sm text-red-700 shadow-lg;
}
</style>

