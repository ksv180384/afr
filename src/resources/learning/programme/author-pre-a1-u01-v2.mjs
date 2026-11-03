import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

// Keep published v1 and the contract examples immutable; import this as version 2.
const root = path.dirname(fileURLToPath(import.meta.url));
const original = JSON.parse(readFileSync(path.join(root, 'packages/PRE_A1/PRE_A1-U01.v1.json'), 'utf8'));
const copy = structuredClone;
const lexicon = new Map(original.sessions.flatMap(s => s.vocabulary).map(w => [w.id, w]));
const goals = new Map(original.sessions.flatMap(s => s.objectives).map(g => [g.id, g]));
const G = id => 'PRE_A1.' + id;
const ru = text => ({ text, text_lang: 'ru' });
const fr = (text, transcription_ru) => ({ text, text_lang: 'fr', transcription_ru });
const word = id => copy(lexicon.get(id));
const expression = id => { const w = word(id); return fr(w.fr, w.transcription_ru); };
const note = (fr, transcription_ru) => ({ fr, transcription_ru });
const audio = (text, transcription_ru) => ({ text, transcription_ru, lang: 'fr-FR', asset_id: null });
const intro = (before, text, ids) => ({ id: 'I' + before.slice(1), before_task_id: before, text, vocabulary_ids: ids });
const task = (id, type, instruction, skill, objectives, words, assessed, correct, incorrect, fields) => ({
  id, type, instruction, prompt: ru('Выберите ответ по ситуации.'), skill,
  objective_ids: objectives.map(G), vocabulary_ids: words, assessed_vocabulary_ids: assessed,
  feedback: { correct, incorrect }, ...fields,
});
const choose = (id, instruction, objectives, ids, correctId, alternatives, correct, incorrect, fields = {}) => task(
  id, 'single_choice', instruction, 'vocabulary', objectives, ids, [correctId], correct, incorrect,
  { choices: alternatives.map((value, index) => ({ id: 'c' + index, ...value })), correct_choice_id: 'c0', ...fields },
);
const listen = (id, instruction, objectives, ids, spoken, transcription, alternatives, correct, incorrect) => task(
  id, 'single_choice', instruction, 'listening', objectives, ids, ids, correct, incorrect,
  { prompt: { ...ru('Послушайте реплику. При необходимости включите её ещё раз.'), audio: audio(spoken, transcription) },
    choices: alternatives.map((text, index) => ({ id: 'c' + index, ...ru(text) })), correct_choice_id: 'c0' },
);
const order = (id, instruction, objectives, ids, parts, full, transcription, correct, incorrect) => {
  const tokens = parts.map(([text, trans], index) => ({ id: 't' + index, ...fr(text, trans) }));
  return task(id, 'word_order', instruction, 'sentence_building', objectives, ids, [], correct, incorrect, {
    prompt: ru('Соберите ответ из карточек. Печатать не нужно.'),
    tokens: [...tokens.slice(1), tokens[0]], accepted_orders: [tokens.map(t => t.id)],
    completed_phrases: [note(full, transcription)],
  });
};
const sessions = copy(original.sessions);
const [l1, l2, l3, l4, l5] = sessions;
const addWords = (s, ids) => { for (const id of ids) if (!s.vocabulary.some(w => w.id === id)) s.vocabulary.push(word(id)); };
const renumber = s => s.tasks.forEach((t, i) => { t.id = 'Q' + String(i + 1).padStart(2, '0'); });
const phraseParts = [['Répétez,', 'рэпэтэ'], ['s’il', 'силь'], ['vous', 'ву'], ['plaît.', 'плэ́']];
const repeatOrder = id => order(id, 'Вы не расслышали. Соберите полную просьбу «Повторите, пожалуйста».', ['CLARIFY.REQUEST'], ['repetez', 'sil-vous-plait'],
  phraseParts, 'Répétez, s’il vous plaît.', 'рэпэтэ, силь ву плэ́',
  'Вы соединили просьбу повторить со знакомой формулой вежливости.', 'Сначала действие «Повторите», затем готовое выражение «пожалуйста».');
