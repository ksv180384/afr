<script setup>
import { computed } from 'vue';

const props = defineProps({status: Number})

const title = computed(() => {
  return {
    503: '503: Сервис недоступен',
    500: '500: Внутренняя ошибка сервера',
    404: '404: Страница не найдена',
    403: '403: Доступ запрещён',
  }[props.status];
});

const description = computed(() => {
  return {
    503: 'Извините, сервис временно недоступен из-за технических работ. Попробуйте зайти позже.',
    500: 'Упс! Произошла ошибка на нашей стороне. Мы уже работаем над решением.',
    404: 'К сожалению, такой страницы не существует. Проверьте адрес или вернитесь на главную.',
    403: 'У вас нет доступа к этой странице. Если это ошибка, свяжитесь с поддержкой.',
  }[props.status];
});
</script>

<template>
  <template v-if="status === 404">
    <div class="error-indicator">
      <div class="error-indicator-content">
        <div class="error-indicator-header">
          Oups, une erreur 404...<small>Упс, ошибка 404...</small>
        </div>
        <div class="error-indicator-text">
          L'adresse que vous cherchez à afficher n'est malheureusement pas disponible sur
          le site.<small>Адрес, который вы хотите отобразить, к сожалению, не доступен на сайте.</small>
        </div>
        <div class="error-indicator-text">
          Veuillez vérifier votre url.<small>Пожалуйста, проверьте ваш URL.</small>
        </div>
        <div class="error-indicator-text">
          <a href="/" class="link">Прейти на главную страницу</a>
        </div>
      </div>
    </div>
  </template>
  <template v-else>
    <div>
      <h1>{{ title }}</h1>
      <div>{{ description }}</div>
    </div>
  </template>
</template>

<style scoped>
.error-indicator {
  height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
}

.error-indicator-content {
  padding: 10px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.3);
  -webkit-backdrop-filter: blur(3px) saturate(120%);
  backdrop-filter: blur(3px) saturate(120%);
  box-shadow: 0 0 8px 2px rgba(34, 36, 38, .25);
}

.error-indicator-header {
  font-size: 30px;
  font-weight: bold;
  margin-bottom: 20px;
}

.error-indicator small {
  display: block;
  font-size: 12px;
}

.error-indicator-text {
  font-size: 16px;
  margin-bottom: 10px;
}
</style>
