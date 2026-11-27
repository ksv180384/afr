<script setup>
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';

const rules = defineModel('rules', { type: Boolean, required: true });
const consent = defineModel('consent', { type: Boolean, required: true });
defineProps({ errors: { type: Object, default: () => ({}) } });
</script>

<template>
  <fieldset class="agreements">
    <legend class="sr-only">Подтверждения для входа в аккаунт</legend>
    <div>
      <AfrCheckbox v-model="rules" name="confirmation_rules" aria-required="true" label="Я принимаю правила сайта" />
      <a :href="route('info.terms-user')" target="_blank" rel="noopener">Прочитать правила сайта</a>
      <AfrInputErrorMessage v-if="errors.confirmation_rules" role="alert">{{ errors.confirmation_rules }}</AfrInputErrorMessage>
    </div>
    <div>
      <AfrCheckbox v-model="consent" name="personal_data_consent" aria-required="true" label="Я даю согласие на обработку персональных данных" />
      <a :href="route('info.personal-data-consent')" target="_blank" rel="noopener">Прочитать текст согласия</a>
      <AfrInputErrorMessage v-if="errors.personal_data_consent" role="alert">{{ errors.personal_data_consent }}</AfrInputErrorMessage>
    </div>
    <a :href="route('info.privacy-policy')" target="_blank" rel="noopener">Политика конфиденциальности</a>
    <AfrInputErrorMessage v-if="errors.legal_version" role="alert">{{ errors.legal_version }}</AfrInputErrorMessage>
  </fieldset>
</template>

<style scoped>
.agreements { @apply grid min-w-0 gap-3 text-sm text-slate-700; }
.agreements a { @apply text-sky-800 underline underline-offset-2; }
.agreements > div > a { @apply ms-[22px] inline-block; }
.agreements :deep(.afr-checkbox) { @apply min-h-7; }
</style>