const wellOrder = id => order(id, 'У вас всё хорошо. Соберите ответ «Всё хорошо, спасибо».', ['WELLBEING.REPLY'], ['ca-va-bien', 'merci'],
  [['Ça', 'са'], ['va', 'ва'], ['bien,', 'бье́(н)'], ['merci.', 'мэрси́']], 'Ça va bien, merci.', 'са ва бье(н), мэрси́',
  'Сначала ответ о самочувствии, затем благодарность.', 'Соберите выражение «Всё хорошо», а после него добавьте «спасибо».');

// Lesson 1: chunks of at most three expressions; no premature conjugation test.
l1.introductions = [
  intro('Q01', 'Начнём с трёх реплик: поздороваться, сказать «Привет» знакомому и попрощаться. Послушайте их. Русская запись произношения приблизительна; (н) обозначает носовой гласный без отдельного «н».', ['bonjour', 'salut', 'au-revoir']),
  intro('Q03', 'Ещё одно приветствие — для вечера. С преподавателем выберите вежливое «Добрый вечер».', ['bonsoir']),
  { ...intro('Q04', 'Теперь знакомство: сообщите своё имя или спросите имя собеседника. Учите выражение целиком; разбирать спряжение сейчас не нужно.', ['je-mappelle', 'comment-tu-tappelles']), audio: audio('Je m’appelle Léa.', 'жё мапэль леа́') },
];
const pairs = l1.tasks[1];
for (const field of ['vocabulary_ids', 'assessed_vocabulary_ids']) pairs[field] = ['bonjour', 'salut', 'au-revoir'];
pairs.left_items = pairs.left_items.filter(i => i.id !== 'l-bonsoir');
pairs.right_items = pairs.right_items.filter(i => i.id !== 'r-bonsoir');
pairs.correct_pairs = pairs.correct_pairs.filter(i => i.left_id !== 'l-bonsoir');
pairs.feedback = { correct: 'Вы различаете нейтральное приветствие, дружеское «Привет» и прощание.', incorrect: 'Сначала решите, начинается разговор или заканчивается. Дружеское «Привет» подходит для общения на «ты».' };
l1.tasks[3] = choose('Q04', 'Вас зовут Леа. Представьтесь новому знакомому.', ['NAME.INTRODUCE'], ['je-mappelle', 'comment-tu-tappelles', 'au-revoir'], 'je-mappelle',
  [fr('Je m’appelle Léa.', 'жё мапэль леа́'), expression('comment-tu-tappelles'), expression('au-revoir')],
  'Так сообщают своё имя. После готового выражения добавьте имя.', 'Здесь нужно сообщить своё имя: вопрос об имени собеседника и прощание решают другие задачи.');

// Lesson 2: recall yesterday's introduction, then learn new expressions in context.
addWords(l2, ['je-mappelle', 'comment-tu-tappelles', 'salut', 'au-revoir']);
l2.tasks.unshift(choose('Q01', 'Вспомните знакомство: вас зовут Поль. Как ответить?', ['NAME.INTRODUCE', 'NAME.UNDERSTAND'], ['je-mappelle', 'comment-tu-tappelles'], 'je-mappelle',
  [fr('Je m’appelle Paul.', 'жё мапэль по́ль'), expression('comment-tu-tappelles'), expression('au-revoir')],
  'На вопрос об имени вы ответили своим именем.', 'Собеседник уже спросил имя. Нужен ответ о себе, а не повтор вопроса.', { prompt: expression('comment-tu-tappelles') }));
