<script setup>
import { ref, useId, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import AfrLegalAgreements from '@/App/Components/Form/AfrLegalAgreements.vue';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';

defineProps({
  registrationUrl: { type: String, required: true },
  canResetPassword: { type: Boolean, default: true },
});
const emit = defineEmits(['success']);

const id = useId();
const needsAgreements = ref(false);
const passwordVisible = ref(false);
const form = useForm({
  email: '',
  password: '',
  remember: false,
  confirmation_rules: false,
  personal_data_consent: false,
  legal_version: usePage().props.legalVersion,
});

watch(() => form.email, () => {
  needsAgreements.value = false;
  form.reset('confirmation_rules', 'personal_data_consent');
  form.clearErrors('confirmation_rules', 'personal_data_consent', 'legal_version');
});

const submit = (url) => {
  if (form.processing) return;

  form.post(url, {
    onSuccess: () => {
      form.reset('password');
      emit('success');
    },
    onError: (errors) => {
      if (errors.confirmation_rules || errors.personal_data_consent || errors.legal_version) {
        if (!needsAgreements.value) {
          form.clearErrors('confirmation_rules', 'personal_data_consent');
        }
        needsAgreements.value = true;
      } else {
        form.reset('password');
      }
    },
    onFinish: () => {
      passwordVisible.value = false;
    },
  });
};
</script>

<template>
  <form
    class="login-form"
    :action="route('login')"
    method="post"
    @submit.prevent="submit($event.currentTarget.action)"
  >
    <div class="login-field">
      <afr-input
        :id="id + '-email'"
        v-model="form.email"
        label="Логин или email"
        name="email"
        autocomplete="username"
        autocapitalize="none"
        :spellcheck="false"
        required
        :aria-invalid="form.errors.email ? 'true' : undefined"
        :aria-describedby="form.errors.email ? id + '-email-error' : undefined"
        @update:model-value="form.clearErrors('email')"
      />
      <afr-input-error-message
        v-if="form.errors.email"
        :id="id + '-email-error'"
        role="alert"
      >
        {{ form.errors.email }}
      </afr-input-error-message>
    </div>

    <div class="login-field">
      <afr-input
        :id="id + '-password'"
        v-model="form.password"
        label="Пароль"
        name="password"
        :native-type="passwordVisible ? 'text' : 'password'"
        autocomplete="current-password"
        autocapitalize="none"
        :spellcheck="false"
        required
        :aria-invalid="form.errors.password ? 'true' : undefined"
        :aria-describedby="form.errors.password ? id + '-password-error' : undefined"
        @update:model-value="form.clearErrors('password')"
      >
        <template #suffix>
          <button
            type="button"
            class="password-toggle"
            :aria-label="passwordVisible ? 'Скрыть пароль' : 'Показать пароль'"
            :aria-controls="id + '-password'"
            @click="passwordVisible = !passwordVisible"
          >
            <svg
              viewBox="0 0 24 24"
              class="size-5"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
              focusable="false"
            >
              <template v-if="passwordVisible">
                <path d="M3 3l18 18M10.7 5.1 12 5c6 0 10 7 10 7a16 16 0 0 1-2.2 2.8" />
                <path d="M6.4 6.8A19.2 19.2 0 0 0 2 12s4 7 10 7a10 10 0 0 0 5.1-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2" />
              </template>
              <template v-else>
                <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z" />
                <circle cx="12" cy="12" r="3" />
              </template>
            </svg>
          </button>
        </template>
      </afr-input>
      <afr-input-error-message
        v-if="form.errors.password"
        :id="id + '-password-error'"
        role="alert"
      >
        {{ form.errors.password }}
      </afr-input-error-message>
    </div>

    <div class="login-options">
      <afr-checkbox
        :id="id + '-remember'"
        v-model="form.remember"
        class="min-h-11 text-slate-700"
        name="remember"
        label="Запомнить меня"
      />
      <Link v-if="canResetPassword" :href="route('password.request')" class="link-small login-link">
        Забыли пароль?
      </Link>
    </div>

    <p v-if="needsAgreements" class="text-sm text-slate-700" role="status">
      Для этого аккаунта нужно подтвердить действующие документы.
    </p>
    <AfrLegalAgreements
      v-if="needsAgreements"
      class="border-t border-sky-100 pt-4"
      v-model:rules="form.confirmation_rules"
      v-model:consent="form.personal_data_consent"
      :errors="form.errors"
      @update:rules="form.clearErrors('confirmation_rules')"
      @update:consent="form.clearErrors('personal_data_consent')"
    />

    <afr-button
      class="login-submit w-full"
      type="primary"
      native-type="submit"
      :loading="form.processing"
      :disabled="needsAgreements && (!form.confirmation_rules || !form.personal_data_consent)"
      :aria-busy="form.processing"
    >
      {{ form.processing ? 'Входим…' : needsAgreements ? 'Подтвердить и войти' : 'Войти' }}
    </afr-button>

    <div class="login-footer">
      <Link :href="registrationUrl" class="link-small login-link">Создать аккаунт</Link>
    </div>
  </form>
</template>

<style scoped>
.login-form {
  @apply flex flex-col gap-4;
}

.login-field {
  @apply flex flex-col gap-1.5;
}

.login-field :deep(label) {
  @apply mb-1.5 block text-sm font-semibold text-sky-950;
}

.login-field :deep(.afr-input) {
  @apply min-h-12 rounded-lg border-sky-200 bg-white shadow-sm
    focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100;
}

.login-field :deep(.afr-input:has([aria-invalid='true'])) {
  @apply border-red-500;
}

.login-field :deep(input) {
  @apply min-h-11 bg-transparent text-base text-slate-700 outline-none;
}

.login-field :deep(input[type='password']::-ms-reveal) {
  display: none;
}

.password-toggle {
  @apply me-0.5 grid size-11 place-items-center rounded-md text-sky-700 hover:bg-sky-50
    focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sky-500;
}

.login-options {
  @apply -my-1 flex flex-wrap items-center justify-between gap-x-2;
}

.login-link {
  @apply inline-flex min-h-11 items-center focus-visible:outline focus-visible:outline-2
    focus-visible:outline-offset-2 focus-visible:outline-sky-500;
}

.login-form .login-submit {
  @apply min-h-11 rounded-md bg-sky-700 text-sm font-semibold text-white shadow-sm hover:bg-sky-800
    focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500;
}

.login-footer {
  @apply flex justify-center border-t border-sky-100 pt-1;
}

.login-footer .login-link {
  @apply font-semibold;
}
</style>
