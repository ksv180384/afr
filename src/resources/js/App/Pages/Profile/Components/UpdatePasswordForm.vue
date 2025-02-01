<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import InputError from '@/App/Components/InputError.vue';
import AfrNotification from '@/App/Components/Notification/AfrNotification.vue';

const refPasswordInput = ref(null);
const refCurrentPasswordInput = ref(null);

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const updatePassword = () => {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation');
        refPasswordInput.value.focus();
      }
      if (form.errors.current_password) {
        form.reset('current_password');
        refCurrentPasswordInput.value.focus();
      }
    },
  });
};
</script>

<template>
  <section class="mx-auto bg-blue-100 rounded p-4">
    <header>
      <h2 class="text-lg font-medium text-gray-900">
        Изменить пароль
      </h2>

      <p class="mt-1 text-sm text-gray-600">
        Убедитесь, что в вашей учетной записи используется длинный случайный пароль, чтобы обеспечить безопасность.
      </p>
    </header>

    <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
      <div>
<!--        <InputLabel for="current_password" value="Current Password" />-->

<!--        <TextInput-->
<!--          id="current_password"-->
<!--          ref="currentPasswordInput"-->
<!--          v-model="form.current_password"-->
<!--          type="password"-->
<!--          class="mt-1 block w-full"-->
<!--          autocomplete="current-password"-->
<!--        />-->

        <afr-input-label
          ref="refCurrentPasswordInput"
          v-model="form.current_password"
          label="Текущий пароль"
          native-type="password"
        />

        <InputError
          :message="form.errors.current_password"
        />
      </div>

      <div>
<!--        <InputLabel for="password" value="New Password" />-->

<!--        <TextInput-->
<!--          id="password"-->
<!--          ref="passwordInput"-->
<!--          v-model="form.password"-->
<!--          type="password"-->
<!--          class="mt-1 block w-full"-->
<!--          autocomplete="new-password"-->
<!--        />-->

        <afr-input-label
          ref="refPasswordInput"
          v-model="form.password"
          label="Новый пароль"
          native-type="password"
        />

        <InputError :message="form.errors.password" class="mt-2" />
      </div>

      <div>
<!--        <InputLabel-->
<!--          for="password_confirmation"-->
<!--          value="Confirm Password"-->
<!--        />-->

<!--        <TextInput-->
<!--          id="password_confirmation"-->
<!--          v-model="form.password_confirmation"-->
<!--          type="password"-->
<!--          class="mt-1 block w-full"-->
<!--          autocomplete="new-password"-->
<!--        />-->

        <afr-input-label
          v-model="form.password_confirmation"
          label="Подтвердите новый пароль"
          native-type="password"
        />

        <InputError
          :message="form.errors.password_confirmation"
          class="mt-2"
        />
      </div>

      <div class="flex flex-col items-center gap-4">
<!--        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>-->

        <afr-notification v-model="form.recentlySuccessful" notification="Пароль успешно изменен."/>
<!--        <Transition-->
<!--          :duration="10"-->
<!--          enter-active-class="transition ease-in-out"-->
<!--          enter-from-class="opacity-0"-->
<!--          leave-active-class="transition ease-in-out"-->
<!--          leave-to-class="opacity-0"-->
<!--        >-->
<!--          <p-->
<!--            v-if="form.recentlySuccessful"-->
<!--            class="text-sm text-green-500"-->
<!--          >-->
<!--            Пароль успешно изменен.-->
<!--          </p>-->
<!--        </Transition>-->

        <afr-button
          class="w-full"
          size="large"
          type="primary"
          native-type="submit"
          :loading="form.processing"
        >
          Сохранить
        </afr-button>
      </div>
    </form>
  </section>
</template>