renumber(l2);
l2.tasks[2].vocabulary_ids = ['ca-va-bien', 'merci', 'ca-va'];
l2.tasks[2].assessed_vocabulary_ids = ['ca-va-bien', 'merci', 'ca-va'];
l2.tasks[2].left_items = ['ca-va-bien', 'merci', 'ca-va'].map((id, i) => ({ id: 'l' + i, ...expression(id) }));
l2.tasks[2].right_items = ['ca-va-bien', 'merci', 'ca-va'].map((id, i) => ({ id: 'r' + i, ...ru(word(id).ru.join(' / ')) })).reverse();
l2.tasks[2].correct_pairs = ['ca-va-bien', 'merci', 'ca-va'].map((id, i) => ({ left_id: 'l' + i, right_id: 'r' + i, assessed_vocabulary_ids: [id] }));
l2.tasks[2].feedback = { correct: 'Вы отличаете вопрос, ответ «Всё хорошо» и благодарность.', incorrect: 'Одна реплика спрашивает, другая сообщает о самочувствии, третья благодарит.' };
l2.tasks[4] = wellOrder('Q05');
l2.tasks[6].objective_ids = [G('WELLBEING.REPLY'), G('WELLBEING.ASK')];
l2.tasks[6].skill = 'vocabulary';
l2.tasks[6].vocabulary_ids.push('salut', 'au-revoir', 'comment-tu-tappelles');
l2.introductions = [
  intro('Q01', 'Сначала коротко вспомним прошлое занятие. Попробуйте ответить по памяти; справка остаётся доступной.', []),
  intro('Q02', 'Новая тема — самочувствие. Спросите, как дела, ответьте «Всё хорошо» и поблагодарите за внимание.', ['ca-va', 'ca-va-bien', 'merci']),
  intro('Q04', 'Сравните «Очень хорошо» и «Не очень хорошо». Послушайте начало отрицательной реплики.', ['tres-bien', 'pas-tres-bien']),
  intro('Q07', 'После своего ответа можно вернуть собеседнику тот же вопрос: «А ты?» Это общение на «ты».', ['et-toi']),
];

// Lesson 3: four new expressions introduced in pairs; retrieve gratitude, no spelling.
addWords(l3, ['bonjour', 'bonsoir']);
l3.tasks.unshift(choose('Q01', 'Вспомните приветствия. Вечером вы входите в магазин: что сказать?', ['GREET.RECOGNIZE'], ['bonsoir', 'bonjour', 'au-revoir'], 'bonsoir',
  ['bonsoir', 'bonjour', 'au-revoir'].map(expression), 'Вечером уместно вечернее приветствие.', 'Вы входите, поэтому не прощайтесь. Для «Добрый вечер» есть отдельная реплика.'));
renumber(l3);
l3.tasks[2].left_items = l3.tasks[2].left_items.filter(i => i.text !== word('sil-vous-plait').fr);
const removed = l3.tasks[2].correct_pairs.find(p => p.assessed_vocabulary_ids.includes('sil-vous-plait'));
l3.tasks[2].right_items = l3.tasks[2].right_items.filter(i => i.id !== removed.right_id);
l3.tasks[2].correct_pairs = l3.tasks[2].correct_pairs.filter(p => p !== removed);
l3.tasks[2].vocabulary_ids = l3.tasks[2].assessed_vocabulary_ids = ['merci', 'pardon-apology', 'de-rien'];
l3.tasks[2].feedback = { correct: 'Вы различаете благодарность, извинение и ответ на благодарность.', incorrect: 'Один человек благодарит, другой отвечает «Не за что». Извинение нужно при неудобстве.' };
l3.tasks[5] = listen('Q06', 'Человек случайно задел соседа сумкой. Что означает его реплика?', ['POLITE.RECOGNIZE'], ['pardon-apology'], 'Pardon.', 'пардо́(н)',
  ['Он извиняется.', 'Он благодарит.', 'Он отвечает «Не за что».'], 'В этой ситуации человек извиняется за неудобство.', 'Он причинил неудобство и просит прощения. Это не благодарность.');
l3.tasks[6] = choose('Q07', 'Прохожий помог вам найти дорогу. Что сказать ему?', ['POLITE.USE', 'POLITE.RECOGNIZE'], ['merci', 'de-rien', 'sil-vous-plait'], 'merci',
  ['merci', 'de-rien', 'sil-vous-plait'].map(expression), 'Помощь получили вы, поэтому вы благодарите.', '«Не за что» отвечает тот, кого благодарят. Здесь благодарность произносите вы.');
l3.introductions = [
  intro('Q01', 'Начните с уже знакомого приветствия. Затем научимся вежливо попросить о помощи и поблагодарить.', []),
  intro('Q02', 'Две новые реплики: вежливо привлечь внимание и добавить «пожалуйста» к просьбе. Это формы для обращения на «вы».', ['excusez-moi', 'sil-vous-plait']),
  intro('Q03', 'Теперь две другие ситуации: вы случайно помешали человеку или вас поблагодарили. Выберите извинение или ответ «Не за что».', ['pardon-apology', 'de-rien']),
];

