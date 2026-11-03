import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root=path.dirname(fileURLToPath(import.meta.url));
const base=JSON.parse(readFileSync(path.join(root,'../templates/pre-a1-u01-examples.json'),'utf8'));
const copy=structuredClone;
const original=base.sessions.flatMap(s=>s.vocabulary);
const get=(id)=>copy(original.find(w=>w.id===id));
const ru=(text)=>({text,text_lang:'ru'});
const fr=(text,transcription_ru)=>({text,text_lang:'fr',transcription_ru});
const note=(fr,transcription_ru)=>({fr,transcription_ru});
const politeR={id:'PRE_A1.POLITE.RECOGNIZE',title:'Различать вежливую просьбу, извинение и ответ на благодарность'};
const politeU={id:'PRE_A1.POLITE.USE',title:'Выбирать вежливую реплику по ситуации'};
const clarifyR={id:'PRE_A1.CLARIFY.UNDERSTAND',title:'Понимать просьбу повторить или говорить медленнее'};
const clarifyU={id:'PRE_A1.CLARIFY.REQUEST',title:'Просить повторить и сообщать о непонимании'};
const word=(id,lexeme_key,text,trans,translation,distractors)=>({id,lexeme_key,fr:text,ru:[translation],transcription_ru:trans,dictionary_word_id:null,
 review_items:[{id:'R01',type:'single_choice',instruction:'Выберите значение выражения.',prompt:fr(text,trans),choices:[{id:'a',...ru(translation)},...distractors.map((t,i)=>({id:['b','c'][i],...ru(t)}))],correct_choice_id:'a',feedback:{correct:'Выражение означает: «'+translation+'».',incorrect:'Здесь нужно значение «'+translation+'». Сопоставьте назначение реплики с ситуацией.'}}]});
