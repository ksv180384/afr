<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowReactive, watch } from 'vue';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';
import { createReviewSettingsController } from '@/App/Learning/reviewSettingsController.js';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import PracticeDate from './PracticeDate.vue';

const props=defineProps({authUser:{type:Object,default:null},guestRepository:{type:Object,default:null}});
const emit=defineEmits(['saved']);
const state=shallowReactive({});
const homeStore=usePracticeHome();
const controller=createReviewSettingsController({transport:accountTransport,guestRepository:props.guestRepository,getOwner:()=>props.authUser?.id ?? null,
  onMutation:owner=>{if (!props.guestRepository?.temporary) homeStore.invalidate(owner);},state});
const clock=ref(Date.now());
let expiryTimer;
const settings=computed(()=>state.profile?.review_suggestions);
const snoozed=computed(()=>settings.value?.snoozed_until && new Date(settings.value.snoozed_until).getTime()>clock.value);
const load=()=>controller.load().catch(failure=>{state.error=failure.response?.data?.message ?? failure.message;});
const save=async(action,enabled)=>{try{await controller.save(action,enabled);emit('saved');}catch{}};
const toggle=event=>{const enabled=event.target.checked;event.target.checked=settings.value.enabled;save('toggle',enabled);};
watch(()=>settings.value?.snoozed_until,until=>{clearTimeout(expiryTimer);const delay=new Date(until).getTime()-Date.now();if(until && delay>0)expiryTimer=setTimeout(()=>{clock.value=Date.now();reload();},delay+25);});
const reload=()=>{if(!state.busy && !state.pending) load();};
let unsubscribe;
watch(()=>props.authUser?.id,()=>load());
onMounted(()=>{load();window.addEventListener('focus',reload);unsubscribe=props.guestRepository?.subscribe('review-settings-'+crypto.randomUUID(),reload);});
onBeforeUnmount(()=>{clearTimeout(expiryTimer);window.removeEventListener('focus',reload);unsubscribe?.();});
</script>
<template>
  <section class="review-settings" aria-label="Предложения повторения">
    <h2>Предложения повторения</h2>
    <p>Короткие повторения можно предлагать между занятиями. Словарь и его расписание сохраняются при отключении предложений.</p>
    <p v-if="!state.ready" role="status">Загружаем настройки…</p>
    <template v-else-if="settings?.available">
      <label><input type="checkbox" :checked="settings.enabled" :disabled="state.busy || state.pending" @change="toggle"> Предлагать короткое повторение</label>
      <p v-if="snoozed">Предложения скрыты до <practice-date :value="settings.snoozed_until" :timezone="state.profile.timezone" :relative="false" />. Включение не отменяет этот отказ.</p>
      <div><button type="button" :disabled="state.busy || state.pending || !settings.enabled || !!snoozed" @click="save('snooze_today')">Не сегодня</button></div>
      <p v-if="!authUser">{{ guestRepository?.temporary ? 'Настройки действуют до закрытия этой страницы.' : 'Настройки сохраняются в этом браузере.' }} При входе используются настройки аккаунта.</p>
    </template>
    <p v-else>Настройки повторения пока недоступны. Попробуйте позже.</p>
    <p v-if="state.busy" role="status">Сохраняем настройку…</p>
    <p v-if="state.error" role="alert">{{ state.error }}</p>
    <button v-if="state.pending" type="button" :disabled="state.busy" @click="save()">Повторить сохранение</button>
    <button v-else-if="state.error" type="button" :disabled="state.busy" @click="load">Обновить настройки</button>
  </section>
</template>
<style scoped>
.review-settings { margin:1rem 0; padding:1rem; border:1px solid #cbd5e1; background:#fff; color:#17364d; }
h2 { font-weight:bold; } p { margin:.7rem 0; line-height:1.6; } label { display:flex; align-items:center; gap:.6rem; }
button { margin-top:.75rem; padding:.45rem .8rem; border:1px solid #64748b; border-radius:.3rem; } button:disabled { opacity:.6; }
</style>