// Lesson 4: distinguish communication problems; build requests, not isolated grammar.
addWords(l4, ['de-rien']);
l4.tasks.unshift(choose('Q01', 'Вспомните прошлое занятие: вас благодарят. Выберите ответ.', ['POLITE.USE', 'POLITE.RECOGNIZE'], ['merci', 'de-rien', 'sil-vous-plait'], 'de-rien',
  ['de-rien', 'merci', 'sil-vous-plait'].map(expression), 'В ответ на благодарность подходит «Не за что».', 'Теперь благодарят вас. Выберите ответ на благодарность, а не просьбу.',
  { skill: 'listening', prompt: { ...ru('Послушайте собеседника и ответьте.'), audio: audio('Merci !', 'мэрси́!') } }));
renumber(l4);
// A situation question precedes matching so only the relevant new expression is shown.
[l4.tasks[2], l4.tasks[3]] = [l4.tasks[3], l4.tasks[2]];
renumber(l4);
const clarification = l4.tasks[3];
const removedClarification = clarification.correct_pairs.find(p => p.assessed_vocabulary_ids.includes('pardon-repeat'));
clarification.left_items = clarification.left_items.filter(i => i.id !== removedClarification.left_id);
clarification.right_items = clarification.right_items.filter(i => i.id !== removedClarification.right_id);
clarification.correct_pairs = clarification.correct_pairs.filter(p => p !== removedClarification);
clarification.vocabulary_ids = clarification.assessed_vocabulary_ids = ['repetez', 'plus-lentement', 'je-ne-comprends-pas'];
clarification.feedback = { correct: 'Вы различаете просьбу повторить, просьбу замедлиться и сообщение о непонимании.', incorrect: 'Определите намерение: услышать ещё раз, снизить темп или сообщить, что смысл непонятен.' };
l4.tasks[5] = repeatOrder('Q06');
l4.tasks[6] = choose('Q07', 'Вы не расслышали последнее слово. Выберите короткое «Простите?»', ['CLARIFY.REQUEST', 'CLARIFY.UNDERSTAND'], ['pardon-repeat', 'merci', 'de-rien'], 'pardon-repeat',
  ['pardon-repeat', 'merci', 'de-rien'].map(expression), 'Вопросительная интонация помогает переспросить.', 'Здесь нужно переспросить. Благодарность и ответ на неё не говорят, что вы не расслышали.');
l4.introductions = [
  intro('Q01', 'Короткое повторение вежливого разговора, затем новые способы попросить помощь в понимании.', []),
  intro('Q02', 'Если не расслышали — попросите повторить. Если речь слишком быстрая — попросите говорить медленнее. Вежливое окончание вы уже знаете.', ['repetez', 'plus-lentement']),
  intro('Q03', 'Если слова слышны, но смысл непонятен, сообщите об этом прямо. Запомните готовую фразу целиком.', ['je-ne-comprends-pas']),
  intro('Q07', 'Можно коротко переспросить: «Простите?» Послушайте вопросительную интонацию. Это другая ситуация, чем извинение за поступок.', ['pardon-repeat']),
];

