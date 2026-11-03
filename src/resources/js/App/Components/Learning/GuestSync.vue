<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import { GuestProgressRepository } from '@/App/Learning/guestRepository.js';
import { createAccountRepository } from '@/App/Learning/accountRepository.js';
import { hasGuestWork, syncGuestRepository, retryRejectedGuestEvents } from '@/App/Learning/guestSync.js';
import { guestSyncUi, guestSyncRevision, temporaryGuestState, clearTemporaryGuest, rememberTemporaryGuest } from '@/App/Learning/guestSyncState.js';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import { browserTimezone } from '@/App/Learning/practiceTime.js';

const page=usePage();
const owner=computed(()=>page.props?.auth?.user?.id ?? null);
const enabled=computed(()=>page.props?.daily_practice_enabled===true && !page.props?.public_practice);
const accountRepository=createAccountRepository();
let base;
let currentRepositories=[];
let stop;
let running=false;
let retryTimer;
let retryDelay=5000;
let disposed=false;
const status=value=>{
  if(value.kind==='syncing' && guestSyncUi.value.kind==='pending') return;
  if(value.kind!==guestSyncUi.value.kind || value.message!==guestSyncUi.value.message) guestSyncUi.value=value;
};
const run=async (retryRejected=false)=>{
  if(running || !owner.value || !enabled.value || disposed) return;
  clearTimeout(retryTimer);
  const actor=owner.value;
  running=true;
  try {
    await base.archiveBound(actor);
    const keys=['current',...await base.ownerKeys(actor)];
    const sources=keys.map((key)=>new GuestProgressRepository({key}));
    if(temporaryGuestState()) {
      const temporary=new GuestProgressRepository();
      temporary.useTemporary(); temporary.cached=structuredClone(temporaryGuestState());
      sources.unshift(temporary);
    }
    currentRepositories=sources;
    let worked=false;
    let partial=false; const rejections=[];
    let target=null;
    for(const repository of sources) {
      const source=await repository.read();
      if(!hasGuestWork(source)) continue;
      if((source.bound_user_id ?? source.sync?.owner_user_id ?? actor)!==actor) continue;
      if(retryRejected) await repository.mutate((state)=>retryRejectedGuestEvents(state));
      if(!source.outbox.length && !source.return_run_id && !source.return_path && !source.sync?.batches.some((batch)=>batch.status==='pending')) continue;
      worked=true;
      const result=await syncGuestRepository({ownerUserId:actor,repository,accountRepository,transport:accountTransport,getOwner:()=>owner.value,onStatus:status});
      partial ||= !!result.rejected?.length; rejections.push(...(result.state.sync?.rejected ?? []));
      const requested=result.state.return_run_id;
      if(requested && result.mapping[requested]) {
        target='/daily/run/'+result.mapping[requested].server_run_id;
        await repository.mutate((state)=>{state.return_run_id=null;});
      }
      if(!target && ['/daily','/daily/vocabulary'].includes(result.state.return_path)) target=result.state.return_path;
      await repository.mutate((state)=>{state.return_path=null;});
      if(repository.temporary && !result.rejected?.length) { clearTemporaryGuest(); repository.syncComplete=true; }
    }
    if(owner.value!==actor) return;
    retryDelay=5000;
    if(worked) {
      guestSyncRevision.value++;
      if(partial) status({kind:'partial',message:'Часть гостевых данных не перенесена. Они остались в этом браузере.',rejected:rejections});
      if(!partial) status({kind:'synced',message:'Гостевой прогресс перенесён в аккаунт.'});
      if(target && page.url.split('?')[0]!==target) router.visit(target);
    }
  } catch(error) {
    const auth=[401,419].includes(error.response?.status) || error.code==='owner_changed';
    if(owner.value===actor) status({kind:auth ? 'auth' : 'pending',
      message:auth ? 'Войдите в свой аккаунт, чтобы перенести прогресс.' : 'Прогресс остался в этом браузере. Перенесём его автоматически, когда восстановится соединение.'});
    if(!auth && !disposed) { retryTimer=setTimeout(()=>run(),retryDelay); retryDelay=Math.min(retryDelay*2,60000); }
  } finally {
    running=false;
    for(const repository of currentRepositories) { if(repository.temporary && !repository.syncComplete) rememberTemporaryGuest(await repository.read()); repository.close(); }
    currentRepositories=[];
    if(owner.value && owner.value!==actor && enabled.value) queueMicrotask(()=>run());
  }
};
const dismiss=()=>status({kind:'idle',message:''});
const online=()=>{if(['pending','error','auth','partial'].includes(guestSyncUi.value.kind)) run(guestSyncUi.value.kind==='partial');};
const feedback=usePracticeFeedback({
  source:'practice-guest-sync',
  status:computed(()=>enabled.value && guestSyncUi.value.message ? {
    ...guestSyncUi.value,
    message:[guestSyncUi.value.message,...(guestSyncUi.value.rejected ?? []).map(entry=>entry.message),
      ...(['error','auth','partial'].includes(guestSyncUi.value.kind) ? ['Неподтверждённые данные не удалены из браузера.'] : [])].join('\n'),
  } : null),
  actions:computed(()=>[
    ...(guestSyncUi.value.kind==='auth' ? [{label:'Войти в тот же аккаунт',run:()=>router.visit('/login?practice=1')}] : []),
  ]),
});
onMounted(()=>{
  base=new GuestProgressRepository({timezone:browserTimezone()});
  base.subscribe('sync-'+crypto.randomUUID(),()=>{});
  stop=watch(()=>[owner.value,enabled.value],async()=>{
    status({kind:'idle',message:''});
    if(!enabled.value) return;
    if(owner.value) await run();
    else try { await base.archiveBound(); } catch(error) {status({kind:'error',message:'Не удалось отделить гостевой прогресс от кеша аккаунта. Повторите действие.'});}
  },{immediate:true});
  window.addEventListener('online',online);
});
onBeforeUnmount(()=>{disposed=true;clearTimeout(retryTimer);stop?.();window.removeEventListener('online',online);base?.close();});
</script>
<template>
  <aside v-if="!feedback.enabled && enabled && guestSyncUi.message" class="guest-sync-notice" role="status" aria-live="polite">
    <p>{{ guestSyncUi.message }}</p>
    <ul v-if="guestSyncUi.rejected?.length"><li v-for="(entry,index) in guestSyncUi.rejected" :key="index">{{ entry.message }}</li></ul>
    <p v-if="['error','auth','partial'].includes(guestSyncUi.kind)">Неподтверждённые данные не удалены из браузера.</p>
    <div class="actions">
      <a v-if="guestSyncUi.kind === 'auth'" href="/login?practice=1" @click.prevent="router.visit('/login?practice=1')">Войти в тот же аккаунт</a>
      <afr-button v-if="guestSyncUi.kind !== 'syncing'" @click="dismiss">Закрыть</afr-button>
    </div>
  </aside>
</template>
<style scoped>
.guest-sync-notice { position:fixed; bottom:1rem; right:1rem; z-index:1000; width:28rem; max-width:calc(100vw - 2rem); max-height:45vh; overflow:auto; padding:1rem; border:1px solid #64748b; border-radius:.25rem; background:#eff6ff; color:#17364d; box-shadow:0 3px 12px #0002; }
p { margin:.3rem 0; line-height:1.5; }.actions { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.6rem; } a { color:#155e91; text-decoration:underline; }
:deep(button) { min-height:44px; white-space:normal; }
</style>
