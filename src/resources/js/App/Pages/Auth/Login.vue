<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import AfrAuthLayout from '@/App/Layouts/AfrAuthLayout.vue';
import AfrAuthCard from '@/App/Components/Card/AfrAuthCard.vue';
import AfrLoginForm from '@/App/Components/Form/AfrLoginForm.vue';
import AfrNotification from '@/App/Components/Notification/AfrNotification.vue';

const props = defineProps({
  canResetPassword: Boolean,
  status: String,
  registrationUrl: { type: String, required: true },
});

const isShowNotification = ref(!!props.status);
</script>

<template>
  <afr-auth-layout>
    <Head title="Авторизация" />

    <afr-auth-card title="Авторизация" class="w-[400px] max-w-full">
      <afr-notification
        v-model="isShowNotification"
        class="mb-4"
        :notification="status"
      />
      <afr-login-form
        :can-reset-password="canResetPassword"
        :registration-url="registrationUrl"
      />
    </afr-auth-card>
  </afr-auth-layout>
</template>
