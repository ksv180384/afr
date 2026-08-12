<script setup>
import { Link } from '@inertiajs/vue3';
import MiniLayout from '@/App/Layouts/MiniLayout.vue';
import SeoHead from '@/App/Components/Seo/SeoHead.vue';

defineProps({ authUser: { type: Object, default: null } });
</script>

<template>
  <mini-layout :auth-user="authUser">
    <seo-head title="Игры для изучения французского" description="Тренируйте французские слова в коротких обучающих играх." />
    <section class="games-page">
      <div>
        <p class="eyebrow">Учиться играя</p>
        <h1>Игры</h1>
        <p class="intro">Короткие тренировки, которые помогают быстрее запоминать французские слова.</p>
      </div>
      <article class="game-card">
        <div class="game-visual"><span>Bonjour</span><i>Здравствуйте</i><i>Спасибо</i><i>До свидания</i></div>
        <div class="game-card-content">
          <p class="eyebrow">Первая игра</p>
          <h2>Падающие переводы</h2>
          <p>Выберите верный перевод, пока карточки не достигли нижней границы. Каждые 5 слов игра ускоряется.</p>
          <Link :href="route('games.falling-translations')" class="play-button">Играть <span>→</span></Link>
        </div>
      </article>
    </section>
  </mini-layout>
</template>

<style scoped>
.games-page { @apply mx-auto flex w-full max-w-5xl flex-col gap-8 px-5 py-12 sm:px-10; }
.eyebrow { @apply mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-600; }
h1 { @apply text-4xl font-black text-slate-900 sm:text-5xl; }
.intro { @apply mt-3 max-w-xl text-lg text-slate-600; }
.game-card { @apply overflow-hidden rounded-3xl bg-slate-900 shadow-2xl md:grid md:grid-cols-2; }
.game-visual { @apply relative min-h-72 overflow-hidden bg-gradient-to-br from-indigo-950 via-sky-900 to-cyan-600 p-8; }
.game-visual span { @apply absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-3xl font-black text-white; }
.game-visual i { @apply absolute rounded-2xl bg-white/95 px-4 py-3 text-sm font-bold not-italic text-slate-800 shadow-xl; animation: float 4s ease-in-out infinite; }
.game-visual i:nth-of-type(1) { @apply left-[8%] top-[12%]; }
.game-visual i:nth-of-type(2) { @apply right-[8%] top-[29%]; animation-delay: -1.2s; }
.game-visual i:nth-of-type(3) { @apply bottom-[12%] left-[18%]; animation-delay: -2.4s; }
.game-card-content { @apply flex flex-col justify-center p-8 text-white sm:p-10; }
h2 { @apply text-3xl font-black; }
.game-card-content > p:not(.eyebrow) { @apply mt-3 leading-relaxed text-slate-300; }
.play-button { @apply mt-7 inline-flex w-fit items-center gap-3 rounded-full bg-cyan-300 px-6 py-3 font-black text-slate-950 transition hover:bg-white; }
.play-button span { @apply text-xl; }
@keyframes float { 50% { transform: translateY(18px) rotate(2deg); } }
</style>
