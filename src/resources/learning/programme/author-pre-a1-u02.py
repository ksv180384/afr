"""Generate the first PRE_A1-U02 draft; no existing package is overwritten."""
import json, copy, re, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][1]
prev=json.loads((KIT/'examples/PRE_A1-U01.v2.json').read_text())
W={}
for s in prev['sessions']:
 for w in s['vocabulary']: W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:
  w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
W['je-mappelle']['example']=dict(fr='Je m’appelle Lila.',ru='Меня зовут Лила.',transcription_ru='жё мапэль лила́')
OLD=set(W); FIRST={}; S=[]
U='PRE_A1.U02.UNDERSTAND'; A='PRE_A1.U02.APPLY'
OBJECTIVES={o['id']:o for u in level['units'] for o in u['objectives']}
def ru(t): return dict(text=t,text_lang='ru')
def fr(t,p): return dict(text=t,text_lang='fr',transcription_ru=p)
def ex(f,r,p): return dict(fr=f,ru=r,transcription_ru=p)
def sound(f,p): return dict(text=f,transcription_ru=p,lang='fr-FR',asset_id=None)
def wc(w): return fr(W[w]['fr'],W[w]['transcription_ru'])
def fb(g,b): return dict(correct=g,incorrect=b)
def opts(v): return [dict(id=f'c{i}',**(ru(t) if isinstance(t,str) else t)) for i,t in enumerate(v)]
def rev(i,p,cs,a,g,b): return dict(id=i,type='single_choice',instruction='Выберите один ответ.',prompt=p,choices=opts(cs),correct_choice_id=f'c{a}',feedback=fb(g,b))
def letter(i,b,p,n,alt):
 W[i]=dict(id=i,lexeme_key=f'fr:{b.lower()}:letter-name',fr=b.lower(),ru=[f'Название французской буквы {b}'],transcription_ru=p,example=ex(f'La lettre {b}.',f'Буква {b}.',f'ла лэтр {p}'),review_items=[
 rev(i+'-meaning',fr(b,p),['Вежливая просьба',f'Буква {b}','Прощание'],1,'Это название буквы алфавита.',f'Здесь названа буква {b}, а не реплика разговора.'),
 rev(i+'-use',ru(f'В имени нужно уточнить именно букву {b}. Какое название выбрать?'),[fr(*alt[0]),fr(*alt[1]),fr(b,p)],2,f'Вы выбрали название буквы {b}.',f'Нужно название буквы {b}; другие карточки обозначают другие буквы.')])
 FIRST[i]=n
letter('letter-a','A','а',1,[('I','и'),('O','о')])
letter('letter-i','I','и',1,[('A','а'),('O','о')])
letter('letter-o','O','о',1,[('I','и'),('A','а')])
letter('letter-l','L','эль',2,[('M','эм'),('I','и')])
letter('letter-m','M','эм',2,[('L','эль'),('A','а')])
def word(i,key,f,r,p,e,n,ds,q,cs,a,g):
 W[i]=dict(id=i,lexeme_key=key,fr=f,ru=[r],transcription_ru=p,example=e,review_items=[
 rev(i+'-meaning',fr(f,p),[ds[0],r,ds[1]],1,f'В этом контексте значение: «{r}».',f'Правильное значение: «{r}».'),
 rev(i+'-use',ru(q),cs,a,g,g)])
 FIRST[i]=n
word('nom-family-name','fr:nom:family-name','le nom','Фамилия (в поле анкеты)','лё но́(н)',ex('Nom : Lamy.','Фамилия: Лами.','но(н) : лами́'),3,['Личное имя','Приветствие'],
 'В анкете есть отдельные поля для имени и фамилии. Куда вписать фамилию Лами?', [fr('Nom','но́(н)'),'Поле личного имени','Поле приветствия'],0,'В анкете с раздельными полями эта подпись обозначает фамилию.')
word('non','fr:non:negative-answer','non','Нет','но́(н)',ex('Non, merci.','Нет, спасибо.','но(н), мэрси́'),3,['Да','Спасибо'],
 'Вам предлагают помощь, но она не нужна. Как вежливо отказаться?',[fr('Oui, merci.','уи, мэрси́'),fr('Non, merci.','но(н), мэрси́'),wc('bonjour')],1,'Для отказа подходит «Нет, спасибо»; согласие означало бы принятие помощи.')
