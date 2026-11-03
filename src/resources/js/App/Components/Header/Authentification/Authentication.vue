<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { prepareGuestAuthentication } from '@/App/Learning/guestSyncState.js';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import ModalAuth from '@/App/Components/Header/Authentification/ModalAuth.vue';
import ProfileControl from '@/App/Components/Header/Authentification/ProfileControl.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

const isShowDialogAuth = ref(false);

const error = ref('');
const click = async () => {
  error.value = '';
  try {
    if (await prepareGuestAuthentication()) { router.visit('/login?practice=1'); return; }
    isShowDialogAuth.value = true;
  } catch (failure) { error.value = failure.message; }
};
</script>

<template>
  <div>
    <template v-if="!authUser">
      <afr-button
        size="small"
        type="primary"
        text
        @click="click"
      >
        Войти
      </afr-button>

      <p v-if="error" role="alert">{{ error }}</p>
      <ModalAuth v-model="isShowDialogAuth"/>
    </template>
    <template v-else>
      <ProfileControl :user="authUser"/>
    </template>
  </div>
</template>

