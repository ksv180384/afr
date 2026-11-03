<script setup>
import { router, usePage } from '@inertiajs/vue3';
defineProps({ page: Object });
const current = usePage();
const change = (page) => router.get(current.url.split('?')[0], { ...Object.fromEntries(new URLSearchParams(current.url.split('?')[1] ?? '')), page }, { preserveState: true, preserveScroll: true });
</script>
<template>
  <el-pagination v-if="page?.total" background layout="total, prev, pager, next" :current-page="page.current_page" :page-size="page.per_page" :total="page.total" :pager-count="5" @current-change="change" />
</template>
