<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { usePracticeHome } from '@/App/Learning/practiceHome.js';
import { learningAccess, completedGuestSessions, decorateLearningCatalog } from '@/App/Learning/learningAccess.js';
import PracticeLayout from '@/App/Layouts/PracticeLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';
import FrenchText from '@/App/Components/Learning/FrenchText.vue';
import PronunciationNotes from '@/App/Components/Learning/PronunciationNotes.vue';

const props = defineProps({catalog:Object, accessOutline:Array, accessNotice:String, authUser: { type: Object, default: null }});
const accessReady = ref(!!props.authUser);
const completed = ref([]);
const profileLevel = ref('PRE_A1');
const catalog = computed(() => props.authUser || !props.accessOutline ? props.catalog : decorateLearningCatalog(props.catalog, learningAccess(props.accessOutline, completed.value, profileLevel.value)));
onMounted(async () => {
  if (props.authUser) return;
  try { const repository = usePracticeHome().guestRepository; await repository.archiveBound(); const state = await repository.read(); completed.value = completedGuestSessions(state); profileLevel.value = state?.current_level ?? 'PRE_A1'; }
  catch { completed.value = []; }
  accessReady.value = true;
  if (catalog.value.access?.locked) router.visit('/practice', {replace:true});
});
</script>
<template>
  <seo-head :title="catalog.seo.title" :description="catalog.seo.description" :canonical-url="catalog.seo.canonicalUrl" :no-index="catalog.seo.noIndex" :robots="catalog.seo.noIndex?'noindex, follow':'index, follow'" :json-ld="catalog.seo.jsonLd" />
  <practice-layout :auth-user="authUser" :title="catalog.title" active="catalog"><div class="practice-body">
      <nav v-if="catalog.kind!=='index'" aria-label="Хлебные крошки" class="practice-breadcrumbs"><template v-for="(crumb,index) in catalog.breadcrumbs" :key="crumb.path">
        <Link v-if="index<catalog.breadcrumbs.length-1" :href="crumb.path">{{ crumb.title }}</Link><span v-else aria-current="page">{{ crumb.title }}</span><span v-if="index<catalog.breadcrumbs.length-1" aria-hidden="true">/</span>
      </template></nav>
      <p v-if="accessNotice" role="status" class="practice-locked-notice">{{ accessNotice }}</p>
      <p v-if="catalog.access?.locked" role="status">{{ catalog.access.reason }}</p>
      <template v-if="!catalog.access?.locked">
      <p>{{ catalog.summary }}</p>
      <p v-if="catalog.kind==='index'">Сессия обычно занимает около пяти минут: сначала знакомство с выражениями, затем вопросы и разбор. Занятия открываются по порядку. Завершённые материалы доступны для повторения. Без регистрации прогресс сохраняется в этом браузере.</p>
      <template v-if="catalog.outcomes.length"><h2>Чему вы научитесь</h2><ul><li v-for="outcome in catalog.outcomes" :key="outcome">{{ outcome }}</li></ul></template>
      <pronunciation-notes :notes="catalog.notes" />
      <p v-if="!catalog.indexable" role="status">Материалы этой страницы ещё готовятся. Новые занятия появятся после публикации.</p>
      <template v-if="catalog.session">
        <p>Уровень: {{ catalog.session.level }} · Около {{ catalog.session.duration_minutes }} мин</p>
        <h2>Цели занятия</h2><ul><li v-for="goal in catalog.session.objectives" :key="goal.id">{{ goal.title }}</li></ul>
        <pronunciation-notes :notes="catalog.session.pronunciation_notes" />
        <section v-for="(block,index) in catalog.session.public_page.explanation_blocks" :key="index">
          <h2>{{ block.heading }}</h2><p v-for="(paragraph,i) in block.paragraphs" :key="i">{{ paragraph }}</p>
          <pronunciation-notes :notes="block.pronunciation_notes ?? []" />
          <ul class="practice-examples"><li v-for="(example,i) in block.examples ?? []" :key="i"><french-text :text="example.fr" :transcription-ru="example.transcription_ru" :translation="example.ru" /></li></ul>
        </section>
        <h2>Выражения занятия</h2><div v-for="word in catalog.session.vocabulary" :key="word.id" class="practice-word">
          <french-text :text="word.fr" :transcription-ru="word.transcription_ru" :translation="word.ru" />
          <french-text v-if="word.example" :text="word.example.fr" :transcription-ru="word.example.transcription_ru" :translation="word.example.ru" />
        </div>
        <p><Link class="practice-primary" :href="'/daily?session='+encodeURIComponent(catalog.session.id)">{{ catalog.access?.completed ? 'Пройти повторно' : 'Начать практику' }}</Link></p>
        <p>После завершения занятия откроется следующее. Это занятие можно пройти повторно.</p>
      </template>
      <template v-if="catalog.cards.length">
        <h2>{{ catalog.kind==='index'?'Все уровни':catalog.kind==='level'?'Темы уровня':'Занятия темы' }}</h2>
        <ul class="practice-cards"><li v-for="(card,index) in catalog.cards" :key="card.path ?? index">
          <h3><Link v-if="card.path && !card.locked && accessReady" :href="card.path">{{ card.title }}</Link><span v-else>{{ card.title }}</span></h3><p>{{ card.summary }}</p><p v-if="card.locked" class="practice-lock" role="status">🔒 {{ card.reason }}</p><p v-else-if="card.completed" class="practice-completed">Пройдено · доступно для повторения</p><p v-else-if="card.review_only" class="practice-completed">Доступно по вашему уровню</p>
          <p v-if="card.count!==undefined">Опубликовано занятий: {{ card.count }}</p><p v-if="card.duration">Около {{ card.duration }} мин</p>
        </li></ul>
      </template>
      <template v-if="catalog.related.length"><h2>Связанные и предыдущие материалы</h2><ul><li v-for="card in catalog.related" :key="card.path"><Link v-if="!card.locked && accessReady" :href="card.path">{{ card.title }}</Link><span v-else>{{ card.title }} · 🔒</span></li></ul></template>
      <nav aria-label="По теме" class="practice-breadcrumbs"><Link v-if="catalog.previous && !catalog.previous.locked && accessReady" :href="catalog.previous.path">Предыдущее: {{ catalog.previous.title }}</Link><Link v-if="catalog.next && !catalog.next.locked && accessReady" :href="catalog.next.path">Следующее: {{ catalog.next.title }}</Link></nav>
      </template>
    </div></practice-layout>
