<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AfrAuthLayout from '@/App/Layouts/AfrAuthLayout.vue';
import AfrAuthCard from "@/App/Components/Card/AfrAuthCard.vue";
import AfrButton from "@/App/Components/Form/AfrButton.vue";

const props = defineProps({
  status: {
    type: String,
  },
});

const form = useForm({});

const submit = () => {
  form.post(route('verification.send'));
};

const verificationLinkSent = computed(
  () => props.status === 'verification-link-sent',
);
</script>

<template>
  <afr-auth-layout>
    <Head title="Подтверждение Email" />

    <afr-auth-card title="Подтверждение Email" class="w-[400px]">
      <div class="mb-4 text-sm text-gray-600">
        Спасибо за регистрацию! Мы отправили письмо со ссылкой для подтверждения на указанный Email.
        Перейдите по ссылке из письма, чтобы завершить регистрацию. Если письмо не пришло, можно отправить его повторно.
      </div>

      <div
        class="mb-4 text-sm font-medium text-green-600"
        v-if="verificationLinkSent"
      >
        Новая ссылка для подтверждения отправлена на Email, указанный при регистрации.
      </div>

      <form @submit.prevent="submit">
        <div class="mt-4 flex flex-col items-center justify-between">
<!--          <PrimaryButton-->
<!--            :class="{ 'opacity-25': form.processing }"-->
<!--            :disabled="form.processing"-->
<!--          >-->
<!--            Отправить письмо повторно-->
<!--          </PrimaryButton>-->
          <afr-button
            class="w-full"
            type="primary"
            native-type="submit"
            :loading="form.processing"
          >
            Отправить письмо повторно
          </afr-button>

          <div class="mt-3">
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="link"
            >
              Выход
            </Link>
          </div>
        </div>
      </form>
    </afr-auth-card>
  </afr-auth-layout>
</template>