const sil=word('sil-vous-plait','fr:sil-vous-plait:polite-request','s’il vous plaît','силь ву плэ́','Пожалуйста (при просьбе).',['Спасибо.','Не за что.']);
const pardon=word('pardon-apology','fr:pardon:apology','pardon','пардо́(н)','Простите (извинение).',['До свидания.','Пожалуйста (при просьбе).']);
const excuse=word('excusez-moi','fr:excusez-moi:polite-attention','excusez-moi','экскюзэ муа́','Извините (вежливое обращение).',['Меня зовут…','Очень хорошо.']);
const rien=word('de-rien','fr:de-rien:reply-to-thanks','de rien','дё рье́(н)','Не за что.',['Спасибо.','Повторите, пожалуйста.']);
const repeat=word('repetez','fr:repetez-sil-vous-plait:request-repeat','répétez, s’il vous plaît','рэпэтэ, силь ву плэ́','Повторите, пожалуйста.',['Говорите медленнее, пожалуйста.','Я не понимаю.']);
const slow=word('plus-lentement','fr:plus-lentement-sil-vous-plait:request-slower','plus lentement, s’il vous plaît','плю ла(н)тёма(н), силь ву плэ́','Говорите медленнее, пожалуйста.',['Повторите, пожалуйста.','Всё хорошо, спасибо.']);
const understand=word('je-ne-comprends-pas','fr:je-ne-comprends-pas:not-understanding','je ne comprends pas','жё нё ко(н)пра(н) па́','Я не понимаю.',['Меня зовут…','А ты?']);
const pardonRepeat=word('pardon-repeat','fr:pardon:request-repeat','pardon ?','пардо́(н)?','Простите? (просьба повторить).',['Простите (извинение за поступок).','Спасибо.']);
const task=(id,type,instruction,skill,goals,ids,correct,incorrect,extra={},assessed=ids)=>({id,type,instruction,prompt:ru('Выполните задание по ситуации.'),feedback:{correct,incorrect},objective_ids:goals.map(g=>g.id),skill,vocabulary_ids:ids,assessed_vocabulary_ids:assessed,...extra});
const choice=(id,instruction,skill,goals,words,options,answer,feedback,prompt)=>task(id,'single_choice',instruction,skill,goals,words,feedback[0],feedback[1],{prompt:prompt??ru('Выберите одну реплику.'),choices:options.map(([text,trans],i)=>({id:['a','b','c'][i],...(trans?fr(text,trans):ru(text))})),correct_choice_id:answer});
const match=(id,goals,words)=>task(id,'matching_pairs','Сопоставьте выражения с их значением.','vocabulary',goals,words.map(w=>w.id),'Все пары передают разные намерения: просьбу, благодарность, извинение или уточнение.','Сначала определите, чего хочет говорящий. Не путайте просьбу и ответ на благодарность.',{left_items:words.map((w,i)=>({id:'l'+i,...fr(w.fr,w.transcription_ru)})),right_items:[...words].reverse().map(w=>({id:'r'+words.indexOf(w),...ru(w.ru.join(' / '))})),correct_pairs:words.map((w,i)=>({left_id:'l'+i,right_id:'r'+i,assessed_vocabulary_ids:[w.id]}))});
const order=(id,instruction,goals,ids,tokens,complete,trans,reason)=>task(id,'word_order',instruction,'sentence_building',goals,ids,reason,reason,{prompt:ru('Нажимайте карточки в нужном порядке.'),tokens:tokens.map(([text,t],i)=>({id:'t'+i,...fr(text,t)})),accepted_orders:[tokens.map((_,i)=>'t'+i)],completed_phrases:[note(complete,trans)]},[]);
const short=(id,instruction,goals,ids,answer,trans,reason)=>task(id,'short_answer',instruction,'vocabulary',goals,ids,reason,reason,{prompt:ru('Введите только требуемое выражение.'),answer_lang:'fr',accepted_answers:[answer],pronunciation_notes:[note(answer,trans)]});
const audio=(text,trans)=>({text,transcription_ru:trans,lang:'fr-FR',asset_id:null});
const block=(heading,paragraphs,examples)=>({heading,paragraphs,examples:examples.map(w=>({fr:w.fr,transcription_ru:w.transcription_ru,ru:w.ru.join(' / ')}))});
const session=(number,title,slug,description,goals,vocabulary,tasks,blocks)=>({id:'PRE_A1-U01-L0'+number,version:1,level:'PRE_A1',unit_id:'PRE_A1-U01',order:number,kind:'practice',
 prerequisite_ids:['PRE_A1-U01-L0'+(number-1)],title,description,duration_minutes:5,objectives:goals,skills:[...new Set(tasks.map(t=>t.skill))],
 introductions:[{id:'I01',before_task_id:'Q01',text:'Познакомьтесь с готовыми репликами и их значениями. Русская запись произношения приблизительна; носовые гласные отмечены (н) без отдельного звука «н». Не нужно заранее знать спряжение: здесь важны цель реплики и ситуация.',vocabulary_ids:vocabulary.map(w=>w.id)}],
 vocabulary,tasks,public_page:{slug,seo_title:title+' — французский с нуля',seo_description:description,summary:description,explanation_blocks:blocks,related_session_ids:['PRE_A1-U01-L01','PRE_A1-U01-L02']}});
