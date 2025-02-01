<script setup>
import { ref, watch, nextTick } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

import AfrDialog from '@/App/Components/Dialog/AfrDialog.vue';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import AfrInputErrorMessage from "@/App/Components/Form/AfrInputErrorMessage.vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
});
const emits = defineEmits(['update:modelValue']);

const refInputEmail = ref(null);
const form = useForm({
  email: '',
  password: '',
  remember: false,
});
const errors = ref({});

const onClose = () => {
  emits('update:modelValue', false);
}

// const submit = async () => {
//
//   isSubmitting.value = true;
//
//   try{
//     const res = await api.auth.login(formData);
//     emits('update:modelValue', false);
//   } catch (e) {
//     console.error(e);
//   } finally {
//     isSubmitting.value = false;
//   }
// }

const submit = async () => {
  errors.value = {};

  form.post(route('login'), {
    onFinish: () => {
      form.reset('password')
    },
    onError: (resErrors) => {
      for (const errorKey in resErrors) {
        errors.value[errorKey] = resErrors[errorKey];
      }
    }
  });
};

watch(
  () => props.modelValue,
  (newVal) => {
    if(newVal){
      nextTick(() => {
        refInputEmail.value.focus();
      });
    }
  }
);
</script>

<template>
  <afr-dialog
    :model-value="modelValue"
    width="300px"
    @close="onClose"
  >
    <template #header>
      <div class="uppercase text-sm font-bold text-gray-600">Авторизация</div>
    </template>
    <form
      class="form-auth"
      @submit.prevent="submit"
    >
      <div class="my-2">
        <afr-input
          ref="refInputEmail"
          v-model="form.email"
          placeholder="Ваш логин/email"
        />
        <template v-if="errors.email">
          <afr-input-error-message class="-mt-.5">{{ errors.email }}</afr-input-error-message>
        </template>
      </div>
      <div>
        <afr-input
          v-model="form.password"
          native-type="password"
          placeholder="Пароль"
        />
      </div>
      <div class="flex justify-between my-2">
        <afr-checkbox
          v-model="form.remember"
          id="remember"
          class="flex-grow"
          label="Запомнить"
        />
        <afr-button
          type="primary"
          native-type="submit"
          :loading="form.processing"
        >
          Вход
        </afr-button>
      </div>
    </form>

    <div class="flex justify-between mb-3 px-1">
      <div>
        <Link :href="route('register')" class="link-small">Регистрация</Link>
      </div>
      <div>
        <Link :href="route('password.request')" class="link-small">
          Забыли пароль?
        </Link>
      </div>
    </div>

  </afr-dialog>
</template>

<style scoped>
.form-auth{
  @apply flex flex-col gap-2;
}
</style>
