<script setup>
import DailyPracticeIcon from './DailyPracticeIcon.vue';

defineProps({
  illustration: { type: String, required: true },
  badge: String,
});
</script>

<template>
  <span class="path-icon" :class="{ 'path-icon--muted': badge }" aria-hidden="true">
    <img :src="illustration" alt="" width="160" height="160" loading="lazy" decoding="async" />
    <daily-practice-icon v-if="badge" :name="badge" class="path-badge" />
  </span>
</template>

<style scoped>
.path-icon {
  position: relative;
  display: grid;
  place-items: center;
  box-sizing: border-box;
  width: var(--node-size);
  height: var(--node-size);
  border: 2px solid #fff;
  border-radius: var(--node-radius, 50%);
  background: radial-gradient(ellipse at 40% 8%, #ffffffa6, transparent 66%),
    linear-gradient(155deg, var(--node-top), var(--node-bottom));
  box-shadow: inset 0 0 0 1px var(--node-border), 0 3px 0 var(--node-edge), 0 5px 10px #25475b1a;
}
.path-icon img { width: 84%; height: 84%; object-fit: contain; }
.path-icon--muted img { filter: grayscale(1); opacity: .6; }
.path-icon .path-badge {
  position: absolute;
  right: -2px;
  bottom: -2px;
  width: 21px;
  height: 21px;
  padding: 3px;
  border: 1px solid var(--node-edge);
  border-radius: 50%;
  color: #586e79;
  background: #fff;
}
</style>