word('oui','fr:oui:affirmative-answer','oui','Да','уи́',ex('Oui, merci.','Да, спасибо.','уи, мэрси́'),3,['Нет','До свидания'],
 'Вам предлагают повторить имя. Вы согласны. Как ответить?',[fr('Non, merci.','но(н), мэрси́'),wc('au-revoir'),fr('Oui, merci.','уи, мэрси́')],2,'Согласие выражается ответом «Да, спасибо»; отказ и прощание не подходят.')
word('epelez','fr:epelez-sil-vous-plait:request-spelling','épelez, s’il vous plaît','Назовите по буквам, пожалуйста','эплэ, силь ву плэ́',ex('Épelez, s’il vous plaît.','Назовите по буквам, пожалуйста.','эплэ, силь ву плэ́'),4,['Повторите целиком, пожалуйста','Говорите медленнее, пожалуйста'],
 'Вы услышали имя, но хотите записать каждую букву. Что попросить?',[wc('repetez'),fr('Épelez, s’il vous plaît.','эплэ, силь ву плэ́'),wc('plus-lentement')],1,'Чтобы уточнить написание, попросите назвать по буквам. Повторить целиком и замедлить речь — другие просьбы.')
def start(n,d,vs):
 p=plan['sessions'][n-1]; s={k:copy.deepcopy(p[k]) for k in ['id','title','order','kind','prerequisite_ids']}
 s.update(version=1,level='PRE_A1',unit_id=plan['id'],description=d,duration_minutes=6,objectives=copy.deepcopy(plan['objectives']),skills=[],vocabulary=[copy.deepcopy(W[w]) for w in vs],tasks=[],introductions=[]); S.append(s); return s
def base(s,sk,gs,vs,av,ins,p,g,b):
 for o in gs:
  if o not in [x['id'] for x in s['objectives']]: s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))
 if sk not in s['skills']: s['skills'].append(sk)
 t=dict(id=f'Q{len(s["tasks"])+1:02}',instruction=ins,prompt=p,feedback=fb(g,b),objective_ids=gs,skill=sk,vocabulary_ids=vs,assessed_vocabulary_ids=av); s['tasks'].append(t); return t
def choose(s,sk,gs,vs,av,ins,p,cs,a,g,b):
 t=base(s,sk,gs,vs,av,ins,p,g,b); t.update(type='single_choice',choices=opts(cs),correct_choice_id=f'c{a}'); return t
def listen(s,gs,vs,av,q,f,p,cs,a,g,b):
 return choose(s,'listening',gs,vs,av,'Прослушайте и выберите ответ.',dict(**ru(q),audio=sound(f,p)),cs,a,g,b)
def intro(s,n,t,vs,ns=(),au=None):
 i=dict(id=f'I{len(s["introductions"])+1:02}',before_task_id=f'Q{n:02}',text=t,vocabulary_ids=vs)
 if ns:i['pronunciation_notes']=[dict(fr=f,transcription_ru=p) for f,p in ns]
 if au:i['audio']=sound(*au)
 s['introductions'].append(i)
def pairs(s,gs,rows,g,b):
 vs=[w for w,r in rows]; t=base(s,'vocabulary',gs,vs,vs,'Соедините каждую карточку с её значением.',ru('Используйте все карточки по одному разу.'),g,b)
 t.update(type='matching_pairs',left_items=[dict(id=f'l{i}',**wc(w)) for i,(w,r) in enumerate(rows)],right_items=[dict(id=f'r{i}',**ru(r)) for i,(w,r) in reversed(list(enumerate(rows)))],correct_pairs=[dict(left_id=f'l{i}',right_id=f'r{i}',assessed_vocabulary_ids=[w]) for i,(w,r) in enumerate(rows)])
def order(s,gs,vs,av,ins,ts,f,p,g,b):
 t=base(s,'sentence_building',gs,vs,av,ins,ru('Нажимайте на карточки в нужном порядке.'),g,b); tokens=[dict(id=f't{i}',**fr(f,p)) for i,(f,p) in enumerate(ts)]
 t.update(type='word_order',tokens=list(reversed(tokens)),accepted_orders=[[x['id'] for x in tokens]],completed_phrases=[dict(fr=f,transcription_ru=p)])
