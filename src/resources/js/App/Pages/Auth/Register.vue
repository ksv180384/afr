<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useVuelidate } from '@vuelidate/core';
import { required, email, minLength, sameAs, helpers } from '@vuelidate/validators';

import AfrAuthLayout from '@/App/Layouts/AfrAuthLayout.vue';
import AfrInputLabel from '@/App/Components/Form/AfrInputLabel.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';
import AfrNotification from '@/App/Components/Notification/AfrNotification.vue';
import PrivacyPolicyModal from '@/App/Pages/Auth/Components/AfrPrivacyPolicyModal.vue';
import UserAgreementModal from '@/App/Pages/Auth/Components/AfrUserAgreementModal.vue';
import AfrAuthCard from '@/App/Components/Card/AfrAuthCard.vue';

const form = useForm({
  email: '',
  name: '',
  password: '',
  password_confirmation: '',
  confirmation_rules: false,
});
const rules = computed(() => {
  return {
    email: {
      required: helpers.withMessage('Заполните поле Email', required),
      email: helpers.withMessage('Неверный Email', email),
    },
    name: { required: helpers.withMessage('Заполните поле Имя', required) },
    password: {
      required: helpers.withMessage('Заполните поле Пароль', required),
      sameAsPassword: helpers.withMessage('Неверно подтвержден пароль.', sameAs(form.password_confirmation))
    },
    password_confirmation: {
      required: helpers.withMessage('Подтвердите пароль.', required),
    },
    confirmation_rules: {
      isTrue: helpers.withMessage('Подтвердите принятие соглашений.', value => value === true),
    },
  }
});

const isOpenUserAgreementModal = ref(false);
const isOpenPrivacyPolicyModal = ref(false);
const errors = ref({});
const notification = ref('');
const isShowNotification = ref(false);

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

const openUserAgreementModal = (e) => {
  e.preventDefault();
  isOpenUserAgreementModal.value = true;
}

const openPrivacyPolicyModal = (e) => {
  e.preventDefault();
  isOpenPrivacyPolicyModal.value = true;
}

const submit = async (e) => {
  errors.value = {};
  const isValidForm = await v$.value.$validate();
  changeForm();
  if (!isValidForm) {
    return;
  }

  form.post(route('register'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation', 'confirmation_rules');
    },
    onError: (resErrors) => {
      for (const errorKey in resErrors) {
        errors.value[errorKey] = resErrors[errorKey];
      }
      changeForm();
    }
  });
}
</script>

<template>
  <afr-auth-layout class="registration-page">

    <Head title="Регистрация" />

    <afr-auth-card title="Регистрация" class="w-[400px]">
      <form @submit.prevent="submit">
        <div>
          <div class="">
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
          <div class="">
            <afr-input-label
              v-model="form.name"
              class="my-4"
              label="Имя/Логин"
              @change="val => changeForm(val, 'name')"
            />
            <template v-if="v$.name.$dirty && errors['name']">
              <afr-input-error-message class="-mt-3">{{ errors['name'] }}</afr-input-error-message>
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
            <template v-if="v$.password.$dirty && errors['password'] || v$.password_confirmation.$dirty && errors['password_confirmation']">
              <afr-input-error-message class="-mt-3">{{ errors['password'] || errors['password_confirmation'] }}</afr-input-error-message>
            </template>
          </div>
          <div class="">
            <afr-input-label
              v-model="form.password_confirmation"
              class="my-4"
              label="Подтвердите пароль"
              native-type="password"
              @change="val => changeForm(val, 'password')"
            />
          </div>
        </div>

        <div class="mt-6">
          <afr-checkbox
            v-model="form.confirmation_rules"
            id="confirmationRules"
            @change="changeForm(val, 'confirmation_rules')"
          >
            Подтверждаю, что ознакомился и принимаю
          </afr-checkbox>
          <div class="label-personal-data-protection-policy">
            <a href="#" class="link-small" @click="openUserAgreementModal">Правила пользовательского соглашения</a> и
            <a href="#" class="link-small" @click="openPrivacyPolicyModal">Политику по защите персональных данных</a>.
          </div>
        </div>

        <template v-if="errors['confirmation_rules']">
          <afr-input-error-message class="mt-4">{{ errors['confirmation_rules'] }}</afr-input-error-message>
        </template>

        <afr-notification v-model="isShowNotification" :notification="notification"/>

        <div v-if="notification" class="text-center mt-4">
          <Link :href="route('index')" class="link-small">На главную</Link>
        </div>

        <div class="form-item mt-4 text-center">
          <afr-button
            class="w-full"
            type="primary"
            size="large"
            native-type="submit"
            :loading="form.processing"
          >
            Зарегистрироваться
          </afr-button>
        </div>
      </form>
      <UserAgreementModal v-model="isOpenUserAgreementModal"/>
      <PrivacyPolicyModal v-model="isOpenPrivacyPolicyModal"/>
    </afr-auth-card>
  </afr-auth-layout>
</template>

<style scoped>
.label-personal-data-protection-policy{
  @apply text-sm;
  color: #6A6A6A;
}

.label-personal-data-protection-policy a{
  text-decoration: underline;
}

.label-personal-data-protection-policy.error{
  color: #ca3333;
}

.label-personal-data-protection-policy.error a{
  color: red;
}

@media (max-width: 870px) {
  .panel-registration{
    display: flex;
    flex-direction: column;
    flex-wrap: wrap;
  }

  .panel-registration-header{
    width: 100%;
    font-size: 14px;
  }

  .panel-registration-content{
    display: flex;
    align-items: center;
  }

  .btn-go-home-page{
    font-size: 30px;
    width: 22px;
    height: 22px;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .panel-registration-content>form{
    width: 100%;
  }

  .info-terms-user-block .content{
    padding: 10px !important;
    font-size: 14px !important;
  }

  .info-terms-user-block .content p, .info-page-block p{
    font-size: 14px !important;
  }
}
</style>
