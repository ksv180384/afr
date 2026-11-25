<script setup>
import { ref, shallowReactive, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import { createPracticeSettingsController } from '@/App/Learning/practiceSettingsController.js';
import { reviewDurationLabel, reviewWordCount } from '@/App/Learning/shortReview.js';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';
import { usePracticeFeedback } from '@/App/Learning/usePracticeFeedback.js';
import DailyPracticeIcon from './DailyPracticeIcon.vue';
import AfrHelpPopover from '@/App/Components/Info/AfrHelpPopover.vue';

const props = defineProps({
  suggestion: Object,
  owner: {type: Number, default: null},
  store: {type: Object, required: true},
  busy: Boolean,
  clickable: Boolean,
  resume: Boolean,
});
const emit = defineEmits(['start', 'dismissed']);
const hidden = ref(false);
const confirmation = ref(null);
const feedback = usePracticeFeedback({status:confirmation});
const state = shallowReactive({});
const controller = createPracticeSettingsController({
  transport: accountTransport,
  guestRepository: props.store.guestRepository,
  getOwner: () => props.owner,
  onMutation: owner => props.store.invalidate(owner),
  state,
});
watch(() => props.owner, () => { hidden.value = false; confirmation.value = null; });
watch(() => props.suggestion, (next, previous) => {
  if (next && !previous) hidden.value = false;
});
const snooze = async () => {
  if (state.busy || props.busy) return;
  try {
    const profile = await controller.save('snooze_today');
    const settings = profile.review_suggestions;
    hidden.value = !settings.can_suggest;
    if (hidden.value && Date.parse(settings.snoozed_until) > Date.now()) {
      confirmation.value = {kind:'saved',message:'Предложение скрыто до завтра.'};
    }
    emit('dismissed');
  } catch {
    if (state.profile?.review_suggestions?.can_suggest === false) {
      hidden.value = true;
      emit('dismissed');
    }
  }
};
</script>

<template>
  <aside v-if="resume || (suggestion?.total && !hidden)" class="review-suggestion" :class="{ 'review-suggestion--clickable': clickable || resume }"
    :aria-label="resume ? 'Начатое повторение' : 'Предложение повторения'">
    <afr-button v-if="clickable || resume" class="suggestion-main" :disabled="busy || state.busy || state.pending" @click="emit('start')">
      <span class="suggestion-emblem"><daily-practice-icon name="reset" /></span>
      <span class="suggestion-copy">
        <strong>{{ resume ? 'Продолжить повторение' : `Повторить ${reviewWordCount(suggestion.total)}` }}</strong>
        <span v-if="!resume && suggestion.estimated_seconds" class="suggestion-duration">
          <daily-practice-icon name="clock" />{{ reviewDurationLabel(suggestion.estimated_seconds) }}
        </span>
      </span>
      <span class="suggestion-arrow"><daily-practice-icon name="chevron" /></span>
    </afr-button>
    <div v-else>
      <h3>Вспомним {{ reviewWordCount(suggestion.total) }}?</h3>
      <p v-if="suggestion.estimated_seconds">{{ reviewDurationLabel(suggestion.estimated_seconds) }}</p>
    </div>
    <div v-if="!resume" class="suggestion-actions">
      <afr-button v-if="!clickable" :disabled="busy || state.busy || state.pending" @click="emit('start')">Повторить</afr-button>
      <div class="snooze-actions">
        <afr-button :text="clickable" :disabled="busy || state.busy" @click="snooze">{{ state.pending ? 'Повторить сохранение' : 'Не сегодня' }}</afr-button>
        <afr-help-popover label="Что произойдёт, если нажать «Не сегодня»?">
          Предложения короткого повторения скроются до завтра по вашему часовому поясу. Повторение можно запустить вручную в личном словаре.
        </afr-help-popover>
      </div>
    </div>
    <p v-if="state.error" class="suggestion-error" role="alert">{{ state.error }}</p>
  </aside>
  <p v-if="hidden && confirmation && !feedback.enabled" class="suggestion-confirmation" role="status">{{ confirmation.message }}</p>
</template>

<style scoped>
.review-suggestion { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.35rem .8rem; padding:.8rem 1rem; margin:.75rem 0; border:1px solid var(--practice-border); border-radius:var(--practice-radius); background:var(--practice-surface); color:var(--practice-ink); }
h3 { font-size:1rem; font-weight:650; }
p { font-size:.875rem; }
.suggestion-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.3rem .5rem; min-width:0; max-width:100%; }
.suggestion-actions :deep(button) { min-height:44px; }
.snooze-actions { display:flex; align-items:center; min-width:0; max-width:100%; }
.suggestion-error { flex-basis:100%; }
.review-suggestion--clickable {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0;
  margin: 1rem 0 0;
  padding: 0;
  border: 0;
  background: transparent;
}
.review-suggestion--clickable > button.suggestion-main {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: .75rem;
  width: 100%;
  padding: .75rem clamp(.7rem, 3vw, 1rem);
  border: 1px solid #d4e0ec;
  border-radius: var(--practice-radius, .85rem);
  background: #f6f9fd;
  color: var(--practice-ink, #18344c);
  text-align: start;
  white-space: normal;
}
.review-suggestion--clickable > button.suggestion-main:enabled:hover {
  border-color: #adc7e3;
  background: #edf4fc;
}
.review-suggestion--clickable > button.suggestion-main:disabled { opacity: .65; }
.suggestion-emblem {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: .7rem;
  background: #e8f0fa;
  color: #355f8a;
}
.suggestion-emblem :deep(svg) { width: 1.25rem; height: 1.25rem; }
.suggestion-copy { display: grid; gap: .15rem; min-width: 0; }
.suggestion-copy strong { font-size: 1rem; font-weight: 700; line-height: 1.35; }
.suggestion-duration { display: flex; align-items: center; gap: .35rem; color: #48647e; font-size: .8rem; line-height: 1.3; }
.suggestion-duration :deep(svg) { width: .9rem; height: .9rem; }
.suggestion-arrow {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: .55rem;
  background: #e8f0fa;
  color: #315d89;
}
.suggestion-arrow :deep(svg) { width: 1rem; height: 1rem; }
.suggestion-confirmation { margin-top:.75rem; color:#365f4c; }
.review-suggestion--clickable .suggestion-actions { justify-content: flex-end; }
.review-suggestion--clickable .suggestion-actions :deep(button.text) { font-size: .8rem; }
</style>