def fill(s,gs,vs,av,ins,bef,bp,aft,ap,cs,a,f,p,g,b):
 t=base(s,'vocabulary',gs,vs,av,ins,ru('Выберите одну карточку для пропуска.'),g,b)
 t.update(type='fill_blank',input_mode='choice',text_before=bef,text_before_transcription_ru=bp,text_after=aft,text_after_transcription_ru=ap,choices=opts(cs),correct_choice_id=f'c{a}',completed_phrases=[dict(fr=f,transcription_ru=p)])
def page(s,blocks):
 s['public_page']=dict(slug=s['id'].lower().replace('_','-'),summary=s['description'],seo_title=s['title']+' — французский с нуля',seo_description=s['description'],explanation_blocks=[dict(heading=h,paragraphs=ps,examples=es) for h,ps,es in blocks])

s=start(1,'Узнайте названия трёх гласных букв и отличите название буквы от её чтения в слове. Повторите дневное приветствие.',['bonjour','letter-a','letter-i','letter-o'])
choose(s,'vocabulary',['PRE_A1.GREET.RECOGNIZE'],['bonjour'],['bonjour'],'Вспомните приветствие.',wc('bonjour'),['Добрый вечер!','Здравствуйте!','До свидания!'],1,'Это нейтральное дневное приветствие.','Правильный перевод — «Здравствуйте!», а не вечернее приветствие или прощание.')
intro(s,2,'Сегодня только три названия букв: A — «а», I — «и», O — «о». Название помогает сообщить букву алфавита. В слове буква может читаться иначе. В озвучке La lettre означает «буква»; эту служебную рамку запоминать не нужно.',['letter-a','letter-i','letter-o'],[('A','а'),('I','и'),('O','о'),('La lettre','ла лэтр')],('La lettre A. La lettre I. La lettre O.','ла лэтр а. ла лэтр и. ла лэтр о.'))
choose(s,'vocabulary',[U],['letter-a','letter-i','letter-o'],['letter-a'],'Какую букву называют «а»?',ru('Выберите написание буквы.'),[wc('letter-i'),wc('letter-o'),wc('letter-a')],2,'Название «а» соответствует букве A.','Выберите A: две другие буквы называют «и» и «о».')
listen(s,[U],['letter-a','letter-i','letter-o'],['letter-i'],'Какую букву назвали?','La lettre I.','ла лэтр и.',[wc('letter-o'),wc('letter-i'),wc('letter-a')],1,'Вы услышали название «и»: это I.','После слова «буква» звучит «и», название I.')
pairs(s,[U],[('letter-a','Название «а»'),('letter-i','Название «и»'),('letter-o','Название «о»')],'У трёх букв разные названия: «а», «и», «о».','Сопоставьте A с «а», I с «и», O с «о».')
choose(s,'reading',[U],['letter-o'],['letter-o'],'На карточке показана одна буква. Как её назвать?',fr('O','о'),['«и»','«а»','«о»'],2,'Буква O называется «о».','Для O подходит название «о».')
listen(s,[U],['letter-a','letter-i','letter-o'],['letter-o'],'Для записи продиктовали букву. Выберите её.','La lettre O.','ла лэтр о.',[wc('letter-o'),wc('letter-a'),wc('letter-i')],0,'Названа буква O, её название «о».','Вы услышали «о», поэтому нужна O.')
choose(s,'reading',[U],['bonjour','letter-o'],[],'Как пользоваться знакомыми буквами в слове?',wc('bonjour'),['Всегда произносить каждую букву её алфавитным названием','Слушать слово целиком: сочетания букв могут читаться иначе','Пропускать все гласные при чтении'],1,'Название буквы и её звучание в слове — разные вещи.','Слушайте слово целиком: алфавитные названия не задают чтение каждого сочетания.')
page(s,[('Три названия, а не весь алфавит',['Начните с трёх букв. Название помогает уточнить написание, но не заменяет чтение слова.'],[W[w]['example'] for w in ['letter-a','letter-i','letter-o']]),('От буквы к слову',['В знакомом приветствии несколько букв образуют сочетания. Не произносите слово как цепочку алфавитных названий. Русская транскрипция приблизительна; (н) обозначает носовой гласный без отдельного «н».'],[ex('Bonjour !','Здравствуйте!','бо(н)жу́р')])])

