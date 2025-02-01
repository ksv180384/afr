<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: null },
  perPage: { type: Number, default: null },
  total: { type: Number, default: null },
  limitLinks: { type: Number, default: 1 },
  routeName: { type: String, required: true },
});

const { query } = usePage().props;

const paginationLinks = computed(() => {
  const links = [];
  if (props.currentPage > 3) {
    links.push({ url: route(props.routeName, { ...query, page: 1 }), label: 1 });
    if (props.currentPage > 4) {
      links.push({ url: null, label: '...' });
    }
    for (let i = props.currentPage - 2; i < props.currentPage; i++) {
      links.push({ url: route(props.routeName, { ...query, page: i }), label: i });
    }
  } else {
    for (let i = 1; i < props.currentPage; i++) {
      links.push({ url: route(props.routeName, { ...query, page: i }), label: i });
    }
  }

  links.push({ url: route(props.routeName, { ...query, page: props.currentPage }), label: props.currentPage });

  if (props.lastPage - props.currentPage > 2) {
    for (let i = props.currentPage + 1; i <= props.currentPage + 2; i++) {
      links.push({ url: route(props.routeName, { ...query, page: i }), label: i });
    }
    if (props.lastPage - props.currentPage > 3) {
      links.push({ url: null, label: '...' });
    }
    links.push({ url: route(props.routeName, { ...query, page: props.lastPage }), label: props.lastPage });
  } else {
    for (let i = props.currentPage + 1; i <= props.lastPage; i++) {
      links.push({ url: route(props.routeName, { ...query, page: i }), label: i });
    }
  }

  return links.map(item => {
    return { ...item, active: parseInt(item.label) === parseInt(props.currentPage) };
  });
});
</script>

<template>
<div v-if="paginationLinks.length > 1" class="pagination">
  <ul>
    <li v-for="link in paginationLinks" :key="link.label" :class="{ 'active': link.active }">
      <template v-if="link.url">
        <Link :href="link.url">{{ link.label }}</Link>
      </template>
      <template v-else>
        <span>{{ link.label }}</span>
      </template>
    </li>
  </ul>
</div>
</template>

<style scoped>
.pagination{
  width: 280px;
  display: flex;
  justify-content: end;
  background: linear-gradient(to left, rgba(255, 255, 255, 1) 220px, rgba(255, 255, 255, 0) 100%);
  border-radius: 0.25rem;
}

.pagination ul{
  display: flex;
  flex-direction: row;
}

.pagination ul li{
  display: flex;
  justify-content: center;
  align-items: center;
  width: 42px;
  height: 26px;
}

.pagination ul li.active{
  border-bottom: 2px solid #176093;
}

.pagination ul li a{
  display: flex;
  width: 100%;
  justify-content: center;
}
</style>
