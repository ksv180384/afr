<script setup>
import { ref } from 'vue';

import AfrButton from '@/App/Components/Form/AfrButton.vue';
import ModalAuth from '@/App/Components/Header/Authentification/ModalAuth.vue';
import ProfileControl from '@/App/Components/Header/Authentification/ProfileControl.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

const isShowDialogAuth = ref(false);

const click = () => {
  isShowDialogAuth.value = true;
}
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

      <ModalAuth v-model="isShowDialogAuth"/>
    </template>
    <template v-else>
      <ProfileControl :user="authUser"/>
    </template>
  </div>
</template>

