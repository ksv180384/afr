<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useVuelidate } from '@vuelidate/core';
import { helpers, required } from "@vuelidate/validators";

import AfrAuthLayout from '@/App/Layouts/AfrAuthLayout.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';
import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrAuthCard from '@/App/Components/Card/AfrAuthCard.vue';
import AfrNotification from '@/App/Components/Notification/AfrNotification.vue';
import InputError from '@/App/Components/InputError.vue';

const props = defineProps({
  canResetPassword: { type: Boolean, },
  status: { type: String, },
});

const form = useForm({
  email: '',
  password: '',
  remember: false,
});
const errors = reactive({});
const isShowNotification = ref(!!props.status);

const rules = computed(() => {
  return {
    email: {
      required: helpers.withMessage('Заполните поле Email/Имя', required),
    },
    password: {
      required: helpers.withMessage('Заполните поле Пароль', required),
    },
  }
});

const v$ = useVuelidate(rules, form);

const changeForm = async (val, fieldName) => {
  Object.keys(errors).forEach(key => delete errors[key]);
  if(fieldName){
    await v$.value?.[fieldName]?.$validate();
  }
  for(const error of v$.value.$errors){
    if(error.$params.type === 'sameAs' && !form.password_confirmation){
      continue;
    }
    if(error.$message){
      errors[error.$property] = error.$message;
    }
  }
}

const submit = async () => {
  const isValidForm = await v$.value.$validate();
  changeForm();
  if(!isValidForm){
    return;
  }

  form.post(route('login'), {
    onFinish: (res) => {
      form.reset('password')
    },
  });
};
</script>

<template>
  <afr-auth-layout>
    <Head title="Авторизация" />

    <afr-auth-card title="Авторизация" class="w-[400px]">

      <afr-notification v-model="isShowNotification" :notification="status"/>

      <form @submit.prevent="submit">
        <div>
          <afr-input-label
            v-model="form.email"
            class="my-4"
            label="Email/Имя"
            @change="val => changeForm(val, 'email')"
          />
          <template v-if="v$.email.$dirty && errors['email']">
            <afr-input-error-message class="-mt-3">{{ errors['email'] }}</afr-input-error-message>
          </template>
        </div>

        <div class="">
          <afr-input-label
            v-model="form.password"
            class="my-4"
            label="Пароль"
            native-type="password"
            @change="val => changeForm(val, 'password')"
          />
          <template v-if="v$.password.$dirty && errors['password']">
            <afr-input-error-message class="-mt-3">{{ errors['password'] }}</afr-input-error-message>
          </template>
        </div>

        <div class="mt-4 block">
          <afr-checkbox
            v-model="form.remember"
            id="confirmationRules"
            @change="changeForm(val, 'remember')"
          >
            Запомнить
          </afr-checkbox>
        </div>

        <template v-for="error in form.errors">
          <InputError class="mt-2" :message="error" />
        </template>

        <div class="my-4">
          <afr-button
            class="w-full"
            type="primary"
            size="large"
            native-type="submit"
            :loading="form.processing"
          >
            Войти
          </afr-button>
        </div>

        <div class="flex justify-between">
          <Link
            v-if="canResetPassword"
            :href="route('password.request')"
            class="link-small"
          >
            Забыли свой пароль?
          </Link>

          <Link
            :href="route('register')"
            class="link-small"
          >
            Регистрация
          </Link>
        </div>
      </form>
    </afr-auth-card>
  </afr-auth-layout>
</template>
