<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useMediaQuery, useMounted } from '@vueuse/core';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import DailyPracticeIcon from '@/App/Components/Learning/DailyPracticeIcon.vue';
import useClickOutside from '@/Composables/useClickOutside.js';

const props = defineProps({
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: null },
  perPage: { type: Number, default: null },
  total: { type: Number, default: null },
  limitLinks: { type: Number, default: 1 },
  routeName: { type: String, default: null },
  compact: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['change']);
const inertiaPage = usePage();
const pageUrl = page => route(props.routeName, { ...inertiaPage.props.query, page });

const mounted = useMounted();
const smallScreen = useMediaQuery('(max-width: 600px)');
const mobileLayout = computed(() => mounted.value && smallScreen.value && !props.compact);

const controls = computed(() => {
  const { currentPage, lastPage, compact, disabled, routeName } = props;
  if (lastPage < 2) return [];

  const items = [];
  const addPage = (page, key = page, label = page, title = 'Страница ' + page) => {
    const unavailable = disabled || page < 1 || page > lastPage;
    items.push({ key, page, label, title, unavailable, href: routeName && !unavailable ? pageUrl(page) : null });
  };
  const addGap = (min, max) => {
    if (min === max) addPage(min);
    else if (min < max) items.push({ key: 'gap-' + min, min, max, title: `Выбрать страницу от ${min} до ${max}` });
  };

  const neighbors = mobileLayout.value ? 0 : 2;
  const end = Math.min(lastPage, Math.max(3, currentPage + (compact ? 1 : neighbors)));
  const start = Math.max(1, compact ? end - 2 : Math.min(lastPage - 2, currentPage - neighbors));
  addPage(currentPage - 1, 'prev', '←', 'Предыдущая страница');
  if (!compact && start > 1) {
    addPage(1);
    addGap(2, start - 1);
  }
  for (let page = start; page <= end; page++) addPage(page);
  if (!compact && end < lastPage) {
    addGap(end + 1, lastPage - 1);
    addPage(lastPage);
  }
  addPage(currentPage + 1, 'next', '→', 'Следующая страница');
  return items;
});

const nav = ref(null);
const gap = ref(null);
const pageNumber = ref('');
let trigger;
const closeJump = (restoreFocus = false) => {
  gap.value = null;
  if (restoreFocus) nextTick(() => trigger?.focus());
};
const openJump = async (item, event) => {
  trigger = event.currentTarget;
  gap.value = item;
  pageNumber.value = '';
  await nextTick();
  trigger.parentElement?.querySelector('input')?.focus();
};
const leaveJump = event => {
  if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) closeJump();
};
const selectPage = item => {
  closeJump();
  if (!item.href && !item.unavailable && item.page !== props.currentPage) emit('change', item.page);
};
const submitJump = () => {
  const page = Number(pageNumber.value);
  if (props.disabled || !gap.value || !Number.isInteger(page) || page < gap.value.min || page > gap.value.max) return;
  closeJump(true);
  if (props.routeName) router.visit(pageUrl(page));
  else emit('change', page);
};
useClickOutside(nav, () => closeJump());
watch(() => [props.currentPage, props.lastPage, props.compact, props.disabled, mobileLayout.value], () => closeJump());
</script>

