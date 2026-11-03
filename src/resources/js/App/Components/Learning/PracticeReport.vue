<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrTextarea from '@/App/Components/Form/AfrTextarea.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import { reportPracticeProblem } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
const props=defineProps({versionId:Number,taskId:String,runId:String,answer:Object});
const inputId='practice-report-'+getCurrentInstance().uid;
const page=usePage();
const userId=computed(()=>page.props?.auth?.user?.id ?? null);
const text=ref(''); const includeAnswer=ref(false); const pending=ref(null); const busy=ref(false); const message=ref('');
const messageKind=ref('info');
watch(userId,()=>{text.value='';includeAnswer.value=false;pending.value=null;message.value='';});
const feedback=usePracticeFeedback({status:computed(()=>({kind:messageKind.value,message:message.value}))});
const canAttach=computed(()=>props.taskId && props.answer && Object.keys(props.answer).length>0);
const submit=async()=>{
  if(busy.value || !userId.value) return;
  const owner=userId.value;
  busy.value=true; message.value='';
  pending.value ??= {client_report_id:crypto.randomUUID(),session_version_id:props.versionId,task_id:props.taskId ?? null,
    ...(props.runId ? {run_id:props.runId}:{}),text:text.value,include_answer:includeAnswer.value===true,
    ...(includeAnswer.value && canAttach.value ? {answer:JSON.parse(JSON.stringify(props.answer))}:{})};
  try {
    const response=await reportPracticeProblem(pending.value);
    if(userId.value!==owner) return;
    messageKind.value='success';
    message.value='Сообщение получено. Номер: '+response.report_id+'.';
    pending.value=null; text.value=''; includeAnswer.value=false;
  } catch(error) { if(userId.value===owner) {messageKind.value='error'; message.value=error.response?.data?.message ?? 'Не удалось отправить сообщение. Текст сохранён в открытой форме.';} }
  finally {busy.value=false;}
};
const edit=()=>{pending.value=null;message.value='';};
</script>
<template>
  <details v-if="userId" class="practice-report"><summary>Сообщить об ошибке</summary>
    <p>Версия №{{ versionId }}<span v-if="taskId"> · шаг {{ taskId }}</span>. Ответ не отправляется без вашего выбора.</p>
    <form @submit.prevent="submit">
      <afr-textarea :id="inputId" v-model="text" label="Что нужно исправить?" required maxlength="4000" rows="3" :disabled="busy || !!pending" />
      <afr-checkbox v-if="canAttach" :id="inputId+'-answer'" v-model="includeAnswer" class="consent" :disabled="busy || !!pending">Приложить мой текущий ответ к этому шагу</afr-checkbox>
      <div class="report-actions"><afr-button native-type="submit" type="primary" :loading="busy" :disabled="!text.trim()">Отправить сообщение</afr-button>
      <afr-button v-if="pending && !busy" @click="edit">Изменить сообщение</afr-button></div>
    </form>
    <p v-if="message && !feedback.enabled" role="status">{{ message }}</p>
  </details>
</template>
<style scoped>
.practice-report { margin:1rem 0; border-top:1px solid #cbd5e1; padding-top:.7rem; overflow-wrap:anywhere; }
summary { color:#155e91; cursor:pointer; min-height:32px; } p { margin:.5rem 0; line-height:1.5; }
:deep(.afr-textarea label) { display:block; padding:0; margin-bottom:.25rem; font-size:.875rem; }
:deep(textarea) { padding:.4rem .5rem; font-size:1rem; resize:vertical; }
.consent { margin:.6rem 0; }
.report-actions { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.5rem; }
:deep(button) { max-width:100%; white-space:normal; }
</style>
