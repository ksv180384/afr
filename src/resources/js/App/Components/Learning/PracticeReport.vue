<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrTextarea from '@/App/Components/Form/AfrTextarea.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { reportPracticeProblem } from '@/App/Services/Api/daily_practice.js';
const props=defineProps({versionId:Number,taskId:String,runId:String,answer:Object,compact:Boolean});
const inputId='practice-report-'+getCurrentInstance().uid;
const page=usePage();
const userId=computed(()=>page.props?.auth?.user?.id ?? null);
const text=ref(''); const includeAnswer=ref(false); const pending=ref(null); const busy=ref(false); const message=ref('');
const messageKind=ref('info');
const dialog = ref(null);
const openReport = () => { if (messageKind.value === 'success') message.value = ''; dialog.value?.showModal(); };
const closeReport = () => { if (!busy.value) dialog.value?.close(); };
const closeOnBackdrop = event => {
  if (event.target !== dialog.value) return;
  const rect = dialog.value.getBoundingClientRect();
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closeReport();
};
watch(userId,()=>{text.value='';includeAnswer.value=false;pending.value=null;message.value='';dialog.value?.close();});
const canAttach=computed(()=>props.taskId && props.answer && Object.keys(props.answer).length>0);
const submit=async()=>{
  if(busy.value || !userId.value || !text.value.trim()) return;
  const owner=userId.value;
  busy.value=true; message.value='';
  pending.value ??= {client_report_id:crypto.randomUUID(),session_version_id:props.versionId,task_id:props.taskId ?? null,
    ...(props.runId ? {run_id:props.runId}:{}),text:text.value,include_answer:includeAnswer.value===true,
    ...(includeAnswer.value && canAttach.value ? {answer:JSON.parse(JSON.stringify(props.answer))}:{})};
  try {
    const response=await reportPracticeProblem(pending.value);
    if(userId.value!==owner) return;
    messageKind.value='success';
    message.value='Спасибо! Мы проверим это задание. Номер сообщения: '+response.report_id+'.';
    pending.value=null; text.value=''; includeAnswer.value=false;
  } catch(error) { if(userId.value===owner) {messageKind.value='error'; message.value=error.response?.data?.message ?? 'Не удалось отправить сообщение. Текст сохранён в открытой форме.';} }
  finally {busy.value=false;}
};
const edit=()=>{pending.value=null;message.value='';};
</script>
<template>
  <div v-if="userId" class="practice-report" :class="{ 'report-compact': compact }">
    <button type="button" class="report-trigger" :aria-label="compact ? 'Сообщить об ошибке' : undefined" :title="compact ? 'Сообщить об ошибке' : undefined" @click="openReport">
      <daily-practice-icon name="message" /><span v-if="!compact">Сообщить об ошибке</span>
    </button>
    <dialog ref="dialog" class="report-dialog" :aria-labelledby="inputId + '-title'" :aria-describedby="inputId + '-description'" @cancel.prevent="closeReport" @click="closeOnBackdrop">
      <div class="report-heading"><h2 :id="inputId + '-title'">{{ taskId ? 'Что-то не так с заданием?' : 'Что-то не так с занятием?' }}</h2><button type="button" class="close-report" :disabled="busy" aria-label="Закрыть" @click="closeReport"><daily-practice-icon name="close" /></button></div>
      <p :id="inputId + '-description'" class="report-description">Расскажите, где ошибка: в тексте, переводе, звуке или проверке ответа.</p>
      <form v-show="messageKind !== 'success' || !message" @submit.prevent="submit">
        <afr-textarea :id="inputId" v-model="text" label="Описание ошибки" placeholder="Например, звук не совпадает с фразой…" required maxlength="4000" rows="4" :disabled="busy || !!pending" />
        <afr-checkbox v-if="canAttach" :id="inputId+'-answer'" v-model="includeAnswer" class="consent" :disabled="busy || !!pending">Приложить мой ответ</afr-checkbox>
        <p class="report-note">{{ taskId ? 'Задание прикрепится автоматически.' : 'Занятие прикрепится автоматически.' }} <span v-if="canAttach">Ваш ответ прикрепится, если вы отметите пункт выше.</span></p>
        <div class="report-actions"><afr-button v-if="pending && !busy" @click="edit">Изменить сообщение</afr-button><afr-button v-else :disabled="busy" @click="closeReport">Отмена</afr-button><afr-button native-type="submit" type="primary" :loading="busy" :disabled="!text.trim()">{{ pending && !busy ? 'Повторить отправку' : 'Отправить сообщение' }}</afr-button></div>
      </form>
      <p v-if="message" class="report-status" :class="messageKind" role="status">{{ message }}</p>
      <afr-button v-if="message && messageKind === 'success'" class="report-done" @click="closeReport">Готово</afr-button>
    </dialog>
  </div>
</template>
<style scoped>
.practice-report { display: flex; justify-content: center; flex-shrink: 0; padding-top: .25rem; overflow-wrap: anywhere; }
.report-trigger { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; color: #73869a; font-size: .75rem; min-height: 32px; }
.report-trigger :deep(svg) { width: 15px; height: 15px; }
.report-trigger:hover { color: #165ca7; }
.report-compact { padding-top: 0; }
.report-compact .report-trigger { width: var(--practice-target, 34px); border-radius: .4rem; color: #51718e; }
.report-compact .report-trigger:hover { background: #edf3f9; color: #165ca7; }
.report-dialog { width: min(480px, calc(100vw - 2rem)); max-height: calc(100svh - 2rem); overflow: auto; margin: auto; padding: 1.5rem; border: 1px solid #d5e1ec; border-radius: 1rem; color: #18344c; background: #fff; box-shadow: 0 24px 80px #18344c30; }
.report-dialog::backdrop { background: #162c4560; backdrop-filter: blur(3px); }
.report-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.report-heading h2 { font-size: 1.2rem; line-height: 1.4; font-weight: 700; }
.close-report { display: grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: .5rem; }
.close-report:hover { background: #edf3f9; }
.report-description { color: #64748b; margin: .65rem 0 1.25rem; font-size: .9rem; line-height: 1.6; }
.report-note { color: #7b8a99; font-size: .8rem; line-height: 1.5; margin: .5rem 0 1rem; }
.report-status { margin-top: 1rem; padding: .75rem; border-radius: .5rem; font-size: .9rem; background: #f1f5f9; }
.report-status.success { background: #edf9f2; color: #226343; }
.report-status.error { background: #fff1f0; color: #9a342f; }
.report-done { margin-top: .75rem; margin-left: auto; }
:deep(.afr-textarea label) { display:block; padding:0; margin-bottom:.25rem; font-size:.875rem; }
:deep(textarea) { padding:.4rem .5rem; font-size:1rem; resize:vertical; }
.consent { margin:.6rem 0; }
.report-actions { display:flex; justify-content: flex-end; flex-wrap:wrap; gap:.5rem; margin-top:.5rem; }
:deep(button) { max-width:100%; white-space:normal; }
</style>