const l3words=[get('merci'),copy(sil),copy(pardon),copy(excuse),copy(rien),get('au-revoir')];
const l3tasks=[
 choice('Q01','Послушайте вежливое обращение. Что делает говорящий?','listening',[politeR],['excusez-moi'],[['Вежливо привлекает внимание.'],['Благодарит за помощь.'],['Сообщает своё имя.']],'a',['Так начинают вежливое обращение к собеседнику.','Это обращение, а не благодарность и не представление.'],{text:'Послушайте реплику и выберите её назначение.',text_lang:'ru',audio:audio('Excusez-moi.','экскюзэ муа́')}),
 match('Q02',[politeR],[get('merci'),sil,pardon,rien]),
 choice('Q03','Вы просите передать книгу. Какая реплика делает просьбу вежливой?','vocabulary',[politeU],['sil-vous-plait','de-rien','au-revoir'],[[sil.fr,sil.transcription_ru],[rien.fr,rien.transcription_ru],[get('au-revoir').fr,get('au-revoir').transcription_ru]],'a',['К просьбе добавляют выражение «пожалуйста».','Ответ на благодарность и прощание не оформляют просьбу.']),
 order('Q04','Вас поблагодарили. Соберите ответ «Не за что».',[politeU],['de-rien'],[['De','дё'],['rien.','рье́(н)']],'De rien.','дё рье́(н)','В этой ситуации нужен ответ на благодарность, а не ещё одна просьба.'),
 task('Q05','fill_blank','Завершите вежливое «пожалуйста».','grammar',[politeU],['sil-vous-plait'],'Это готовое выражение вежливой просьбы. Запомните его целиком.','Завершение этого устойчивого выражения не заменяется словом благодарности.',{prompt:ru('Восстановите середину выражения.'),text_before:'S’il ',text_after:' plaît.',text_before_transcription_ru:'силь',text_after_transcription_ru:'плэ́',input_mode:'choice',choices:[{id:'a',...fr('vous','ву́')},{id:'b',...fr('toi','туа́')},{id:'c',...fr('moi','муа́')}],correct_choice_id:'a',completed_phrases:[note('S’il vous plaît.','силь ву плэ́')]},[]),
 short('Q06','Напишите по-французски «Спасибо».',[politeR,politeU],['merci'],'Merci.','мэрси́','Благодарность — отдельная реплика; её не подменяют просьбой или прощанием.'),
];
const l3=session(3,'Вежливые слова','vezhlivye-slova','Научитесь вежливо обратиться, оформить просьбу, извиниться и ответить на благодарность.',[politeR,politeU],l3words,l3tasks,[
 block('Просьба и обращение',['Вежливое обращение помогает начать разговор с незнакомым человеком. К самой просьбе добавляют отдельное выражение вежливости. Здесь вы изучаете готовые реплики для обращения к незнакомому человеку или преподавателю.'],[excuse,sil]),
 block('Благодарность и извинение',['Когда вам помогли, поблагодарите. В ответ на благодарность говорят, что благодарить не за что. Извинение за неудобство — другая цель разговора; не смешивайте её с просьбой.'],[get('merci'),rien,pardon]),
 block('Разобранный пример',['Вы случайно задели соседа сумкой. Подойдёт извинение. Если же сосед уже передал вам книгу, нужна благодарность; ответ на благодарность произнесёт он.'],[pardon,get('merci'),rien]),
]);
const l4words=[copy(repeat),copy(slow),copy(understand),copy(pardonRepeat),get('merci'),copy(sil)];
const l4tasks=[
 choice('Q01','Послушайте просьбу. Что нужно сделать собеседнику?','listening',[clarifyR],['repetez'],[['Повторить сказанное.'],['Назвать своё имя.'],['Попрощаться.']],'a',['Говорящий просит произнести сказанное ещё раз.','Просьба касается повторения, а не имени и не завершения разговора.'],{text:'Послушайте и выберите действие.',text_lang:'ru',audio:audio('Répétez, s’il vous plaît.','рэпэтэ, силь ву плэ́')}),
 match('Q02',[clarifyR],[repeat,slow,understand,pardonRepeat]),
 choice('Q03','Собеседник говорит слишком быстро. Попросите его снизить темп.','vocabulary',[clarifyU],['plus-lentement','repetez','je-ne-comprends-pas'],[[slow.fr,slow.transcription_ru],[repeat.fr,repeat.transcription_ru],[understand.fr,understand.transcription_ru]],'a',['Просьба о более медленной речи указывает именно на темп.','Повторение и сообщение о непонимании не содержат прямой просьбы снизить темп.']),
 order('Q04','Соберите реплику «Я не понимаю».',[clarifyU],['je-ne-comprends-pas'],[['Je','жё'],['ne','нё'],['comprends','ко(н)пра(н)'],['pas.','па́']],'Je ne comprends pas.','жё нё ко(н)пра(н) па́','В нейтральной учебной реплике отрицание окружает глагол: сначала говорящий, затем первая часть отрицания, глагол и завершающая часть.'),
 task('Q05','fill_blank','Попросите произнести сказанное ещё раз.','grammar',[clarifyU],['repetez','sil-vous-plait'],'Вы восстановили полную знакомую формулу вежливой просьбы.','Устойчивую формулу вежливой просьбы восстановите целиком: её слова нельзя заменять произвольно.',{prompt:ru('Восстановите знакомую реплику.'),text_before:'Répétez, s’il ',text_after:' plaît.',text_before_transcription_ru:'рэпэтэ, силь',text_after_transcription_ru:'плэ́',input_mode:'choice',choices:[{id:'a',...fr('vous','ву́')},{id:'b',...fr('toi','туа́')},{id:'c',...fr('moi','муа́')}],correct_choice_id:'a',completed_phrases:[note('Répétez, s’il vous plaît.','рэпэтэ, силь ву плэ́')]},[]),
 short('Q06','Вы не расслышали реплику. Напишите короткое «Простите?» в значении просьбы повторить.',[clarifyR,clarifyU],['pardon-repeat'],'Pardon ?','пардо́(н)?','Вопросительная интонация превращает короткое слово в просьбу повторить. Это не сообщение о самочувствии.'),
];
const l4=session(4,'Повторите, пожалуйста','povtorite-pozhaluysta','Научитесь сообщать о непонимании, просить повторить и говорить медленнее.',[clarifyR,clarifyU],l4words,l4tasks,[
 block('Три разных затруднения',['Если вы не расслышали, попросите повторить. Если трудно следить за темпом, попросите говорить медленнее. Если смысл остался непонятен, сообщите об этом прямо. Эти реплики не полностью взаимозаменяемы.'],[repeat,slow,understand]),
 block('Короткое уточнение',['Короткое вопросительное «Простите?» помогает переспросить. Похожее слово с невопросительной интонацией может быть извинением за поступок, поэтому значения словаря хранятся раздельно.'],[pardonRepeat]),
 block('Разобранный пример',['Вы услышали длинную реплику, но не успели различить слова. Прямая просьба о более медленном темпе точнее общего сообщения о непонимании. После повторения можно поблагодарить.'],[slow,get('merci')]),
]);
const allGoals=[...base.sessions.flatMap(s=>s.objectives),politeR,politeU,clarifyR,clarifyU];
const goal=(id)=>allGoals.find(g=>g.id===id);
const g=(...ids)=>ids.map(goal);
const l5words=[get('bonjour'),get('je-mappelle'),get('comment-tu-tappelles'),get('ca-va'),get('ca-va-bien'),get('et-toi'),get('merci'),copy(rien),copy(repeat),copy(excuse),get('au-revoir')];
const l5tasks=[
 choice('Q01','Послушайте начало разговора. О чём спрашивает говорящий?','listening',g('PRE_A1.GREET.RECOGNIZE','PRE_A1.NAME.UNDERSTAND'),['bonjour','comment-tu-tappelles'],[['Об имени собеседника.'],['О времени встречи.'],['О цене книги.']],'a',['После приветствия прозвучал вопрос об имени.','Содержание вопроса — имя, а не время и не покупка.'],{text:'Послушайте короткую реплику.',text_lang:'ru',audio:audio('Bonjour ! Comment tu t’appelles ?','бо(н)жу́р! кома(н) тю тапэ́ль?')}),
 choice('Q02','Вас спросили, как вас зовут. Какая реплика отвечает на вопрос?','vocabulary',g('PRE_A1.NAME.INTRODUCE','PRE_A1.NAME.UNDERSTAND','PRE_A1.POLITE.RECOGNIZE'),['je-mappelle','merci','excusez-moi'],[['Je m’appelle Léa.','жё мапэль леа́'],['Merci.','мэрси́'],['Excusez-moi.','экскюзэ муа́']],'a',['Ответ должен содержать представление по имени. Вежливые слова не заменяют нужную информацию.','Благодарность и вежливое обращение уместны в других местах разговора, но имени не сообщают.']),
 match('Q03',g('PRE_A1.WELLBEING.UNDERSTAND','PRE_A1.WELLBEING.REPLY'),[get('ca-va'),get('ca-va-bien'),get('merci')]),
 order('Q04','Вы уже поблагодарили собеседника. Соберите его ответ «Не за что».',[politeU],['de-rien'],[['De','дё'],['rien.','рье́(н)']],'De rien.','дё рье́(н)','После благодарности собеседник отвечает, что благодарить не за что. Не путайте говорящих.'),
 task('Q05','short_answer','Попросите собеседника повторить; используйте полную вежливую реплику.','sentence_building',[clarifyR,clarifyU],['repetez'],'Полная просьба сочетает действие и вежливое завершение.','Нужно именно повторение; просьба замедлить речь меняет смысл.',{prompt:ru('Напишите по-французски «Повторите, пожалуйста». Затем сравните произношение.'),answer_lang:'fr',accepted_answers:['Répétez, s’il vous plaît.'],pronunciation_notes:[note('Répétez, s’il vous plaît.','рэпэтэ, силь ву плэ́')]}),
 choice('Q06','Вы ответили, что у вас всё хорошо. Верните собеседнику вопрос о самочувствии.','vocabulary',g('PRE_A1.WELLBEING.ASK'),['et-toi','au-revoir','je-mappelle'],[['Et toi ?','э туа́?'],['Au revoir !','о рёвуа́р'],['Je m’appelle Léa.','жё мапэль леа́']],'a',['Ответный вопрос приглашает собеседника рассказать о себе.','Прощание заканчивает разговор, а представление по имени меняет тему.']),
];
const l5=session(5,'Короткий разговор целиком','korotkiy-razgovor','Соедините приветствие, имя, вопрос о самочувствии, вежливое уточнение и завершение разговора.',allGoals,l5words,l5tasks,[
 block('Не меняйте цель реплики',['В коротком разговоре каждая реплика отвечает на конкретный вопрос или совершает действие. После вопроса об имени сообщите имя; после вопроса о самочувствии дайте ответ. Благодарность не заменяет информацию.'],[get('je-mappelle'),get('ca-va-bien'),get('merci')]),
 block('Поддержите разговор',['Ответив о себе, можно вернуть тот же вопрос. Если вы не поняли собеседника, попросите повторить вместо случайного ответа.'],[get('et-toi'),repeat]),
 block('Разобранный пример',['Вы познакомились, назвали имя и обменялись вопросами о самочувствии. Собеседник помог понять новую реплику: поблагодарите; в конце используйте прощание.'],[get('merci'),rien,get('au-revoir')]),
]);
base.package_id='programme-pre-a1-u01-v1';
base.sessions.push(l3,l4,l5);
for(const session of base.sessions) {
 if(session.order>=3) {
  for(const task of session.tasks) {
   if(task.objective_ids.some(id=>!id)) throw new Error('Unknown objective in '+session.id+'/'+task.id);
  }
 }
}
const target=path.join(root,'packages/PRE_A1');mkdirSync(target,{recursive:true});
writeFileSync(path.join(target,'PRE_A1-U01.v1.json'),JSON.stringify(base,null,2)+'\n');
console.log('Authored PRE_A1-U01: five sessions, preserving the two existing snapshots.');