s=start(2,'Найдите звуки «ж» и «с» в знакомых репликах, узнайте немую конечную букву и названия двух согласных букв.',['letter-a','letter-i','letter-o','letter-l','letter-m','bonjour','je-mappelle','ca-va','salut'])
listen(s,[U],['letter-a','letter-i','letter-o'],['letter-a'],'Вспомните букву из прошлого занятия.','La lettre A.','ла лэтр а.',[wc('letter-o'),wc('letter-a'),wc('letter-i')],1,'Вы вспомнили название A — «а».','Звучало «а», то есть A.')
intro(s,2,'В bonjour и je m’appelle буква j передаёт звук, близкий к «ж». В ça va буква ç звучит как «с». Слушайте знакомые реплики целиком, без алфавитного перечисления.',['bonjour','je-mappelle','ca-va'],[('bonjour','бо(н)жу́р'),('je m’appelle','жё мапэ́ль'),('j','жи'),('ça va','са ва́'),('ç','сэ сэдий')],('Bonjour ! Je m’appelle Lila. Ça va ?','бо(н)жу́р! жё мапэль лила́. са ва́?'))
choose(s,'reading',[U],['je-mappelle'],[],'С какого звука начинается готовое выражение?',fr('Je m’appelle Lila.','жё мапэль лила́'),['С «ж»','С «с»','С «м»'],0,'Начало выражения звучит примерно «жё».','Первый звук близок к «ж»; «м» слышится позже.')
listen(s,[U],['bonjour','ca-va','salut'],['ca-va'],'Какую знакомую реплику вы услышали?','Ça va ?','са ва́?',[wc('bonjour'),wc('salut'),wc('ca-va')],2,'Начало «са» соответствует вопросу «Как дела?».','Прозвучал вопрос «Как дела?», начинающийся с «с».')
intro(s,4,'Для будущей записи имени нужны ещё две буквы: L называется «эль», M — «эм». Это названия букв; в самом имени слышатся согласные «л» и «м».',['letter-l','letter-m'],[('L','эль'),('M','эм')],('La lettre L. La lettre M.','ла лэтр эль. ла лэтр эм.'))
pairs(s,[U],[('letter-l','Буква с названием «эль»'),('letter-m','Буква с названием «эм»'),('letter-a','Буква с названием «а»')],'Вы различили две согласные и знакомую гласную.','L называют «эль», M — «эм», A — «а».')
intro(s,5,'В salut конечная t не произносится: слышим «салю». Это наблюдение о знакомом слове, а не правило для всех конечных согласных. В bonjour сочетание ou передаёт звук «у».',['salut','bonjour'],[('salut','салю́'),('t','тэ'),('bonjour','бо(н)жу́р'),('ou','у')],('Salut ! Bonjour !','салю́! бо(н)жу́р!'))
choose(s,'reading',[U],['salut'],[],'Что происходит с конечной согласной в этом приветствии?',wc('salut'),['Произносится отдельное «т»','Конечная согласная не звучит','В конце звучит «м»'],1,'Приветствие заканчивается звучанием «лю», без отдельного «т».','Конечная буква здесь немая; отдельного «т» нет.')
listen(s,[U],['letter-l','letter-m','letter-a'],['letter-m'],'Какую согласную назвали?','La lettre M.','ла лэтр эм.',[wc('letter-l'),wc('letter-m'),wc('letter-a')],1,'Название «эм» соответствует M.','Нужна M: «эм»; L называют «эль».')
choose(s,'reading',[U],['bonjour'],[],'Как читается сочетание двух букв после согласного звука «ж»?',wc('bonjour'),['Как «о-и»','Как «у»','Как «а»'],1,'В этом слове сочетание после «ж» даёт «у».','Здесь слышим «жу»: сочетание даёт один звук «у».')
page(s,[('Согласные в знакомых словах',['В приветствии слышится «ж», а в вопросе о делах — «с». Сопоставляйте запись и целое слово.'],[ex('Bonjour !','Здравствуйте!','бо(н)жу́р'),ex('Ça va ?','Как дела?','са ва́?')]),('Буква может не звучать',['У неформального приветствия конечная согласная немая. Не переносите это наблюдение на каждое французское слово.'],[ex('Salut !','Привет!','салю́')]),('Названия для уточнения записи',['Эти согласные пригодятся, когда нужно назвать короткое имя по буквам.'],[W[w]['example'] for w in ['letter-l','letter-m']])])