// Lesson 5: retrieval and transfer, no new vocabulary dump before the questions.
addWords(l5, ['pas-tres-bien', 'plus-lentement', 'sil-vous-plait']);
l5.tasks = [
  listen('Q01', 'Начинается знакомство. Что сказал собеседник?', ['GREET.RECOGNIZE', 'NAME.UNDERSTAND'], ['bonjour', 'comment-tu-tappelles'], 'Bonjour ! Comment tu t’appelles ?', 'бо(н)жу́р! кома(н) тю тапэ́ль?',
    ['Поздоровался и спросил ваше имя.', 'Поздоровался и спросил, как дела.', 'Попрощался и поблагодарил.'], 'Вы узнали приветствие и вопрос об имени в одной реплике.', 'После приветствия нужно назвать имя: это вопрос о знакомстве, а не о самочувствии.'),
  order('Q02', 'Ответьте новому знакомому: «Меня зовут Леа».', ['NAME.INTRODUCE'], ['je-mappelle'],
    [['Je', 'жё'], ['m’appelle', 'мапэ́ль'], ['Léa.', 'леа́']], 'Je m’appelle Léa.', 'жё мапэль леа́', 'Вы ответили на вопрос и назвали имя.', 'Начните с «я», затем поставьте выражение «зовут» и имя.'),
  listen('Q03', 'Вы спросили, как дела. Как чувствует себя знакомый?', ['WELLBEING.UNDERSTAND'], ['pas-tres-bien'], 'Pas très bien.', 'па трэ бье́(н)',
    ['Не очень хорошо.', 'Очень хорошо.', 'Хорошо, спасибо.'], 'Вы различили отрицательный ответ на слух.', 'Не пропускайте начало реплики: оно меняет её смысл.'),
  choose('Q04', 'Вы рассказали, как у вас дела. Верните тот же вопрос знакомому.', ['WELLBEING.ASK'], ['et-toi', 'comment-tu-tappelles', 'merci'], 'et-toi',
    ['et-toi', 'comment-tu-tappelles', 'merci'].map(expression), 'Вы сохранили тему разговора и поинтересовались собеседником.', 'Имя вы уже знаете. Сейчас нужен ответный вопрос о самочувствии.'),
  wellOrder('Q05'),
  choose('Q06', 'В кафе вы просите меню. Как добавить к просьбе «пожалуйста»?', ['POLITE.USE', 'POLITE.RECOGNIZE'], ['sil-vous-plait', 'merci', 'de-rien'], 'sil-vous-plait',
    ['sil-vous-plait', 'merci', 'de-rien'].map(expression), 'Знакомую формулу можно использовать и в новой ситуации — в кафе.', 'Пока вы просите, нужна формула просьбы. Благодарность последует после помощи.'),
  listen('Q07', 'Послушайте просьбу другого ученика. В чём затруднение?', ['CLARIFY.UNDERSTAND'], ['plus-lentement'], 'Plus lentement, s’il vous plaît.', 'плю ла(н)тёма(н), силь ву плэ́',
    ['Собеседник говорит слишком быстро.', 'Ученик хочет узнать имя.', 'Ученик хочет попрощаться.'], 'Он просит снизить темп речи.', 'Это просьба говорить медленнее, а не вопрос об имени.'),
  repeatOrder('Q08'),
];
l5.introductions = [intro('Q01', 'Это закрепление: новых выражений нет. Вспоминайте реплики в коротких ситуациях. При необходимости откройте справку; после ответа можно послушать фразу и повторить её вслух.', [])];

