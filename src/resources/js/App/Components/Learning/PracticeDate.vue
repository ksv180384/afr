<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { browserTimezone, practiceDate } from '@/App/Learning/practiceTime.js';
const props = defineProps({ value: String, timezone: String, time: { type: Boolean, default: true }, relative: { type: Boolean, default: true } });
const now = ref(Date.now());
let timer;
const formatted = computed(() => practiceDate(props.value, { timezone: props.timezone || browserTimezone(), time: props.time, relative: props.relative, now: now.value }));
onMounted(() => { now.value = Date.now(); timer = setInterval(() => { now.value = Date.now(); }, 60000); });
onBeforeUnmount(() => clearInterval(timer));
</script>
<template><time :datetime="value" :title="formatted.title">{{ formatted.text }}</time></template>