s=start(3,'Узнайте носовой гласный в знакомом приветствии, ответы «да» и «нет» и подпись поля фамилии в анкете.',['bonjour','merci','nom-family-name','non','oui'])
listen(s,['PRE_A1.GREET.RECOGNIZE'],['bonjour'],['bonjour'],'Вспомните знакомое приветствие.','Bonjour !','бо(н)жу́р!',['Спасибо!','До свидания!','Здравствуйте!'],2,'Это дневное приветствие.','Прозвучало «Здравствуйте!», а не благодарность или прощание.')
intro(s,2,'В начале bonjour гласный носовой: часть воздуха проходит через нос, отдельного «н» нет. Обозначаем это приблизительно как (н). Новые короткие ответы: non — «нет», oui — «да». В non тоже один носовой гласный без отдельного «н» после него.',['non','oui','bonjour'],[('bonjour','бо(н)жу́р'),('non','но́(н)'),('oui','уи́')],('Non, merci. Oui, merci.','но(н), мэрси́. уи, мэрси́.'))
choose(s,'vocabulary',[A],['non','oui','merci','bonjour'],['non'],'Вам предлагают повторить, но вы уже всё поняли. Выберите вежливый отказ.',ru('Предложение вам не нужно.'),[fr('Oui, merci.','уи, мэрси́'),fr('Non, merci.','но(н), мэрси́'),wc('bonjour')],1,'Отказ выражается словом «нет» с благодарностью.','Выберите «Нет, спасибо»: «да» означало бы согласие.')
listen(s,[A],['non','oui','merci'],['oui'],'Собеседник принимает предложение или отказывается?','Oui, merci.','уи, мэрси́.',['Отказывается','Прощается','Принимает предложение'],2,'Прозвучало «Да, спасибо»: это согласие.','Ответ «Да, спасибо» означает согласие, а не отказ.')
intro(s,4,'В анкете с отдельными полями имени и фамилии nom обозначает фамилию; словарная форма le nom. Пример: Nom : Lamy. — «Фамилия: Лами». Слова nom и non звучат одинаково. Их смысл различаем по ситуации и написанию, а не по звуку.',['nom-family-name'],[('nom','но́(н)'),('le nom','лё но́(н)'),('Nom : Lamy.','но(н) : лами́'),('non','но́(н)')],('Le nom. Non.','лё но́(н). но́(н).'))
choose(s,'vocabulary',[A],['nom-family-name'],['nom-family-name'],'В анкете имя и фамилия записываются отдельно. Что означает подпись поля?',fr('Nom','но́(н)'),['Фамилия','Личное имя','Ответ «нет»'],0,'В такой анкете это поле фамилии.','Здесь это подпись поля фамилии, а не отрицательный ответ.')
choose(s,'reading',[A],['nom-family-name','non','oui'],['non'],'Человек отказывается от помощи. Какая запись соответствует его короткому ответу «нет»?',ru('Используйте смысл ситуации, а не только звучание.'),[wc('nom-family-name'),wc('oui'),wc('non')],2,'В ответе об отказе пишется отрицательное слово.','Для отказа нужно «нет». Подпись поля фамилии — другое слово, хотя звучание совпадает.')
listen(s,[A],['non','oui','merci'],['non'],'Какой короткий ответ звучит перед благодарностью?','Non, merci.','но(н), мэрси́.',['«Да» без носового гласного','«Нет» с носовым гласным','Только благодарность, без ответа'],1,'В отказе звучит носовой гласный; отдельного «н» в конце нет.','Перед благодарностью звучит «нет»: носовой гласный после начального согласного «н».')
fill(s,[A],['non','oui','merci'],['oui'],'Вы согласны на помощь. Дополните ответ «Да, спасибо».','','',', merci.','мэрси́',[wc('non'),wc('oui')],1,'oui, merci.','уи, мэрси́','Согласие выражается словом «да».','Выберите «да»; отрицание изменит смысл на отказ.')
page(s,[('Что значит носовой гласный',['Воздух проходит и через нос. В нашей подсказке (н) — условное обозначение; отдельную согласную «н» после гласного добавлять не нужно.'],[ex('Bonjour !','Здравствуйте!','бо(н)жу́р'),W['non']['example']]),('Смысл различает одинаковое звучание',['В анкете с раздельными полями подпись обозначает фамилию. Отрицательный ответ звучит так же, но пишется иначе. По одному звучанию выбирать между этими словами нельзя.'],[W['nom-family-name']['example'],W['non']['example']]),('Согласие и отказ',['Оба коротких ответа можно дополнить знакомой благодарностью.'],[W['oui']['example'],W['non']['example']])])

