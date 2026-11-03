<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { LEVELS } from '@/App/Learning/types/index.js';
const props=defineProps({authUser:Object});
const store=usePracticeHome();
const level=ref(null);
const error=ref('');
const saved=ref(false);
const retrying=ref(false);
let retryTimer;
let retryAction;
let disposed=false;
const scheduleRetry=action=>{retrying.value=true;retryAction=action;clearTimeout(retryTimer);if(!disposed)retryTimer=setTimeout(action,5000);};
const feedback=usePracticeFeedback({error,source:'practice-level-setting',status:computed(()=>saved.value ? {kind:'success',message:'Текущий уровень сохранён.'} : null)});
const ready=computed(()=>store.state.ready && store.state.owner===props.authUser?.id);
const load=async()=>{clearTimeout(retryTimer);try {
  if(store.state.profilePending) await store.changeLevel(props.authUser.id,level.value);
  await store.refresh(props.authUser.id);level.value=store.state.home.profile.current_level;error.value='';retrying.value=false;retryAction=null;
}catch(failure){if(!failure.response?.status || failure.response.status>=500) scheduleRetry(load);else error.value=failure.message;}};
const save=async()=>{
  clearTimeout(retryTimer);
  error.value=''; saved.value=false;
  try {await store.changeLevel(props.authUser.id,level.value);level.value=store.state.home.profile.current_level;saved.value=true;retrying.value=false;retryAction=null;}
  catch(failure){if(!failure.response?.status || failure.response.status>=500 || failure.response.status===409) scheduleRetry(save);else error.value=failure.response?.data?.message ?? failure.message;}
};
const reconnect=()=>retryAction?.();
onMounted(()=>{load();window.addEventListener('online',reconnect);});
onBeforeUnmount(()=>{disposed=true;clearTimeout(retryTimer);window.removeEventListener('online',reconnect);});
</script>
<template>
  <section class="level-setting" aria-labelledby="practice-level-heading">
    <h2 id="practice-level-heading">Текущий уровень</h2>
    <p v-if="!ready" role="status">Проверяем сохранённый уровень…</p>
    <form v-else @submit.prevent="save">
      <div class="practice-field"><label for="practice-current-level">Текущий уровень</label><afr-select id="practice-current-level" v-model="level" native :disabled="store.state.busy || store.state.profilePending"><afr-option v-for="item in LEVELS" :key="item.code" :value="item.code" :label="item.title" /></afr-select></div>
      <afr-button native-type="submit" type="primary" :loading="store.state.busy" :disabled="!level || retrying">Сохранить уровень</afr-button>
    </form>
    <p>Начатое занятие сохранится: его можно закончить на прежнем уровне либо отложить и начать на новом в разделе «Сегодня».</p>
    <Link href="/daily">Открыть практику на сегодня</Link>
    <p v-if="saved && !feedback.enabled" role="status">Текущий уровень сохранён.</p>
    <p v-if="retrying" role="status">Настройки сохранятся автоматически, когда появится соединение.</p>
    <p v-if="error && !feedback.enabled" role="alert">{{ error }}</p>
  </section>
</template>
<style scoped>
.level-setting { margin:1rem 0; padding:1rem; border:1px solid #cbd5e1; color:#17364d; background:white; } h2 { font-weight:bold; } p { line-height:1.6; margin:.7rem 0; }
form { display:flex; flex-wrap:wrap; gap:.5rem; align-items:end; margin-top:.7rem; } .practice-field { display:grid; gap:.25rem; min-width:150px; } label { font-size:.875rem; }
a { color:#155e91; text-decoration:underline; } :deep(button) { max-width:100%; white-space:normal; }
</style>
