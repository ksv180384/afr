<script setup>
import { randomUuid } from '@/App/Learning/randomUuid.js';
import { computed, onBeforeUnmount, onMounted, ref, shallowReactive, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import InputError from '@/App/Components/InputError.vue';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';
import { createPracticeSettingsController } from '@/App/Learning/practiceSettingsController.js';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import PracticeDate from './PracticeDate.vue';
import PracticeSettingHeading from './PracticeSettingHeading.vue';

const props=defineProps({authUser:{type:Object,default:null},guestRepository:{type:Object,default:null},controller:Object,compact:Boolean});
const reviewableWords = 'выражения, которые пора повторить по расписанию или которые отмечены для повторения';
const emit=defineEmits(['saved']);
const homeStore=usePracticeHome();
const controller=props.controller ?? createPracticeSettingsController({transport:accountTransport,guestRepository:props.guestRepository,getOwner:()=>props.authUser?.id ?? null,
  onMutation:owner=>{if (!props.guestRepository?.temporary) homeStore.invalidate(owner);},state:shallowReactive({})});
const state = controller.state;
const savingReview = computed(() => state.busy && state.operation === 'review');
const error = computed(() => ['load', 'review'].includes(state.operation) ? state.error : '');
const clock=ref(Date.now());
let expiryTimer;
const settings=computed(()=>state.profile?.review_suggestions);
const snoozed=computed(()=>settings.value?.snoozed_until && new Date(settings.value.snoozed_until).getTime()>clock.value);
const load=()=>controller.load().catch(()=>{});
const save=async(action,enabled)=>{try{await controller.save(action,enabled);emit('saved');}catch{}};
watch(()=>settings.value?.snoozed_until,until=>{clearTimeout(expiryTimer);const delay=new Date(until).getTime()-Date.now();if(until && delay>0)expiryTimer=setTimeout(()=>{clock.value=Date.now();reload();},delay+25);});
const reload=()=>{if(!props.controller && !state.busy && !state.pending) load();};
let unsubscribe;
watch(()=>props.authUser?.id,()=>{if(!props.controller) load();});
onMounted(()=>{if(!props.controller){reload();window.addEventListener('focus',reload);unsubscribe=props.guestRepository?.subscribe('review-settings-'+randomUuid(),reload);}});
onBeforeUnmount(()=>{clearTimeout(expiryTimer);window.removeEventListener('focus',reload);unsubscribe?.();});
</script>
<template>
  <section class="review-settings" :class="{ 'review-settings-compact': compact }" aria-label="Предложения повторения">
    <practice-setting-heading v-if="compact" heading-id="practice-review-heading" title="Предложения повторения">
      <div class="review-explanation">
        <p>
          Если галочка включена и в словаре есть {{ reviewableWords }}, сайт показывает карточку «Вспомним …?»
          на <Link href="/daily" class="text-sky-700 underline">странице практики</Link> и на экране результатов занятия.
          В коротком повторении будет от одного до трёх заданий.
        </p>
        <p>
          В карточке указано примерное время. Нажмите «Повторить», чтобы начать, или «Не сегодня», чтобы скрыть предложения до завтра.
          После завершения короткого повторения предложения появятся снова не раньше следующего дня.
        </p>
        <p>
          Если снять галочку, карточки исчезнут. Выражения, отметки и расписание в словаре сохранятся.
          Чтобы начать повторение вручную, откройте раздел <Link href="/daily/vocabulary" class="text-sky-700 underline">«Повторять»</Link>
          и нажмите «Повторить изученное».
        </p>
      </div>
    </practice-setting-heading>
    <template v-else>
      <h2>Предложения повторения</h2>
      <p>Карточка «Вспомним …?» появляется на странице практики и после занятия, если в словаре есть {{ reviewableWords }}.</p>
    </template>
    <p v-if="!state.profile && !error" role="status">Загружаем настройки…</p>
    <template v-else-if="settings?.available">
      <afr-checkbox
        class="review-toggle w-fit"
        label="Предлагать короткое повторение"
        :model-value="settings.enabled"
        :loading="savingReview"
        :disabled="(state.busy || state.pending) && !savingReview"
        @change="save('toggle', $event)"
      />
      <p v-if="settings.enabled && snoozed">Предложения возобновятся <practice-date :value="settings.snoozed_until" :timezone="state.profile.timezone" :relative="false" />.</p>
      <p v-if="!authUser">{{ guestRepository?.temporary ? 'Настройки действуют до закрытия этой страницы.' : 'Настройки сохраняются в этом браузере.' }} При входе используются настройки аккаунта.</p>
    </template>
    <p v-else-if="state.profile">Настройки повторения пока недоступны. Попробуйте позже.</p>
    <div v-if="error && !state.busy" class="mt-2 space-y-2">
      <input-error :message="error" role="alert" />
      <afr-button v-if="state.pending" @click="save()">Повторить сохранение</afr-button>
      <afr-button v-else-if="state.operation === 'load'" @click="load">Повторить загрузку</afr-button>
    </div>
  </section>
</template>
<style scoped>
.review-settings { margin:1rem 0; padding:1rem; border:1px solid #cbd5e1; background:#fff; color:#17364d; }
h2 { font-weight:bold; } p { margin:.7rem 0; line-height:1.6; }
.review-settings-compact .review-toggle { min-height: 44px; }
.review-settings-compact p { margin-block: .5rem; font-size: .875rem; }
.review-explanation { display: grid; gap: .5rem; }
.review-explanation p { margin: 0; }
</style>