s=start(4,'Попросите назвать имя по буквам и выберите запись короткого имени из пяти уже знакомых букв.',['epelez','repetez','plus-lentement','sil-vous-plait','je-mappelle','letter-a','letter-i','letter-o','letter-l','letter-m','nom-family-name'])
choose(s,'vocabulary',[A],['nom-family-name'],['nom-family-name'],'Повторение: в анкете отдельно указаны имя и фамилия. Что записывают в это поле?',fr('Nom','но́(н)'),['Личное имя','Фамилию','Ответ на предложение'],1,'Это поле фамилии.','В такой анкете подпись означает фамилию.')
intro(s,2,'Чтобы уточнить написание, скажите Épelez, s’il vous plaît. — «Назовите по буквам, пожалуйста». Знакомая просьба повторить означает повтор всей реплики; новая просьба нужна именно для букв.',['epelez'],[('Épelez, s’il vous plaît.','эплэ, силь ву плэ́')],('Épelez, s’il vous plaît.','эплэ, силь ву плэ́'))
choose(s,'vocabulary',[A],['epelez','repetez','plus-lentement'],['epelez'],'Вы слышали имя, но не знаете его написания. Что попросить?',ru('Нужно услышать отдельные буквы.'),[wc('repetez'),wc('epelez'),wc('plus-lentement')],1,'Просьба назвать по буквам помогает уточнить запись.','Нужна просьба назвать по буквам; замедление и повтор целиком не уточняют каждую букву.')
listen(s,[A],['epelez','repetez','plus-lentement'],['epelez'],'Что нужно сделать после этой просьбы?','Épelez, s’il vous plaît.','эплэ, силь ву плэ́.',['Повторить имя только целиком','Сказать имя тише','Назвать буквы имени по очереди'],2,'Собеседник просит перечислить буквы.','Просьба относится к написанию: назовите каждую букву имени.')
intro(s,4,'Пример имени: Lila — «Лила». Буквы по порядку: L, I, L, A; их названия — «эль, и, эль, а». Другое имя Mila — «Мила»: M, I, L, A. Это примеры, отдельные карточки имён запоминать не нужно. В аудио перед каждой буквой звучит знакомое пояснение «буква».',['letter-l','letter-m','letter-i'],[('Lila','лила́'),('Mila','мила́'),('L','эль'),('I','и'),('A','а'),('M','эм')],('Lila. La lettre L, la lettre I, la lettre L, la lettre A.','лила́. ла лэтр эль, ла лэтр и, ла лэтр эль, ла лэтр а.'))
listen(s,[A],['letter-l','letter-m','letter-i','letter-a','letter-o'],['letter-m'],'Какое имя записать по названным буквам?','La lettre M, la lettre I, la lettre L, la lettre A.','ла лэтр эм, ла лэтр и, ла лэтр эль, ла лэтр а.',[fr('Lila','лила́'),fr('Mila','мила́'),fr('Lola','лола́')],1,'Названы M, I, L, A — получается имя Мила.','Порядок начинается с M, затем I, L, A: запишите имя Мила.')
order(s,[A],['epelez','sil-vous-plait'],['epelez'],'Соберите просьбу «Назовите по буквам, пожалуйста», начав с самой просьбы.',[('Épelez,','эплэ'),('s’il vous plaît.','силь ву плэ́')],'Épelez, s’il vous plaît.','эплэ, силь ву плэ́.','Сначала действие, затем знакомая вежливая формула.','Начните с просьбы назвать по буквам и завершите вежливой формулой.')
choose(s,'reading',[A],['letter-l','letter-m','letter-i','letter-a'],['letter-l','letter-i','letter-a'],'Какой порядок букв соответствует имени на карточке?',fr('Lila','лила́'),[fr('L, I, L, A','эль, и, эль, а'),fr('M, I, L, A','эм, и, эль, а'),fr('L, A, L, I','эль, а, эль, и')],0,'Для имени Лила нужны L, I, L, A в этом порядке.','Выберите L, I, L, A: изменение первой буквы или порядка меняет запись.')
choose(s,'vocabulary',['PRE_A1.CLARIFY.REQUEST'],['repetez','epelez','plus-lentement'],['repetez'],'Теперь вы хотите услышать имя ещё раз целиком, без перечисления букв. Что сказать?',ru('Выберите знакомую просьбу повторить.'),[wc('epelez'),wc('plus-lentement'),wc('repetez')],2,'Для повтора целиком подходит просьба повторить.','Выберите «Повторите, пожалуйста»: в этой ситуации перечислять буквы не требуется.')
page(s,[('Как уточнить написание',['Если вы услышали имя, но не знаете его записи, попросите перечислить буквы. Повтор целого имени решает другую задачу.'],[W['epelez']['example'],W['repetez']['example']]),('Названия складываются в имя',['В примерах используются только уже изученные буквы. Имя с начальной «эм» и имя с начальной «эль» имеют разное написание.'],[ex('L, I, L, A','Буквы имени Лила.','эль, и, эль, а'),ex('M, I, L, A','Буквы имени Мила.','эм, и, эль, а')]),('Без оценки собственного произношения',['Можно добровольно повторить пример вслух. Сайт проверяет выбранную запись и порядок карточек.'],[ex('Je m’appelle Mila.','Меня зовут Мила.','жё мапэль мила́')])])

