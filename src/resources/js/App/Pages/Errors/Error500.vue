<script setup>
import ErrorLayout from '@/Components/ErrorLayout.vue';

defineProps({
  admin: { type: Boolean, default: false },
});
</script>

<template>
  <error-layout
    title="500: Внутренняя ошибка сервера"
    description="Сервер не смог обработать запрос. Попробуйте ещё раз позже."
    :admin="admin"
    show-home-link
  >
    <template #heading>
      Oups, une erreur 500...<small>Упс, ошибка 500...</small>
    </template>
    <p>
      Le serveur n'a pas pu traiter votre demande.
      <small>На сервере произошла ошибка. Не удалось открыть страницу.</small>
    </p>
    <p>
      Veuillez réessayer plus tard.
      <small>Пожалуйста, попробуйте ещё раз позже.</small>
    </p>
  </error-layout>
</template>
