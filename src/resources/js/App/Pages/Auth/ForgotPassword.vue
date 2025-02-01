<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useVuelidate } from '@vuelidate/core';
import { required, email, helpers } from '@vuelidate/validators';

import AfrAuthLayout from '@/App/Layouts/AfrAuthLayout.vue';
import AfrAuthCard from '@/App/Components/Card/AfrAuthCard.vue';
import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';
import AfrNotification from '@/App/Components/Notification/AfrNotification.vue';

const props = defineProps({
  status: { type: String, },
});

const form = useForm({
  email: '',
});
const rules = computed(() => {
  return {
    email: {
      required: helpers.withMessage('Заполните поле Email', required),
      email: helpers.withMessage('Неверный Email', email),
    },
  }
});
const isShowNotification = ref(!!props.status);
const errors = ref({});

const v$ = useVuelidate(rules, form);

const changeForm = async (val, fieldName) => {
  Object.keys(errors.value).forEach(key => delete errors[key]);
  if(fieldName){
    await v$.value?.[fieldName].$validate();
  }
  for(const error of v$.value.$errors){
    if(error.$message){
      errors.value[error.$property] = error.$message;
    }
  }
}

const submit = async () => {
  errors.value = {};
  const isValidForm = await v$.value.$validate();
  changeForm();
  if (!isValidForm) {
    return;
  }

  form.post(route('password.email'), {
    onError: (resErrors) => {
      for (const errorKey in resErrors) {
        errors.value[errorKey] = resErrors[errorKey];
      }
      changeForm();
    }
  });
};

watch(
  () => props.status,
  (newVal) => {
    isShowNotification.value = !!newVal;
  }
);
</script>

<template>
  <afr-auth-layout>
    <Head title="Забыли пароль" />

    <afr-auth-card title="Изменить пароль" class="w-[400px]">
      <div class="mb-4 text-sm text-gray-600">
        Забыли свой пароль? Сообщите нам свой адрес электронной почты,
        и мы вышлем вам по электронной почте ссылку для сброса пароля, которая позволит
        вам изменить пароль.
      </div>

      <afr-notification v-model="isShowNotification" :notification="status" class="mb-6"/>

      <form @submit.prevent="submit">
        <div>
          <afr-input-label
            v-model="form.email"
            label="Email"
            @change="val => changeForm(val, 'email')"
          />

          <template v-if="v$.email.$dirty && errors['email']">
            <afr-input-error-message class="-mt-3">{{ errors['email'] }}</afr-input-error-message>
          </template>
        </div>

        <div class="mt-4 flex items-center justify-end">
          <afr-button
            class="w-full"
            type="primary"
            size="large"
            native-type="submit"
            :loading="form.processing"
          >
            Получить ссылку для сброса пароля
          </afr-button>
        </div>
      </form>
    </afr-auth-card>
  </afr-auth-layout>
</template>
