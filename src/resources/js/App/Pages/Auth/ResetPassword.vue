<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { email, helpers, required, sameAs } from '@vuelidate/validators';
import { useVuelidate } from '@vuelidate/core';

import AfrAuthLayout from '@/App/Layouts/AfrAuthLayout.vue';
import AfrAuthCard from '@/App/Components/Card/AfrAuthCard.vue';
import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';

const props = defineProps({
  email: { type: String, required: true, },
  token: { type: String, required: true, },
});

const errors = ref({});
const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});
const rules = computed(() => {
  return {
    email: {
      required: helpers.withMessage('Заполните поле Email', required),
      email: helpers.withMessage('Неверный Email', email),
    },
    password: {
      required: helpers.withMessage('Заполните поле Пароль', required),
      sameAsPassword: helpers.withMessage('Неверно подтвержден пароль.', sameAs(form.password_confirmation))
    },
    password_confirmation: {
      required: helpers.withMessage('Подтвердите пароль.', required),
    },
  }
});

const v$ = useVuelidate(rules, form);

const changeForm = async (val, fieldName) => {
  Object.keys(errors.value).forEach(key => delete errors[key]);
  if(fieldName){
    await v$.value?.[fieldName].$validate();
  }
  for(const error of v$.value.$errors){
    if(error.$params.type === 'sameAs' && !form.password_confirmation){
      continue;
    }
    if(error.$message){
      errors.value[error.$property] = error.$message;
    }
  }
}

const submit = async () => {
  errors.value = {};
  const isValidForm = await v$.value.$validate();
  changeForm();
  if(!isValidForm){
    return;
  }

  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
    onError: (resErrors) => {
      for (const errorKey in resErrors) {
        errors.value[errorKey] = resErrors[errorKey];
      }
      changeForm();
    }
  });
};
</script>

<template>
  <afr-auth-layout class="reset-password-page">
    <Head title="Сбросить пароль" />

    <afr-auth-card title="Сбросить пароль" class="w-[400px]">
      <form @submit.prevent="submit">
        <div class="mt-4">
          <afr-input-label
            v-model="form.email"
            class="my-4"
            label="Email"
            @change="val => changeForm(val, 'email')"
          />
          <template v-if="v$.email.$dirty && errors['email']">
            <afr-input-error-message class="-mt-3">{{ errors['email'] }}</afr-input-error-message>
          </template>
        </div>

        <div>
          <afr-input-label
            v-model="form.password"
            class="my-4"
            label="Password"
            native-type="password"
            @change="val => changeForm(val, 'password')"
          />
          <template v-if="v$.password.$dirty && errors['password']">
            <afr-input-error-message class="-mt-3">{{ errors['password'] }}</afr-input-error-message>
          </template>
        </div>

        <div>
          <afr-input-label
            v-model="form.password_confirmation"
            class="my-4"
            label="Подтвердите пароль"
            native-type="password"
            @change="val => changeForm(val, 'password')"
          />
        </div>

        <div class="form-item mt-4 text-center">
          <afr-button
            class="w-full"
            type="primary"
            size="large"
            native-type="submit"
            :loading="form.processing"
          >
            Сбросить пароль
          </afr-button>
        </div>
      </form>
    </afr-auth-card>
  </afr-auth-layout>
</template>