const descriptions = [
  'Поздоровайтесь, попрощайтесь и представьтесь по имени. Короткие примеры, аудирование и карточки без набора текста.',
  'Вспомните знакомство, спросите о самочувствии и поддержите разговор. Выбирайте ответы и собирайте реплики из карточек.',
  'Вежливо обратитесь, попросите о помощи и поблагодарите. Тренируйте реплики в разных ситуациях без печати.',
  'Попросите повторить или говорить медленнее и сообщите о непонимании. Сначала пример, затем выбор и сборка фразы.',
  'Закрепите знакомство, самочувствие и вежливые просьбы в новых ситуациях. Без новых слов и без набора текста.',
];
const reviewContexts = {
  'bonjour': ['Вы входите в магазин днём. Выберите нейтральное приветствие.', ['bonjour', 'bonsoir', 'au-revoir']],
  'salut': ['Вы встречаете друга. Выберите короткое неформальное «Привет».', ['salut', 'bonsoir', 'au-revoir']],
  'bonsoir': ['Вы входите в магазин вечером. Скажите «Добрый вечер».', ['bonsoir', 'bonjour', 'au-revoir']],
  'au-revoir': ['Разговор закончен, вы уходите. Попрощайтесь.', ['au-revoir', 'bonjour', 'bonsoir']],
  'je-mappelle': ['Как начинается ответ «Меня зовут…» на вопрос нового знакомого?', ['je-mappelle', 'comment-tu-tappelles', 'bonjour']],
  'comment-tu-tappelles': ['Вы назвали своё имя. Теперь узнайте имя нового знакомого.', ['comment-tu-tappelles', 'je-mappelle', 'au-revoir']],
  'ca-va': ['Встретив друга, спросите: «Как дела?»', ['ca-va', 'comment-tu-tappelles', 'ca-va-bien']],
  'ca-va-bien': ['Вас спросили о самочувствии. Ответьте: «Всё хорошо».', ['ca-va-bien', 'pas-tres-bien', 'ca-va']],
  'tres-bien': ['Ответьте о самочувствии: «Очень хорошо».', ['tres-bien', 'pas-tres-bien', 'merci']],
  'pas-tres-bien': ['Вас спросили, как дела. Ответьте: «Не очень хорошо».', ['pas-tres-bien', 'tres-bien', 'ca-va']],
  'merci': ['Вам помогли найти дорогу. Поблагодарите.', ['merci', 'et-toi', 'ca-va']],
  'et-toi': ['Вы рассказали о своём самочувствии. Верните вопрос: «А ты?»', ['et-toi', 'comment-tu-tappelles', 'merci']],
  'sil-vous-plait': ['Вы просите книгу. Добавьте к просьбе «пожалуйста».', ['sil-vous-plait', 'merci', 'de-rien']],
  'excusez-moi': ['Вежливо привлеките внимание незнакомого человека: «Извините меня».', ['excusez-moi', 'merci', 'au-revoir']],
  'pardon-apology': ['Вы случайно наступили человеку на ногу. Выберите короткое извинение.', ['pardon-apology', 'merci', 'de-rien']],
  'de-rien': ['Вас поблагодарили. Ответьте «Не за что».', ['de-rien', 'merci', 'sil-vous-plait']],
  'repetez': ['Вы не расслышали. Попросите повторить полной вежливой фразой.', ['repetez', 'plus-lentement', 'je-ne-comprends-pas']],
  'plus-lentement': ['Вам трудно следить за быстрой речью. Попросите снизить темп.', ['plus-lentement', 'repetez', 'je-ne-comprends-pas']],
  'je-ne-comprends-pas': ['Слова слышны, но смысл непонятен. Сообщите: «Я не понимаю».', ['je-ne-comprends-pas', 'repetez', 'plus-lentement']],
  'pardon-repeat': ['Вы не расслышали. Коротко переспросите: «Простите?»', ['pardon-repeat', 'merci', 'de-rien']],
};
for (const [index, session] of sessions.entries()) {
  session.version = 2;
  session.duration_minutes = session.tasks.length === 8 ? 6 : 5;
  session.description = descriptions[index];
  session.public_page.summary = session.public_page.seo_description = descriptions[index];
  session.skills = [...new Set(session.tasks.map(t => t.skill))];
  session.objectives = [...new Set(session.tasks.flatMap(t => t.objective_ids))].map(id => copy(goals.get(id)));
  for (const t of session.tasks) {
    if (t.type === 'word_order' && t.tokens.map(i => i.id).join() === t.accepted_orders[0].join()) t.tokens = [...t.tokens.slice(1), t.tokens[0]];
  }
  for (const w of session.vocabulary) {
    const [instruction, ids] = reviewContexts[w.id];
    w.review_items = [copy(w.review_items.find(i => i.type === 'single_choice')), {
      id: 'R02', type: 'single_choice', instruction, prompt: ru('Сначала попробуйте вспомнить реплику, затем выберите её.'),
      choices: ids.map((id, i) => ({ id: 'c' + i, ...expression(id) })), correct_choice_id: 'c0',
      feedback: { correct: 'Эта реплика подходит: «' + w.ru.join(' / ') + '».', incorrect: 'Здесь нужно: «' + w.ru.join(' / ') + '». Сравните цель реплики с ситуацией.' },
    }];
  }
}
const result = { ...copy(original), package_id: 'programme-pre-a1-u01-v2', sessions };
writeFileSync(path.join(root, 'packages/PRE_A1/PRE_A1-U01.v2.json'), JSON.stringify(result, null, 2) + '\n');
console.log('Prepared v2: 5 sessions, ' + sessions.reduce((n, s) => n + s.tasks.length, 0) + ' tasks, no typing; v1 untouched.');