s=start(5,'Повторите знакомые буквы и звучания, выберите имя по буквам и используйте просьбу уточнить написание. Нового материала нет.',['letter-a','letter-i','letter-o','letter-l','letter-m','bonjour','salut','non','oui','nom-family-name','merci','epelez','repetez','plus-lentement','sil-vous-plait'])
intro(s,1,'Сегодня только повторение. Слушайте целую реплику, затем выбирайте смысл или запись. Для одинаково звучащих слов используйте ситуацию. Названия букв пригодятся для записи имени.',[])
listen(s,[U],['letter-l','letter-m','letter-i'],['letter-l'],'Какую букву назвали?','La lettre L.','ла лэтр эль.',[wc('letter-m'),wc('letter-i'),wc('letter-l')],2,'Название «эль» соответствует L.','Выберите L: «эль», а не M: «эм».')
pairs(s,[U],[('letter-a','Название «а»'),('letter-i','Название «и»'),('letter-o','Название «о»')],'Вы восстановили три знакомых названия гласных букв.','A называют «а», I — «и», O — «о».')
choose(s,'reading',[U],['salut'],[],'Какая запись звучания соответствует этому знакомому приветствию?',wc('salut'),['«салют» с конечным «т»','«салю» без конечного «т»','«салу» со звуком «у»'],1,'Конечная согласная не произносится, а гласный приблизительно передан как «ю».','Подходит «салю»: конечного «т» нет; русское «у» не передаёт этот гласный точно.')
listen(s,[A],['non','oui','merci'],['non'],'Как человек ответил на предложение помощи?','Non, merci.','но(н), мэрси́.',['Согласился','Отказался с благодарностью','Попросил назвать по буквам'],1,'Вы услышали вежливый отказ с носовым гласным.','Это «Нет, спасибо»: отказ, а не согласие или просьба.')
choose(s,'vocabulary',[A],['nom-family-name','non','bonjour'],['nom-family-name'],'В анкете с раздельными полями имя уже записано. Какое поле нужно для фамилии?',ru('Выберите подходящую подпись.'),[wc('non'),wc('nom-family-name'),wc('bonjour')],1,'Подпись поля фамилии — существительное, а не ответ «нет».','Выберите поле фамилии. Одинаковое звучание с отрицанием не делает слова взаимозаменяемыми.')
listen(s,[A],['letter-l','letter-o','letter-a','letter-i','letter-m'],['letter-o'],'Вы уже знаете все эти буквы. Как записать имя?','La lettre L, la lettre O, la lettre L, la lettre A.','ла лэтр эль, ла лэтр о, ла лэтр эль, ла лэтр а.',[fr('Lila','лила́'),fr('Mila','мила́'),fr('Lola','лола́')],2,'Последовательность L, O, L, A даёт имя Лола.','После первой L звучит O, затем L и A: получается имя Лола.')
choose(s,'vocabulary',[A],['epelez','repetez','plus-lentement'],['epelez'],'На встрече вы хотите записать имя точно. Выберите просьбу назвать его по буквам.',ru('Примените изученное выражение в новой ситуации.'),[wc('plus-lentement'),wc('repetez'),wc('epelez')],2,'Для точной записи вы попросили перечислить буквы.','Нужна просьба назвать по буквам. Замедление и повтор целиком не гарантируют уточнения записи.')
order(s,[A],['epelez','sil-vous-plait'],['epelez'],'Соберите ту же просьбу, начав с действия.',[('Épelez,','эплэ'),('s’il vous plaît.','силь ву плэ́')],'Épelez, s’il vous plaît.','эплэ, силь ву плэ́.','Просьба назвать по буквам завершена вежливой формулой.','Поставьте просьбу о буквах перед вежливой формулой.')
page(s,[('Буквы и знакомые слова',['Повторите три гласные и две согласные по названиям. Затем вернитесь к чтению целого слова: название буквы и её звук могут различаться.'],[W[w]['example'] for w in ['letter-l','letter-m','letter-o']]),('Одинаковое звучание требует контекста',['Поле фамилии и отрицание различаются записью и функцией. Носовой гласный сам по себе не позволяет выбрать одно из этих слов.'],[W['nom-family-name']['example'],W['non']['example']]),('Применение на встрече',['Просьба назвать по буквам помогает уточнить написание имени. Используются только пять знакомых букв.'],[W['epelez']['example'],ex('L, O, L, A','Буквы имени Лола.','эль, о, эль, а')])])

