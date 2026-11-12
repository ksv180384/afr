<script setup>
import SeoHead from '@/App/Components/Seo/SeoHead.vue';

defineProps({
  title: { type: String, required: true },
  description: { type: String, required: true },
  admin: { type: Boolean, default: false },
  showHomeLink: { type: Boolean, default: false },
});
</script>

<template>
  <seo-head
    v-if="!admin"
    :title="title"
    :description="description"
    :no-index="true"
  />

  <div class="error-indicator" :class="{ 'error-indicator--admin': admin }">
    <div class="error-indicator-content">
      <div class="error-indicator-header">
        <slot name="heading">{{ title }}</slot>
      </div>
      <slot>
        <p>{{ description }}</p>
      </slot>
      <p v-if="admin || showHomeLink">
        <a v-if="admin" href="/admin" class="link">Перейти в панель администратора</a>
        <a v-else href="/" class="link">Перейти на главную страницу</a>
      </p>
    </div>
  </div>
</template>

<style scoped>
.error-indicator {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
}

.error-indicator--admin {
  min-height: 50vh;
}

.error-indicator-content {
  padding: 10px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.3);
  -webkit-backdrop-filter: blur(3px) saturate(120%);
  backdrop-filter: blur(3px) saturate(120%);
  box-shadow: 0 0 8px 2px rgba(34, 36, 38, 0.25);
}

.error-indicator--admin .error-indicator-content {
  padding: 24px;
  background: rgba(255, 255, 255, 0.9);
  -webkit-backdrop-filter: none;
  backdrop-filter: none;
}

.error-indicator-header {
  font-size: 30px;
  font-weight: bold;
  margin-bottom: 20px;
}

.error-indicator--admin .error-indicator-header {
  font-size: 24px;
  margin-bottom: 16px;
}

.error-indicator-content :deep(small) {
  display: block;
  font-size: 12px;
}

.error-indicator-content :deep(p) {
  font-size: 16px;
  margin-bottom: 10px;
}

.error-indicator--admin .link {
  @apply text-sky-600 hover:underline;
}
</style>