<template>
  <nav v-if="controls.length" ref="nav" class="pagination" :class="{ 'pagination--compact': compact }" aria-label="Страницы">
    <ul>
      <li v-for="item in controls" :key="item.key" :class="{ 'page-gap': item.min, 'page-gap-open': gap?.key === item.key }">
        <template v-if="item.min">
          <button type="button" class="page-control" :class="{ editing: gap?.key === item.key }" :disabled="disabled"
            :title="item.title" :aria-label="item.title" @click="openJump(item, $event)">…</button>
          <form v-if="gap?.key === item.key" class="page-jump" @submit.prevent="submitJump"
            @focusout="leaveJump" @keydown.esc.stop.prevent="closeJump(true)">
            <afr-input v-model="pageNumber" size="small" native-type="number" placeholder="№"
              required step="1" :min="item.min" :max="item.max" inputmode="numeric" enterkeyhint="go"
              :aria-label="item.title" :title="item.title" />
            <afr-button class="jump-submit" native-type="submit" size="small"
              aria-label="Перейти на выбранную страницу" title="Перейти на выбранную страницу">
              <DailyPracticeIcon name="check" />
            </afr-button>
          </form>
        </template>
        <component :is="item.href ? Link : 'button'" v-else class="page-control"
          v-bind="item.href ? { href: item.href } : { type: 'button', disabled: item.unavailable }"
          :aria-label="item.title" :title="item.title" :aria-current="item.key === currentPage ? 'page' : undefined"
          @click="selectPage(item)">{{ item.label }}</component>
      </li>
    </ul>
  </nav>
</template>

<style scoped>
.pagination {
  width: fit-content;
  max-width: 100%;
  align-self: flex-start;
  border-radius: .25rem;
  background: linear-gradient(to left, #fff 75%, #ffffff00);
}
.pagination--compact { background: #fff; }
.pagination ul {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
}
.page-control {
  display: grid;
  place-items: center;
  min-width: 2.5rem;
  min-height: 1.75rem;
  padding-inline: .25rem;
  border-bottom: 2px solid transparent;
  font-variant-numeric: tabular-nums;
}
.pagination .page-control { border-radius: 0; }
.page-control[aria-current="page"] { border-color: #176093; }
.page-control:enabled:hover,
a.page-control:hover { background: #eff6ff; color: #176093; }
.page-control:disabled { opacity: .4; cursor: default; }
.page-control:focus-visible { outline: 2px solid #176093; outline-offset: -2px; }
.pagination--compact .page-control { min-width: 1.75rem; }
.page-gap { display: grid; }
.page-gap > * { grid-area: 1 / 1; }
.page-gap .editing { visibility: hidden; }
.page-jump { display: flex; min-width: 0; }
.page-jump .jump-submit { display: none; }
.page-jump :deep(.afr-input-component) { width: 0; flex: 1; }
.page-jump :deep(.afr-input-component),
.page-jump :deep(.afr-input) { height: 100%; }
.page-jump :deep(.afr-input:focus-within) { border-color: #176093; }
.page-jump :deep(input) {
  width: 100%;
  height: 100%;
  padding: 0;
  text-align: center;
  font-variant-numeric: tabular-nums;
  appearance: textfield;
}
.page-jump :deep(input::-webkit-inner-spin-button),
.page-jump :deep(input::-webkit-outer-spin-button) { appearance: none; }
@media (max-width: 600px) {
  .pagination:not(.pagination--compact) {
    width: 100%;
    background: #fff;
  }
  .pagination:not(.pagination--compact) ul { flex-wrap: nowrap; }
  .pagination:not(.pagination--compact) li { flex: 1 1 0; }
  .pagination:not(.pagination--compact) .page-control {
    width: 100%;
    min-width: 0;
    min-block-size: 44px;
  }
  .pagination:not(.pagination--compact) .page-gap-open { flex: 0 0 calc(2.75rem + 44px); }
}
@media (max-width: 600px), (pointer: coarse) {
  .page-control { min-height: 44px; }
  .page-jump :deep(.afr-input) { border-radius: .5rem 0 0 .5rem; }
  .page-jump :deep(.afr-input-component) { width: 2.75rem; flex: none; }
  .page-jump :deep(input) { font-size: 1rem; }
  .page-jump .jump-submit {
    display: grid;
    place-items: center;
    width: 44px;
    min-height: 44px;
    padding: 0;
    border: 0;
    border-radius: 0 .5rem .5rem 0;
    background: #176093;
    color: #fff;
  }
  .jump-submit :deep(svg) { margin: 0; }
  .page-jump .jump-submit:active { background: #10496f; }
  .page-jump .jump-submit:focus-visible { outline: 2px solid #fff; outline-offset: -4px; }
}
</style>