</template>
<style>
.practice-lock,.practice-locked-notice { color:#7c4a03; } .practice-completed { color:#21613b; }
.practice-body h2 { font-size:1.3rem; font-weight:bold; margin:1.5rem 0 .7rem; } .practice-body h3 { font-size:1.1rem; font-weight:bold; }
.practice-body p { line-height:1.7; margin:.7rem 0; white-space:pre-line; } .practice-body a { color:#155e91; text-decoration:underline; }
.practice-breadcrumbs { display:flex; gap:.5rem 1rem; flex-wrap:wrap; margin:.5rem 0; }
.practice-breadcrumbs a,.practice-body>p>a { display:inline-flex; align-items:center; min-height:44px; }
.practice-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr)); gap:1rem; }
.practice-cards>li,.practice-word,.practice-french { background:#fff; padding:1rem; border:1px solid #cbd5e1; border-radius:.25rem; margin:.5rem 0; }
.practice-examples { display:grid; gap:1rem; margin:1rem 0; } .practice-examples>li { padding:1rem; border:1px solid #cbd5e1; border-radius:.25rem; } .practice-transcription { color:#475569; } .practice-body .practice-primary { display:inline-flex; min-height:44px; align-items:center; padding:.7rem 1rem; background:#17658b; color:white; text-decoration:none; border-radius:.25rem; }
.practice-body :focus-visible { outline:3px solid #2563eb; outline-offset:3px; }
</style>