# Add notes for French fragments within Russian containers, including copied reviews.
NOTES={'A':'а','I':'и','O':'о','L':'эль','M':'эм','L, I, L, A':'эль, и, эль, а','M, I, L, A':'эм, и, эль, а','L, O, L, A':'эль, о, эль, а'}
def annotate(v):
 if isinstance(v,list):
  for x in v: annotate(x)
 if not isinstance(v,dict): return
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-z])'+re.escape(f)+r'(?![A-Za-z])',text) and not any(n['fr']==f for n in ns): ns.append(dict(fr=f,transcription_ru=p))
  if ns: v['pronunciation_notes']=ns
 for x in list(v.values()): annotate(x)
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Связь букв и звуков на знакомых репликах: названия пяти букв, носовые гласные и уточнение написания короткого имени.',learning_outcomes=['Узнавать названия пяти изученных букв и их запись.','Связывать частые буквы и сочетания со звучанием в знакомых словах.','Различать согласие, отказ и подпись поля фамилии по контексту.','Просить назвать имя по буквам и выбирать запись по знакомым названиям.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u02-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True); target=OUT/'PRE_A1-U02.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv: raise SystemExit('Existing package: inspect import/version history before rewriting.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('non','Повторить носовой гласный и отказ в ситуациях с выбором количества.'),('oui','Повторить согласие при подтверждении количества.'),('repetez','Использовать при непонимании услышанного числа.'),('epelez','Сохранить различие между просьбой повторить число и просьбой назвать буквы имени.'),('bonjour','Вернуть приветствие в короткую ситуацию с числами.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Подтвердить наличие PRE_A1-U01-L05 в базе перед импортом: пока проверяется файловая зависимость.','Прослушать выбранным французским голосом буквы в служебной рамке и последовательности букв имён, особенно L/M.','Выполнить предпросмотр, пробное прохождение, языковую редактуру и замер времени; 6 минут — авторский ориентир.'],progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U03'),scope_notes=['U01 — исходный подготовленный пакет для повторения; публикация в базе и принятие U02 пользователем не подтверждены.','La lettre — объяснённая служебная рамка озвучки; отдельной учебной целью не является.','В анкете с раздельными полями nom дано в значении фамилии. Омонимы nom/non не различаются только на слух.','В L01 и L02 цель APPLY включена по плану, содержательные проверки начинаются в L03.','Имя Лола — необязательная деталь из изученных букв, новых правил или слов в L05 нет.','Основной LEXEME_REGISTRY.json не изменён; новые ключи предложены в delta до принятия пакета.'])
(OUT/'PRE_A1-U02.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))

