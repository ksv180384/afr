<script setup>
import { ref, shallowReactive, watch } from 'vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import { createReviewSettingsController } from '@/App/Learning/reviewSettingsController.js';
import { reviewDurationLabel, reviewWordCount } from '@/App/Learning/shortReview.js';
import { accountTransport } from '@/App/Services/Api/daily_practice.js';

const props = defineProps({
  suggestion: Object,
  owner: {type: Number, default: null},
  store: {type: Object, required: true},
  busy: Boolean,
});
const emit = defineEmits(['start', 'dismissed']);
const hidden = ref(false);
const state = shallowReactive({});
const controller = createReviewSettingsController({
  transport: accountTransport,
  guestRepository: props.store.guestRepository,
  getOwner: () => props.owner,
  onMutation: owner => props.store.invalidate(owner),
  state,
});
watch(() => props.owner, () => { hidden.value = false; });
watch(() => props.suggestion, (next, previous) => {
  if (next && !previous) hidden.value = false;
});
const snooze = async () => {
  if (state.busy || props.busy) return;
  try {
    await controller.save('snooze_today');
    hidden.value = true;
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
  <aside v-if="suggestion?.total && !hidden" class="review-suggestion" aria-label="Предложение повторения">
    <div>
      <h3>Вспомним {{ reviewWordCount(suggestion.total) }}?</h3>
      <p v-if="suggestion.estimated_seconds">{{ reviewDurationLabel(suggestion.estimated_seconds) }}</p>
    </div>
    <div class="suggestion-actions">
      <afr-button :disabled="busy || state.busy || state.pending" @click="emit('start')">Повторить</afr-button>
      <afr-button :disabled="busy || state.busy" @click="snooze">{{ state.pending ? 'Повторить сохранение' : 'Не сегодня' }}</afr-button>
    </div>
    <p v-if="state.error" class="suggestion-error" role="alert">{{ state.error }}</p>
  </aside>
</template>

<style scoped>
.review-suggestion { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.35rem .8rem; padding:.8rem 1rem; margin:.75rem 0; border:1px solid var(--practice-border); border-radius:var(--practice-radius); background:var(--practice-surface); color:var(--practice-ink); }
h3 { font-size:1rem; font-weight:650; }
p { font-size:.875rem; }
.suggestion-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.3rem .5rem; }
.suggestion-actions :deep(button) { min-height:44px; }
.suggestion-error { flex-basis:100%; }
</style>
