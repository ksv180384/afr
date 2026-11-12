<script setup>
import ErrorLayout from '@/Components/ErrorLayout.vue';

defineProps({
  admin: { type: Boolean, default: false },
});
</script>

<template>
  <error-layout
    title="404: Страница не найдена"
    description="К сожалению, такой страницы не существует. Проверьте адрес или вернитесь на главную."
    :admin="admin"
    show-home-link
  >
    <template #heading>
      Oups, une erreur 404...<small>Упс, ошибка 404...</small>
    </template>
    <p>
      L'adresse que vous cherchez à afficher n'est malheureusement pas disponible sur le site.
      <small>Адрес, который вы хотите отобразить, к сожалению, не доступен на сайте.</small>
    </p>
    <p>
      Veuillez vérifier votre url.
      <small>Пожалуйста, проверьте ваш URL.</small>
    </p>
  </error-layout>
</template>
