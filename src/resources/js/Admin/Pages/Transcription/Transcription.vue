<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
});

const text = ref('');

const VOWELS = 'aeiouyàâäéèêëîïôöùûüœаеёиоуыэюя';

const normalizeWord = (word) => word
  .toLowerCase()
  .replaceAll('’', "'")
  .normalize('NFC');

const isVowel = (char) => Boolean(char) && VOWELS.includes(char);

const replaceAt = (word, index, length, value) => (
  word.slice(0, index) + value + word.slice(index + length)
);

const applyRuleReplacements = (word, rules) => {
  let result = word;

  rules.forEach((rule) => {
    result = result.replace(rule.pattern, rule.replacement);
  });

  return result;
};

const stripSilentEndings = (word) => {
  if (word.length <= 2) {
    return word;
  }

  if (/(er|ez)$/.test(word) && word.length > 3) {
    return word.replace(/(er|ez)$/, 'э');
  }

  if (/et$/.test(word)) {
    return word.replace(/et$/, 'э');
  }

  if (/(gard|mont|visit|entr|travaill|écout|achet|cherch|habit|port|réserv|rempliss|parl)ent$/.test(word)) {
    return word.replace(/ent$/, '');
  }

  if (/e$/.test(word) && !/(le|de|te|me|se|ce|que)$/.test(word)) {
    word = word.slice(0, -1);
  }

  if (/bus$/.test(word)) {
    return word;
  }

  return word.replace(/[stdxz]$/g, '');
};

const replaceIenEndings = (word) => word
  .replace(/ciennes?$/g, 'сьен')
  .replace(/ciens?$/g, 'сьен:')
  .replace(/giennes?$/g, 'жьен')
  .replace(/giens?$/g, 'жьен:')
  .replace(/tiennes?$/g, 'тьен')
  .replace(/tiens?$/g, 'тьен:')
  .replace(/diennes?$/g, 'дьен')
  .replace(/diens?$/g, 'дьен:')
  .replace(/iennes?$/g, 'ьен')
  .replace(/iens?$/g, 'ьен:');

const replaceSpecificStems = (word) => word
  .replace(/applaud/g, 'аплёд')
  .replace(/([bcdfgjkmpqrstvwxz])\1+/g, '$1')
  .replace(/^philo/g, 'филё')
  .replace(/appart(?:e|a)?ment/g, 'апартёман:')
  .replace(/travaill/g, 'травай')
  .replace(/montr/g, 'мон:тр')
  .replace(/visit/g, 'визит')
  .replace(/entr/g, 'ан:тр')
  .replace(/écout/g, 'экут')
  .replace(/achet|achèt/g, 'ашэт')
  .replace(/habit/g, 'абит')
  .replace(/admir/g, 'адмир')
  .replace(/cherch/g, 'шэрш')
  .replace(/gard/g, 'гярд')
  .replace(/modern/g, 'модэрн')
  .replace(/plaisir/g, 'плезир')
  .replace(/machine/g, 'машин')
  .replace(/(гярд|мон:тр|визит|ан:тр|травай|экут|ашэт|шэрш|абит|адмир|порт|резерв|рём:плис|парл)ent$/g, '$1')
  .replace(/tenir$/g, 'тёнир')
  .replace(/enir$/g, 'ёнир')
  .replace(/annu/g, 'аню');

