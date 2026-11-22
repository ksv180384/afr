<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrTextarea from '@/App/Components/Form/AfrTextarea.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import { reportPracticeProblem } from '@/App/Services/Api/daily_practice.js';
import { reportAnswerText } from '@/App/Learning/playerUi.js';
const props=defineProps({lessonId:Number,versionId:Number,taskId:String,runId:String,task:Object,answer:Object,compact:Boolean});
const inputId='practice-report-'+getCurrentInstance().uid;
const page=usePage();
const userId=computed(()=>page.props?.auth?.user?.id ?? null);
const text=ref(''); const includeAnswer=ref(false); const pending=ref(null); const busy=ref(false); const message=ref('');
const messageKind=ref('info');
const reportTrigger = ref(null);
const dialog = ref(null);
const openReport = () => {
  if (messageKind.value === 'success') message.value = '';
  if (!pending.value) includeAnswer.value = false;
  dialog.value?.showModal();
};
const closeReport = () => { if (!busy.value) dialog.value?.close(); };
const closeOnBackdrop = event => {
  if (event.target !== dialog.value) return;
  const rect = dialog.value.getBoundingClientRect();
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closeReport();
};
watch(userId,()=>{text.value='';includeAnswer.value=false;pending.value=null;message.value='';dialog.value?.close();});
const answerPreview = computed(() => props.taskId ? reportAnswerText(props.task, pending.value?.answer ?? props.answer) : '');
const canAttach = computed(() => Boolean(answerPreview.value));
watch(canAttach, available => { if (!available) includeAnswer.value = false; });
const submit=async()=>{
  if(busy.value || !userId.value || !text.value.trim()) return;
  const owner=userId.value;
  busy.value=true; message.value='';
  const attachAnswer = includeAnswer.value && canAttach.value;
  const lesson = props.lessonId ? { lesson_id: props.lessonId } : { session_version_id: props.versionId };
  pending.value ??= {client_report_id:crypto.randomUUID(),...lesson,task_id:props.taskId ?? null,
    ...(props.runId ? {run_id:props.runId}:{}),text:text.value,include_answer:attachAnswer,
    ...(attachAnswer ? {answer:JSON.parse(JSON.stringify(props.answer))}:{})};
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
    <button ref="reportTrigger" type="button" class="report-trigger" :aria-label="compact ? 'Сообщить об ошибке' : undefined" :title="compact ? 'Сообщить об ошибке' : undefined" @click="openReport">
      <daily-practice-icon name="message" /><span v-if="!compact">Сообщить об ошибке</span>
    </button>
    <dialog
      ref="dialog"
      class="report-dialog mobile-dialog-sheet"
      :aria-labelledby="inputId + '-title'"
      :aria-describedby="inputId + '-description'"
      @cancel.prevent="closeReport"
      @close="reportTrigger?.focus({ preventScroll: true })"
      @click="closeOnBackdrop"
    >
      <div class="report-heading"><h2 :id="inputId + '-title'">{{ taskId ? 'Что-то не так с заданием?' : 'Что-то не так с занятием?' }}</h2><button type="button" class="close-report" :disabled="busy" aria-label="Закрыть" @click="closeReport"><daily-practice-icon name="close" /></button></div>
      <p :id="inputId + '-description'" class="report-description">Расскажите, где ошибка: в тексте, переводе, звуке или проверке ответа.</p>
      <form v-show="messageKind !== 'success' || !message" @submit.prevent="submit">
        <afr-textarea :id="inputId" v-model="text" label="Описание ошибки" placeholder="Например, звук не совпадает с фразой…" required maxlength="4000" rows="4" :disabled="busy || !!pending" />
        <p class="report-note">{{ taskId ? 'Задание прикрепится автоматически.' : 'Занятие прикрепится автоматически.' }}
          <span v-if="taskId && !canAttach">Ответ можно приложить после ввода или выбора.</span>
        </p>
        <div v-if="canAttach" class="report-attachment">
          <afr-checkbox
            :id="inputId + '-answer'"
            v-model="includeAnswer"
            :aria-describedby="inputId + '-attachment-status'"
            :disabled="busy || !!pending"
          >Приложить введённый ответ</afr-checkbox>
          <div class="answer-preview" :class="{ 'is-included': includeAnswer }">
            <div class="answer-preview-header">
              <p class="answer-preview-label">Ваш ответ</p>
              <span :id="inputId + '-attachment-status'" class="answer-preview-status">
                {{ includeAnswer ? 'Будет приложен' : 'Без вложения' }}
              </span>
            </div>
            <p class="answer-preview-text">{{ answerPreview }}</p>
          </div>
        </div>
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
.report-dialog { border: 1px solid #d5e1ec; color: #18344c; background: #fff; box-shadow: 0 24px 80px #18344c30; }
.report-dialog::backdrop { background: #162c4560; backdrop-filter: blur(3px); }
.report-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.report-heading h2 { font-size: 1.2rem; line-height: 1.4; font-weight: 700; }
.close-report { display: grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: .5rem; }
.close-report:hover { background: #edf3f9; }
.report-description { color: #64748b; margin: .65rem 0 1.25rem; font-size: .9rem; line-height: 1.6; }
.report-note { color: #64748b; font-size: .8rem; line-height: 1.5; margin: .5rem 0 .75rem; }
.report-status { margin-top: 1rem; padding: .75rem; border-radius: .5rem; font-size: .9rem; background: #f1f5f9; }
.report-status.success { background: #edf9f2; color: #226343; }
.report-status.error { background: #fff1f0; color: #9a342f; }
.report-done { margin-top: .75rem; margin-left: auto; }
:deep(.afr-textarea label) { display:block; padding:0; margin-bottom:.25rem; font-size:.875rem; }
:deep(textarea) { padding:.4rem .5rem; font-size:1rem; resize:vertical; }
.report-attachment { margin: .75rem 0; }
.answer-preview { margin-top: .5rem; padding: .65rem .75rem; border: 1px solid #dce5ee; border-radius: .65rem; background: #f7f9fc; color: #52667d; }
.answer-preview-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .35rem; }
.answer-preview-label { font-size: .75rem; font-weight: 650; }
.answer-preview-status { padding: .15rem .5rem; border-radius: 1rem; background: #e9eff5; color: #475f75; font-size: .7rem; font-weight: 650; white-space: nowrap; }
.answer-preview-text { max-height: 8rem; overflow: auto; white-space: pre-wrap; font-size: .875rem; }
.answer-preview.is-included { border-color: #a7c5ea; background: #eef6ff; color: #18344c; }
.answer-preview.is-included .answer-preview-status { background: #dcecff; color: #17518b; }
@media (prefers-reduced-motion: no-preference) {
  .answer-preview, .answer-preview-status { transition: background-color 140ms ease-out, border-color 140ms ease-out, color 140ms ease-out; }
}
.report-actions { display:flex; justify-content: flex-end; flex-wrap:wrap; gap:.5rem; margin-top:.5rem; }
:deep(button) { max-width:100%; white-space:normal; }
</style>
