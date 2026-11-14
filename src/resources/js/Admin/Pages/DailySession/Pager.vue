<script setup>
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({ page: Object, only: Array, pageParam: { type: String, default: 'page' } });
const current = usePage();
const change = (page) => router.get(current.url.split('?')[0], { ...Object.fromEntries(new URLSearchParams(current.url.split('?')[1] ?? '')), [props.pageParam]: page }, { preserveState: true, preserveScroll: true, only: props.only });
</script>
<template>
  <el-pagination v-if="page?.total" background layout="total, prev, pager, next" :current-page="page.current_page" :page-size="page.per_page" :total="page.total" :pager-count="5" @current-change="change" />
</template>