const transcribeElision = (word) => {
  if (!/^l['’]/.test(word)) {
    return null;
  }

  const base = word.slice(2);
  const baseTranscription = transcribeWord(base);
  const dropFirstSound = (value) => value.replace(/^[аеёиоуыэюя]/, '');

  if (/^(h?[oô]|au|eau)/.test(base)) {
    return `лё${dropFirstSound(baseTranscription)}`;
  }

  if (/^u/.test(base)) {
    return `лю${dropFirstSound(baseTranscription)}`;
  }

  if (/^(a|â|à)/.test(base)) {
    return `ля${dropFirstSound(baseTranscription)}`;
  }

  if (/^(in|im)/.test(base)) {
    return `лен:${baseTranscription.replace(/^эн:/, '')}`;
  }

  if (/^(e|é|è|ê|ex)/.test(base)) {
    return `ле${dropFirstSound(baseTranscription)}`;
  }

  return `л${baseTranscription}`;
};

const transcribeWord = (rawWord) => {
  let word = normalizeWord(rawWord);

  if (!word) {
    return '';
  }

  const exact = {
    la: 'ля',
    une: 'юн',
    "qu'une": 'кюн',
    "n'ai": 'нэ',
    nai: 'нэ',
    un: 'эн:',
    je: 'жё',
    j: 'ж',
    tu: 'тю',
    il: 'иль',
    ils: 'иль',
    elle: 'эль',
    elles: 'эль',
    nous: 'ну',
    vous: 'ву',
    moi: 'муа',
    toi: 'туа',
    et: 'э',
    est: 'э',
    les: 'ле',
    des: 'дэ',
    mes: 'мэ',
    tes: 'тэ',
    ses: 'сэ',
    le: 'лё',
    de: 'дё',
    du: 'дю',
    au: 'о',
    aux: 'о',
    ce: 'сё',
    cet: 'сэ',
    cette: 'сэт',
    ces: 'сэ',
    ne: 'нё',
    que: 'кё',
    qui: 'ки',
    quoi: 'куа',
    dans: 'дан:',
    en: 'ан:',
    mon: 'мон:',
    ton: 'тон:',
    son: 'сон:',
    ma: 'ма',
    ta: 'та',
    sa: 'са',
    notre: 'нотр',
    votre: 'вотр',
    leur: 'лёр',
    c: 'с',
    "c'est": 'сэ',
    "qu'est-ce": 'кэскё',
    "s'il": 'силь',
    plaît: 'пле',
    où: 'у',
    oui: 'уи',
    non: 'нон:',
    août: 'ут',
    ville: 'виль',
    restaurant: 'рэсторан:',
    café: 'кяфэ',
    maison: 'мэзон:',
    station: 'стасьон:',
    gare: 'гяр',
    banque: 'бан:к',
    regarder: 'рёгярдэ',
    visiter: 'визитэ',
    montrer: 'мон:трэ',
    entrer: 'ан:трэ',
    femme: 'фам',
    école: 'еколь',
    opéra: 'опера',
    appartement: 'апартёман:',
    homme: 'ом',
    hôtel: 'отэль',
    chambre: 'шам:бр',
    fenêtre: 'фёнэтр',
    voiture: 'вуатюр',
    fille: 'фий',
    musée: 'мюзэ',
    rue: 'рю',
    porte: 'порт',
    usine: 'юзин',
    avec: 'авэк',
    petit: 'пёти',
    grande: 'гран:д',
    table: 'табль',
    chaise: 'шэз',
    lampe: 'лям:п',
    lit: 'ли',
    bureau: 'бюро',
    téléphone: 'тэлефон',
    plafond: 'пляфон:',
    plancher: 'плян:шэ',
    mur: 'мюр',
    radio: 'радьё',
    télé: 'тэле',
    noir: 'нуар',
    vert: 'вэр',
    bleu: 'блё',
    gris: 'гри',
    blanc: 'блян:',
    nouveau: 'нуво',
    nouvelle: 'нувэль',
    travailler: 'травайе',
    écouter: 'экутэ',
    acheter: 'ашётэ',
    chercher: 'шэршэ',
    habiter: 'абитэ',
    sous: 'су',
    devant: 'дёван:',
    sur: 'сюр',
    être: 'этр',
    train: 'трэн:',
    avion: 'авьён:',
    auto: 'ото',
    matin: 'матэн:',
    soir: 'суар',
    beau: 'бо',
    belle: 'бэль',
    rouge: 'руж',
    jaune: 'жон',
    orange: 'оран:ж',
    aller: 'але',
    aussi: 'оси',
    mais: 'мэ',
    souvent: 'суван:',
    comment: 'коман:',
    famille: 'фамий',
    mère: 'мэр',
    père: 'пэр',
    fils: 'фис',
    parents: 'паран:',
    jardin: 'жардэн:',
    garage: 'гяраж',
    chien: 'шьен:',
    chat: 'ша',
    cuisine: 'куизин',
    salon: 'салён:',
    dimanche: 'диман:ш',
    samedi: 'самди',
    vite: 'вит',
    ensemble: 'ан:сам:бль',
    beaucoup: 'боку',
    toujours: 'тужур',
  };

  if (exact[word]) {
    return exact[word];
  }

  const elision = transcribeElision(word);
  if (elision) {
    return elision;
  }

  word = word.replace(/^l['’]/, 'л');
  word = word.replace(/^qu['’]/, 'к');
  word = replaceSpecificStems(word);
  word = replaceIenEndings(word);
  word = stripSilentEndings(word);

  const multiLetterRules = [
    { pattern: /ga/g, replacement: 'гя' },
    { pattern: /gue/g, replacement: 'г' },
    { pattern: /gi(?=[aeou])/g, replacement: 'жь' },
    { pattern: /di(?=[aeou])/g, replacement: 'дь' },
    { pattern: /ti(?=[aeou])/g, replacement: 'ть' },
    { pattern: /si(?=[aeou])/g, replacement: 'сь' },
    { pattern: /tion/g, replacement: 'сьон:' },
    { pattern: /ge(?=[aouàâäôöùûü])/g, replacement: 'ж' },
    { pattern: /ail(?:le)?/g, replacement: 'ай' },
    { pattern: /eil(?:le)?/g, replacement: 'ей' },
    { pattern: /euil(?:le)?/g, replacement: 'ёй' },
    { pattern: /ien(?=[aeiouàâäéèêëîïôöùûüœ])/g, replacement: 'ьен' },
    { pattern: /ia/g, replacement: 'ья' },
    { pattern: /ie/g, replacement: 'ье' },
    { pattern: /io/g, replacement: 'ьё' },
    { pattern: /ine$/g, replacement: 'ин' },
    { pattern: /ise$/g, replacement: 'из' },
    { pattern: /omme$/g, replacement: 'ом' },
    { pattern: /omme/g, replacement: 'ом' },
    { pattern: /eau/g, replacement: 'о' },
    { pattern: /au/g, replacement: 'о' },
    { pattern: /ou/g, replacement: 'у' },
    { pattern: /oi/g, replacement: 'уа' },
    { pattern: /ai|ei|è|ê|ë/g, replacement: 'э' },
    { pattern: /é/g, replacement: 'э' },
    { pattern: /eu|œu/g, replacement: 'ё' },
    { pattern: /ch/g, replacement: 'ш' },
    { pattern: /ph/g, replacement: 'ф' },
    { pattern: /gn/g, replacement: 'нь' },
    { pattern: /qu/g, replacement: 'к' },
    { pattern: /gu(?=[eéiîy])/g, replacement: 'г' },
    { pattern: /ill(?=$|[eéiî])/g, replacement: 'й' },
    { pattern: /il(?=$)/g, replacement: 'й' },
    { pattern: /ain|ein|aim|eim/g, replacement: 'эн:' },
    { pattern: /in|im|yn|ym|un|um/g, replacement: 'эн:' },
    { pattern: /an|am|en|em/g, replacement: 'ан:' },
    { pattern: /on|om/g, replacement: 'он:' },
    { pattern: /th/g, replacement: 'т' },
  ];

  word = applyRuleReplacements(word, multiLetterRules);

  let result = '';

  for (let i = 0; i < word.length; i += 1) {
    const char = word[i];
    const prev = word[i - 1] ?? '';
    const next = word[i + 1] ?? '';

    if (char === 'c') {
      result += isVowel(next) && 'eéiîy'.includes(next) ? 'с' : 'к';
      continue;
    }

    if (char === 'ç') {
      result += 'с';
      continue;
    }

    if (char === 'g') {
      result += 'eéiîy'.includes(next) ? 'ж' : 'г';
      continue;
    }

    if (char === 's') {
      result += isVowel(prev) && isVowel(next) ? 'з' : 'с';
      continue;
    }

    if (char === 'x') {
      result += 'кс';
      continue;
    }

    if (char === 'h' || char === "'" || char === '’') {
      continue;
    }

    const oneLetter = {
      a: 'а',
      à: 'а',
      â: 'а',
      ä: 'а',
      b: 'б',
      d: 'д',
      e: 'ё',
      f: 'ф',
      i: 'и',
      î: 'и',
      ï: 'и',
      j: 'ж',
      k: 'к',
      l: 'ль',
      m: 'м',
      n: 'н',
      o: 'о',
      ô: 'о',
      ö: 'о',
      p: 'п',
      r: 'р',
      t: 'т',
      u: 'ю',
      ù: 'ю',
      û: 'ю',
      ü: 'ю',
      v: 'в',
      w: 'в',
      y: 'и',
      z: 'з',
    };

    result += oneLetter[char] ?? char;
  }

  result = result
    .replace(/^й$/g, 'иль')
    .replace(/^ёль$/g, 'эль')
    .replace(/^ж$/g, 'жё')
    .replace(/^ль(?=[аеёиоуыэюя])/g, 'л')
    .replace(/ль(?=[бвгджзклмнпрстфхцчшщ])/g, 'л')
    .replace(/([бвгджзкмнпрстфхцчшщ])ль(?=[аеёиоуыэюя])/g, '$1л')
    .replace(/льэ/g, 'ле')
    .replace(/н:([бп])/g, 'м:$1')
    .replace(/ль(?=$)/g, 'ль')
    .replace(/льа/g, 'ля')
    .replace(/льо/g, 'лё')
    .replace(/льу/g, 'лю')
    .replace(/зь(?=[аеёиоуыэюя])/g, 'сь')
    .replace(/ё$/g, '')
    .replace(/(.)\1{2,}/g, '$1$1');

  return result;
};

const startsWithVowelSound = (word) => /^(?:h?[aeiouyàâäéèêëîïôöùûüœ]|l['’][aeiouyàâäéèêëîïôöùûüœ])/i.test(normalizeWord(word));

const applyLiaison = (tokens) => tokens.map((token, index) => {
  if (!token.isWord || index === 0) {
    return token.transcribed;
  }

  const previous = [...tokens].slice(0, index).reverse().find((item) => item.isWord);
  if (!previous || !startsWithVowelSound(token.raw)) {
    return token.transcribed;
  }

  const previousRaw = normalizeWord(previous.raw);

  if (['nous', 'vous', 'des', 'les', 'mes', 'tes', 'ses', 'ces', 'aux'].includes(previousRaw) && !/^l['’]/.test(normalizeWord(token.raw))) {
    return `z${token.transcribed}`.replace(/^z/, 'з');
  }

  if (['est', 'sont', 'petit', 'grand'].includes(previousRaw) && !/^l['’]/.test(normalizeWord(token.raw))) {
    return `т${token.transcribed}`;
  }

  if (['un', 'en', 'mon', 'ton', 'son', 'bien'].includes(previousRaw) && !/^l['’]/.test(normalizeWord(token.raw))) {
    return `н${token.transcribed}`;
  }

  return token.transcribed;
});

const transcribeText = (value) => {
  const normalizedValue = value.replace(/[‐‑‒–—-]/g, ' ');
  const tokens = [...normalizedValue.matchAll(/[\p{L}'’]+|[^\p{L}'’]+/gu)].map(([raw]) => {
    const isWordToken = /^[\p{L}'’]+$/u.test(raw);

    return {
      raw,
      isWord: isWordToken,
      transcribed: isWordToken ? transcribeWord(raw) : raw,
    };
  });

  const wordsWithLiaison = applyLiaison(tokens);

  return tokens.map((token, index) => {
    if (token.isWord) {
      return wordsWithLiaison[index];
    }

    return token.transcribed;
  }).join('');
};

const transcription = computed(() => transcribeText(text.value));

const examples = [
  ['la ville', 'ля виль'],
  ['le restaurant', 'лё рэсторан:'],
  ['regarder', 'рёгярдэ'],
  ['la maison', 'ля мэзон:'],
  ['la fille', 'ля фий'],
  ['le garçon', 'лё гярсон:'],
  ['beaucoup', 'боку'],
  ['toujours', 'тужур'],
];
</script>

<template>
  <admin-layout
    :auth-user="authUser"
    title="Генерация транскрипции"
  >
    <Head>
      <title>Генерация транскрипции</title>
      <meta name="description" content="Генерация русской транскрипции французских слов" />
      <meta property="og:title" content="Генерация транскрипции" />
      <meta property="og:description" content="Генерация русской транскрипции французских слов" />
    </Head>

    <div class="transcription-page">
      <el-card shadow="never" class="transcription-card">
        <template #header>
          <div class="card-header">
            <div>
              <div class="card-title">Французская транскрипция</div>
              <div class="card-subtitle">Работает в браузере по правилам, без обращения к БД</div>
            </div>
          </div>
        </template>

        <div class="form-grid">
          <div>
            <el-input
              v-model="text"
              placeholder="Введите слово или фразу на французском"
              :rows="7"
              type="textarea"
              resize="none"
            />
          </div>

          <div class="result-box">
            <div class="result-label">Транскрипция</div>
            <div class="result-text">
              {{ transcription || 'Введите французское слово' }}
            </div>
          </div>
        </div>

        <div class="examples">
          <div class="examples-title">Примеры из словаря</div>
          <div class="examples-list">
            <button
              v-for="[source, expected] in examples"
              :key="source"
              type="button"
              class="example-item"
              @click="text = source"
            >
              <span>{{ source }}</span>
              <span>{{ expected }}</span>
            </button>
          </div>
        </div>
      </el-card>
    </div>
  </admin-layout>
</template>

<style scoped>
.transcription-page{
  @apply bg-sky-50 min-h-full p-4;
}

.transcription-card{
  @apply border-blue-100;
}

.card-header{
  @apply flex items-center justify-between;
}

.card-title{
  @apply text-xl font-semibold text-gray-800;
}

.card-subtitle{
  @apply text-sm text-gray-500;
}

.form-grid{
  @apply grid grid-cols-1 lg:grid-cols-2 gap-4;
}

.result-box{
  @apply rounded border border-blue-100 bg-sky-50 p-4 min-h-[168px];
}

.result-label{
  @apply text-sm text-gray-500 mb-2;
}

.result-text{
  @apply whitespace-pre-wrap break-words text-lg font-semibold text-gray-800;
}

.examples{
  @apply mt-6;
}

.examples-title{
  @apply mb-2 text-sm font-semibold text-gray-600;
}

.examples-list{
  @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2;
}

.example-item{
  @apply flex justify-between gap-3 rounded border border-blue-100 bg-white px-3 py-2 text-left text-sm transition hover:border-sky-300 hover:bg-sky-50;
}

.example-item span:first-child{
  @apply font-semibold text-sky-700;
}

.example-item span:last-child{
  @apply text-gray-600;
}
</style>
