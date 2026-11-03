"""Author PRE_A1-U06 only; no imported version is overwritten by default."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][5]
W={}
for source in [OUT/'PRE_A1-U05.v1.json',OUT/'PRE_A1-U04.v1.json',OUT/'PRE_A1-U03.v1.json',OUT/'PRE_A1-U02.v1.json',KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='PRE_A1.U06.UNDERSTAND';A='PRE_A1.U06.APPLY'
OBJECTIVES={o['id']:o for u in level['units'] for o in u['objectives']}

def ru(t): return dict(text=t,text_lang='ru')

def fr(t,p): return dict(text=t,text_lang='fr',transcription_ru=p)

def ex(f,r,p): return dict(fr=f,ru=r,transcription_ru=p)

def sound(f,p): return dict(text=f,transcription_ru=p,lang='fr-FR',asset_id=None)

def wc(w): return fr(W[w]['fr'],W[w]['transcription_ru'])

def fb(g,b): return dict(correct=g,incorrect=b)

def opts(v): return [dict(id=f'c{i}',**(ru(t) if isinstance(t,str) else t)) for i,t in enumerate(v)]

def rev(i,p,cs,a,g,b): return dict(id=i,type='single_choice',instruction='Выберите один ответ.',prompt=p,choices=opts(cs),correct_choice_id=f'c{a}',feedback=fb(g,b))

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

 return t

def page(s,blocks):
 s['public_page']=dict(slug=s['id'].lower().replace('_','-'),summary=s['description'],seo_title=s['title']+' — французский с нуля',seo_description=s['description'],explanation_blocks=[dict(heading=h,paragraphs=ps,examples=es) for h,ps,es in blocks])

def grammar(t):
 t['skill']='grammar'
 return t

def addword(i,key,f,r,p,e,n,reviews):
 W[i]=dict(id=i,lexeme_key=key,fr=f,ru=[r],transcription_ru=p,example=e,review_items=reviews);FIRST[i]=n
NOUNS=[
 ('mere','mere:mother','la mère','мать','ла мэ́р',1),('pere','pere:father','le père','отец','лё пэ́р',1),('parents','parents:parents','les parents','родители','лэ пара́(н)',1),
 ('frere','frere:brother','le frère','брат','лё фрэ́р',2),('soeur','soeur:sister','la sœur','сестра','ла сё́р',2),
 ('fils','fils:son','le fils','сын','лё фи́с',3),('fille','fille:daughter','la fille','дочь','ла фи́й',3),('enfant','enfant:child','un enfant','ребёнок','э(н) на(н)фа́(н)',3),
 ('famille','famille:family','la famille','семья','ла фами́й',4),('oncle','oncle:uncle','un oncle','дядя','э(н) но́(н)кль',4),('tante','tante:aunt','une tante','тётя','юн та́(н)т',4)]
for index,(i,key,f,r,p,n) in enumerate(NOUNS):
 group=[w for w in NOUNS if w[5]==n]
 if len(group)<3:group.append(NOUNS[1])
 others=[v for v in group if v[0]!=i][:2]
 meanings=[v[3] for v in others];meanings.insert(index%3,r)
 application=[fr(v[2],v[4]) for v in others];application.insert((index+1)%3,fr(f,p))
 situations={'mere':'Вы рассказываете о матери. Какое название выбрать?','pere':'Вы представляете своего отца. Какое слово подходит?','parents':'Вы говорите о родителях вместе, а не об одном человеке. Какое название подходит?','frere':'Вы хотите назвать своего брата, не отца и не сестру. Выберите название.','soeur':'Вы рассказываете о сестре. Какое название подходит?','fils':'Родитель представляет своего сына. Какое название соответствует этой роли?','fille':'Родитель представляет свою дочь. В этом семейном контексте какое название подходит?','enfant':'В списке указан один ребёнок без уточнения пола и роли сына или дочери. Какое общее название выбрать?','famille':'Вы говорите о семье в целом, а не об одном дяде или тёте. Какое название выбрать?','oncle':'Вы называете своего дядю. Какое французское название подходит?','tante':'Вы называете свою тётю. Какое французское название подходит?'}
 addword(i,'fr:'+key,f,r,p,ex(f.capitalize()+'.',r.capitalize()+'.',p),n,[rev(i+'-meaning',fr(f,p),meanings,index%3,f'Здесь значение — «{r}».',f'Правильный смысл — «{r}»; другие варианты называют других членов семьи или другую группу.'),rev(i+'-use',ru(situations[i]),application,(index+1)%3,'Выбранное слово соответствует указанной семейной роли.',f'В этой ситуации нужен смысл «{r}»; не подменяйте его другим членом семьи.')])
addword('mon-possessive','fr:mon:first-person-masculine-possessive','mon','мой (перед изученным словом мужского рода)','мо́(н)',ex('Mon père.','Мой отец.','мо(н) пэ́р'),1,[rev('mon-meaning',fr('Mon père.','мо(н) пэ́р'),['Мой отец.','Твой отец.','Моя мать.'],0,'Говорящий называет собственного отца.','Это «мой отец», а не чужой отец или мать.'),rev('mon-use',ru('Нужно сказать «мой отец». Выберите слово перед изученным названием отца.'),[fr('ma','ма'),fr('mon','мо́(н)'),wc('des-article')],1,'Перед этим словом мужского рода подходит изученная форма «мой».','Для отца нужна форма мужского рода; форма женского рода и артикль нескольких предметов не подходят.')])
addword('ma-possessive','fr:ma:first-person-feminine-possessive','ma','моя (перед изученным словом женского рода)','ма',ex('Ma mère.','Моя мать.','ма мэ́р'),1,[rev('ma-meaning',fr('Ma mère.','ма мэ́р'),['Моя мать.','Мой отец.','Твоя мать.'],0,'Говорящий называет собственную мать.','Это «моя мать»; род и значение должны соответствовать указанной роли.'),rev('ma-use',ru('Нужно сказать «моя мать». Выберите слово перед изученным названием матери.'),[wc('mon-possessive'),wc('des-article'),fr('ma','ма')],2,'Перед изученным словом женского рода подходит форма «моя».','Для матери в этом сочетании нужна форма женского рода, а не мужского или множественного числа.')])
addword('mes-possessive','fr:mes:first-person-plural-possessive','mes','мои (перед несколькими родственниками)','мэ',ex('Mes sœurs.','Мои сёстры.','мэ сё́р'),2,[rev('mes-meaning',fr('Mes sœurs.','мэ сё́р'),['Моя сестра.','Ровно две сестры.','Мои сёстры; точное число не названо.'],2,'Речь о нескольких сёстрах говорящего без точного числа.','Указано множественное число, но не обязательно два человека.'),rev('mes-use',ru('Нужно назвать своих братьев во множественном числе. Какое начало выбрать?'),[wc('mon-possessive'),fr('mes','мэ'),wc('ma-possessive')],1,'Перед несколькими братьями используется форма «мои».','Нужна форма множественного числа; две другие формы относятся к одному человеку.')])
addword('children-question','fr:vous-avez-des-enfants:children-question','vous avez des enfants ?','У вас есть дети?','ву завэ дэ за(н)фа́(н)?',ex('Vous avez des enfants ?','У вас есть дети?','ву завэ дэ за(н)фа́(н)?'),3,[rev('children-question-meaning',fr('Vous avez des enfants ?','ву завэ дэ за(н)фа́(н)?'),['Сколько вам лет?','У вас есть дети?','Кто это?'],1,'Вопрос относится к тому, есть ли у человека дети.','Спрашивают о детях, а не о возрасте или личности показанного человека.'),rev('children-question-use',ru('В учебном диалоге нужно спросить, есть ли у собеседника дети. Какой вопрос подходит?'),[wc('age-question'),wc('formal-name-question'),fr('Vous avez des enfants ?','ву завэ дэ за(н)фа́(н)?')],2,'Выбран вопрос о детях.','Нужен вопрос о наличии детей; имя и возраст спрашивают иначе.')])
addword('jai-des-enfants','fr:jai-des-enfants:have-children','j’ai des enfants','У меня есть дети','жэ дэ за(н)фа́(н)',ex('J’ai des enfants.','У меня есть дети.','жэ дэ за(н)фа́(н)'),3,[rev('jai-des-enfants-meaning',fr('J’ai des enfants.','жэ дэ за(н)фа́(н)'),['У меня есть дети; точное число не названо.','У меня нет детей.','Это мои родители.'],0,'Говорящий сообщает о детях без точного количества.','Дети есть; отрицания и указания на родителей в реплике нет.'),rev('jai-des-enfants-use',ru('В учебной ситуации у персонажа есть дети, но их число не уточняется. Какой ответ выбрать?'),[fr('J’ai des enfants.','жэ дэ за(н)фа́(н)'),fr('J’ai dix-neuf ans.','жэ дизнёва́(н)'),wc('repetez')],0,'Реплика сообщает именно о детях, без точного числа.','Нужен ответ о детях; возраст и просьба повторить не отвечают на этот вопрос.')])
addword('qui-est-ce','fr:qui-est-ce:identity-question','qui est-ce ?','Кто это?','ки э́с?',ex('Qui est-ce ?','Кто это?','ки э́с?'),4,[rev('qui-est-ce-meaning',fr('Qui est-ce ?','ки э́с?'),['Кто это?','Сколько вам лет?','У вас есть дети?'],0,'Спрашивают, кто этот человек.','Правильный смысл — «Кто это?», а не возраст или наличие детей.'),rev('qui-est-ce-use',ru('Собеседник представляет родственника. Вы хотите уточнить, кто это. Какой вопрос выбрать?'),[wc('children-question'),fr('Qui est-ce ?','ки э́с?'),wc('age-question')],1,'Вопрос уточняет, кто этот человек.','Нужен вопрос «Кто это?»; остальные спрашивают о детях и возрасте.')])

s=start(1,'Узнавайте названия родителей и представляйте мать и отца с помощью знакомого образца «это». Выбирайте изученные формы «мой» и «моя».',['mere','pere','parents','mon-possessive','ma-possessive','cest-identify','ce-sont-identify','jhabite-a','je-suis-de'])
choose(s,'reading',['PRE_A1.U05.APPLY'],['jhabite-a','je-suis-de'],['jhabite-a'],'Перед знакомством с семьёй человек рассказал о себе. Что он сообщил?',fr('J’habite à Lyon.','жабит а льо́(н).'),['Он из Лиона; где живёт, не сказано.','Он живёт в Лионе.','Он назвал своё имя.'],1,'Сообщено место жительства — Лион.','Это проживание в Лионе, а не только происхождение или имя.')
intro(s,2,'Начнём с трёх названий: la mère — мать, le père — отец, les parents — родители. Первые два называют по одному человеку; последнее — родителей вместе. Названия учим с показанным артиклем.',['mere','pere','parents'],[('la mère','ла мэ́р'),('le père','лё пэ́р'),('les parents','лэ пара́(н)')],('La mère. Le père. Les parents.','ла мэ́р. лё пэ́р. лэ пара́(н).'))
pairs(s,[U],[('mere','Мать'),('pere','Отец'),('parents','Родители')],'Вы различили названия матери, отца и родителей вместе.','Не смешивайте отдельного человека и родителей вместе: у каждого названия своё значение.')
listen(s,[U],['mere','pere','parents'],['pere'],'Кого назвали?','Le père.','лё пэ́р.',['Мать','Родителей вместе','Отца'],2,'Назван отец.','Выберите отца: мать и родители вместе — другие значения.')
intro(s,4,'О своих родителях говорим mon père — «мой отец», ma mère — «моя мать». В этих сочетаниях mon стоит перед словом мужского рода, ma — перед словом женского рода. Выбор зависит от названия родственника, а не от пола говорящего. Эти формы заменяют показанный артикль: не ставим их одновременно с ним.',['mon-possessive','ma-possessive'],[('mon père','мо(н) пэ́р'),('ma mère','ма мэ́р'),('mon','мо́(н)'),('ma','ма')],('Mon père. Ma mère.','мо(н) пэ́р. ма мэ́р.'))
choose(s,'vocabulary',[U],['mon-possessive','ma-possessive','mere','pere','cest-identify'],['mere','ma-possessive'],'Говорящий представляет свою мать. Какую реплику выбрать?',ru('Нужны верные родство и значение «моя».'),[fr('C’est mon père.','сэ мо(н) пэ́р.'),fr('C’est ma mère.','сэ ма мэ́р.'),fr('C’est la mère.','сэ ла мэ́р.')],1,'Реплика называет именно мать говорящего.','Нужно «Это моя мать»; отец — другой родственник, а название матери без «моя» не сообщает нужную связь.')
grammar(fill(s,[A],['ma-possessive','mon-possessive','mere','cest-identify'],['ma-possessive'],'Дополните сообщение «Это моя мать».','C’est ','сэ',' mère.','мэ́р',[wc('mon-possessive'),wc('ma-possessive')],1,'C’est ma mère.','сэ ма мэ́р.','Перед изученным словом женского рода подходит форма «моя».','Для матери здесь нужна форма женского рода, независимо от того, кто говорит.'))
choose(s,'reading',[U],['mon-possessive','ma-possessive','mere','pere','cest-identify'],['mere','pere'],'Каких родственников представили в двух репликах?',fr('C’est ma mère. C’est mon père.','сэ ма мэ́р. сэ мо(н) пэ́р.'),['Дважды мать','Мать и отца','Дважды отца'],1,'Одна реплика представляет мать, другая — отца.','Названы два разных родственника: мать и отец.')
grammar(fill(s,[A],['cest-identify','mon-possessive','ma-possessive','pere'],['mon-possessive'],'Дополните сообщение «Это мой отец». Выберите форму перед изученным названием отца.','C’est ','сэ',' père.','пэ́р',[wc('ma-possessive'),wc('mon-possessive')],1,'C’est mon père.','сэ мо(н) пэ́р.','Перед изученным словом мужского рода подходит форма «мой».','Нужна форма мужского рода перед названием отца; пол говорящего не меняет этот выбор.'))
page(s,[('Названия родителей',['Название матери, название отца и слово для родителей вместе различаются. Сначала запоминаем короткие сочетания с артиклями.'],[W[w]['example'] for w in ['mere','pere','parents']]),('Как сказать о своих родителях',['В изученных сочетаниях выбираем форму по французскому роду названия родственника. Пол самого говорящего этот выбор не меняет. Форма со значением «мой» или «моя» заменяет артикль.'],[ex('Mon père.','Мой отец.','мо(н) пэ́р'),ex('Ma mère.','Моя мать.','ма мэ́р')]),('Представляем человека',['Знакомый образец указания теперь используется с названием родственника. Для этого не нужна новая таблица форм глагола.'],[ex('C’est ma mère.','Это моя мать.','сэ ма мэ́р'),ex('C’est mon père.','Это мой отец.','сэ мо(н) пэ́р')])])

s=start(2,'Узнавайте брата и сестру, называйте своих братьев и сестёр. Различайте одного родственника и несколько без точного количества.',['mere','pere','parents','frere','soeur','mon-possessive','ma-possessive','mes-possessive','cest-identify','ce-sont-identify'])
choose(s,'reading',[U],['cest-identify','ma-possessive','mere','pere','parents'],['mere'],'Кого представили в знакомой реплике?',fr('C’est ma mère.','сэ ма мэ́р.'),['Родителей вместе','Отца','Мать'],2,'Представлена мать говорящего.','Это мать; отец и родители вместе называются иначе.')
intro(s,2,'Ещё два близких родственника: le frère — брат, la sœur — сестра. С уже знакомыми формами получаются mon frère — «мой брат», ma sœur — «моя сестра».',['frere','soeur'],[('le frère','лё фрэ́р'),('la sœur','ла сё́р'),('mon frère','мо(н) фрэ́р'),('ma sœur','ма сё́р')],('Mon frère. Ma sœur.','мо(н) фрэ́р. ма сё́р.'))
pairs(s,[U],[('frere','Брат'),('soeur','Сестра'),('pere','Отец')],'Вы отличили брата и сестру от знакомого отца.','Брат, сестра и отец — разные семейные роли; соедините каждую со своим названием.')
listen(s,[U],['cest-identify','mon-possessive','frere','pere','soeur'],['frere'],'Кого представил говорящий?','C’est mon frère.','сэ мо(н) фрэ́р.',['Отца','Брата','Сестру'],1,'Представлен брат говорящего.','Нужно выбрать брата, а не отца или сестру.')
intro(s,4,'Для нескольких своих родственников используем mes — «мои»: mes frères — «мои братья», mes sœurs — «мои сёстры». Эта форма подходит к обоим изученным названиям во множественном числе. Конечная буква s в этих названиях не звучит; точное число людей не сообщается.',['mes-possessive'],[('mes','мэ'),('mes frères','мэ фрэ́р'),('mes sœurs','мэ сё́р'),('s','эс')],('Mes frères. Mes sœurs.','мэ фрэ́р. мэ сё́р.'))
choose(s,'vocabulary',[U],['frere','soeur','mon-possessive','ma-possessive','mes-possessive'],['mes-possessive'],'Вы говорите о нескольких своих сёстрах, не называя число. Какое сочетание выбрать?',ru('Нужно множественное число.'),[fr('Ma sœur','ма сё́р'),fr('Mon frère','мо(н) фрэ́р'),fr('Mes sœurs','мэ сё́р')],2,'Выбраны несколько сестёр говорящего без точного числа.','Нужна форма «мои сёстры»; одна сестра и брат не соответствуют ситуации.')
grammar(fill(s,[A],['mon-possessive','ma-possessive','mes-possessive','frere'],['mes-possessive'],'Дополните сочетание «мои братья».','','',' frères.','фрэ́р',[wc('ma-possessive'),wc('mes-possessive'),wc('mon-possessive')],1,'mes frères.','мэ фрэ́р.','Для нескольких своих братьев подходит форма «мои».','Нужна форма множественного числа; единственное число здесь не подходит.'))
choose(s,'reading',[U],['ce-sont-identify','mes-possessive','parents','mere','pere'],['parents'],'Что сообщает реплика?',fr('Ce sont mes parents.','сё со(н) мэ пара́(н).'),['Это мой отец.','Это моя мать.','Это мои родители.'],2,'Говорящий представляет своих родителей.','Реплика относится к родителям вместе, а не к одному из них.')
order(s,[A],['ce-sont-identify','mes-possessive','soeur'],[],'Соберите «Это мои сёстры», начав с образца для нескольких людей.',[('Ce sont','сё со́(н)'),('mes','мэ'),('sœurs.','сё́р')],'Ce sont mes sœurs.','сё со(н) мэ сё́р.','Перед названием сестёр стоят начало множественного числа и форма «мои».','Начните с образца для нескольких людей, затем поставьте «мои» перед названием сестёр.')
page(s,[('Брат и сестра',['Названия братьев и сестёр учим с французским родом. Перед одним братом или одной сестрой используем уже показанные формы.'],[ex('Mon frère.','Мой брат.','мо(н) фрэ́р'),ex('Ma sœur.','Моя сестра.','ма сё́р')]),('Несколько моих родственников',['Одна форма со значением «мои» используется с двумя изученными названиями во множественном числе. Она не сообщает точное количество.'],[ex('Mes frères.','Мои братья.','мэ фрэ́р'),ex('Mes sœurs.','Мои сёстры.','мэ сё́р'),ex('Mes parents.','Мои родители.','мэ пара́(н)')]),('Собираем представление',['Для нескольких людей возвращаем знакомое начало множественного числа. Название родственника сохраняет своё значение в единственном и множественном числе.'],[ex('Ce sont mes sœurs.','Это мои сёстры.','сё со(н) мэ сё́р')])])

s=start(3,'Называйте сына, дочь и ребёнка, спрашивайте о детях и сообщайте их наличие или точное количество. Повторите числа.',['fils','fille','enfant','children-question','jai-des-enfants','mere','pere','frere','soeur','mon-possessive','ma-possessive','mes-possessive','cest-identify','un','deux','trois','oui','age-question','formal-name-question'])
listen(s,['PRE_A1.U03.UNDERSTAND'],['un','deux','trois'],['deux'],'Вспомните маленькие числа. Какое число назвали?','Deux.','дё́.',['1','3','2'],2,'Прозвучало число два.','Нужно 2; один и три называют другое количество.')
intro(s,2,'Три названия в семье: le fils — сын, la fille — дочь, un enfant — ребёнок. Здесь речь именно о семейной роли дочери. Общее слово «ребёнок» не уточняет, сын это или дочь. С родителем можно связать уже знакомыми формами: mon fils — «мой сын», ma fille — «моя дочь».',['fils','fille','enfant'],[('le fils','лё фи́с'),('la fille','ла фи́й'),('un enfant','э(н) на(н)фа́(н)'),('mon fils','мо(н) фи́с'),('ma fille','ма фи́й')],('Le fils. La fille. Un enfant.','лё фи́с. ла фи́й. э(н) на(н)фа́(н).'))
pairs(s,[A],[('fils','Сын'),('fille','Дочь'),('enfant','Ребёнок')],'Вы отличили сына и дочь от общего названия ребёнка.','Общее слово не указывает семейную роль сына или дочери; два других названия её уточняют.')
listen(s,[A],['cest-identify','ma-possessive','fille','fils','soeur'],['fille'],'Кого представляет родитель?','C’est ma fille.','сэ ма фи́й.',['Сестру','Дочь','Сына'],1,'Родитель представляет свою дочь.','Правильный ответ — дочь; сестра и сын называются иначе.')
intro(s,4,'Готовый вопрос: Vous avez des enfants ? — «У вас есть дети?». Ответ о наличии: J’ai des enfants. — «У меня есть дети». Точное число не названо. Знакомое «мои дети» — mes enfants. Для количества вместо des используем знакомое число: J’ai deux enfants. — «У меня двое детей». Число можно заменить другим изученным; слушайте фразу целиком.',['children-question','jai-des-enfants'],[('Vous avez des enfants ?','ву завэ дэ за(н)фа́(н)?'),('J’ai des enfants.','жэ дэ за(н)фа́(н)'),('des','дэ'),('mes enfants','мэ за(н)фа́(н)'),('J’ai deux enfants.','жэ дё за(н)фа́(н)')],('Vous avez des enfants ? Oui, j’ai des enfants. J’ai deux enfants. Mes enfants.','ву завэ дэ за(н)фа́(н)? уи, жэ дэ за(н)фа́(н). жэ дё за(н)фа́(н). мэ за(н)фа́(н).'))
choose(s,'vocabulary',[A],['children-question','age-question','formal-name-question'],['children-question'],'В учебном диалоге нужно спросить, есть ли у собеседника дети. Какой вопрос выбрать?',ru('Нужны сведения о детях, а не имя или возраст.'),[wc('age-question'),wc('children-question'),wc('formal-name-question')],1,'Выбран вопрос о детях.','Нужно спросить о детях; два других вопроса касаются возраста и имени.')
listen(s,[A],['children-question','jai-des-enfants','oui'],['jai-des-enfants'],'Что известно о детях из ответа?','Vous avez des enfants ? Oui, j’ai des enfants.','ву завэ дэ за(н)фа́(н)? уи, жэ дэ за(н)фа́(н).',['Есть дети, точное число не названо','Детей нет','Детей ровно двое'],0,'Ответ подтверждает наличие детей, но не их точное количество.','Дети есть; из этой реплики нельзя узнать, сколько именно.')
choose(s,'reading',[A],['jai-des-enfants','enfant','deux','trois','un'],['deux'],'Сколько детей указано в реплике?',fr('J’ai deux enfants.','жэ дё за(н)фа́(н).'),['Один ребёнок','Трое детей','Двое детей'],2,'Названо точное количество — двое детей.','Нужно число два; это не общее сообщение без количества.')
order(s,[A],['jai-des-enfants','enfant','deux'],[],'Соберите «У меня двое детей», начав с изученного начала о себе.',[('J’ai','жэ'),('deux','дё'),('enfants.','а(н)фа́(н)')],'J’ai deux enfants.','жэ дё за(н)фа́(н).','После начала о себе стоят число и название детей.','Поставьте число между началом ответа и названием детей.')
page(s,[('Роли в семье и общее название',['В этом занятии дочь — именно семейная роль. Общее название ребёнка не сообщает, о сыне или дочери идёт речь. Названия с артиклями сохраняйте целиком при первом повторении.'],[W[w]['example'] for w in ['fils','fille','enfant']]),('Вопрос и короткий ответ',['В учебном диалоге можно спросить о детях и выбрать короткий ответ. Ответ выбирается из готовых вариантов по условию.'],[W['children-question']['example'],W['jai-des-enfants']['example']]),('Количество нужно услышать отдельно',['Общее сообщение о наличии детей не означает определённого числа. Для точного количества подставляем изученное число; в целой фразе слышны связи между словами.'],[ex('J’ai deux enfants.','У меня двое детей.','жэ дё за(н)фа́(н)'),ex('J’ai trois enfants.','У меня трое детей.','жэ труа за(н)фа́(н)')])])

s=start(4,'Назовите семью, дядю и тётю, уточните, кто представлен. Повторите формы «мой», «моя», «мои» и знакомые семейные роли.',['famille','oncle','tante','qui-est-ce','mere','pere','parents','frere','soeur','fils','fille','mon-possessive','ma-possessive','mes-possessive','cest-identify','ce-sont-identify','children-question','age-question'])
choose(s,'reading',[A],['cest-identify','mon-possessive','fils','pere','frere'],['fils'],'Кого назвал родитель в знакомой реплике?',fr('C’est mon fils.','сэ мо(н) фи́с.'),['Сына','Отца','Брата'],0,'Представлен сын говорящего.','Нужен сын; отец и брат — другие роли.')
intro(s,2,'Ещё три названия: la famille — семья, un oncle — дядя, une tante — тётя. С изученными формами: ma famille — «моя семья», mon oncle — «мой дядя», ma tante — «моя тётя». Произношение сочетания с дядей слушаем целиком.',['famille','oncle','tante'],[('la famille','ла фами́й'),('un oncle','э(н) но́(н)кль'),('une tante','юн та́(н)т'),('ma famille','ма фами́й'),('mon oncle','мо(н) но́(н)кль'),('ma tante','ма та́(н)т')],('Ma famille. Mon oncle. Ma tante.','ма фами́й. мо(н) но́(н)кль. ма та́(н)т.'))
pairs(s,[A],[('oncle','Дядя'),('tante','Тётя'),('famille','Семья')],'Вы отличили двух родственников от семьи в целом.','Дядя и тётя — отдельные родственники; семья — общее название.')
listen(s,[A],['cest-identify','ma-possessive','tante','mere','fille'],['tante'],'Кого представил говорящий?','C’est ma tante.','сэ ма та́(н)т.',['Мать','Тётю','Дочь'],1,'Представлена тётя говорящего.','Нужно выбрать тётю; мать и дочь называются иначе.')
intro(s,4,'Чтобы уточнить, кто этот человек, спросите Qui est-ce ? — «Кто это?». Например: Qui est-ce ? C’est mon oncle. — «Кто это? Это мой дядя». Ответ строится из уже знакомого образца и названия родственника.',['qui-est-ce'],[('Qui est-ce ?','ки э́с?'),('C’est mon oncle.','сэ мо(н) но́(н)кль')],('Qui est-ce ? C’est mon oncle.','ки э́с? сэ мо(н) но́(н)кль.'))
choose(s,'vocabulary',[A],['qui-est-ce','children-question','age-question'],['qui-est-ce'],'Собеседник представляет родственника. Вы хотите уточнить, кто это. Какой вопрос выбрать?',ru('Нужна личность представленного человека.'),[wc('children-question'),wc('age-question'),wc('qui-est-ce')],2,'Вопрос уточняет, кто этот человек.','Нужно «Кто это?», а не возраст собеседника или наличие детей.')
grammar(fill(s,[A],['cest-identify','famille','mon-possessive','ma-possessive','mes-possessive'],['ma-possessive'],'Дополните сообщение «Это моя семья».','C’est ','сэ',' famille.','фами́й',[wc('mes-possessive'),wc('ma-possessive'),wc('mon-possessive')],1,'C’est ma famille.','сэ ма фами́й.','Перед изученным названием семьи подходит форма женского рода.','Нужна форма «моя» для этого сочетания; один отец и несколько родственников требуют других показанных форм.'))
choose(s,'reading',[U],['famille','ma-possessive','mon-possessive','mere','pere','soeur','frere','tante'],['mere','soeur'],'Какие родственники перечислены в короткой заметке?',fr('Ma famille : ma mère, mon père, ma sœur.','ма фами́й : ма мэ́р, мо(н) пэ́р, ма сё́р.'),['Мать, отец, брат','Тётя, отец, сестра','Мать, отец, сестра'],2,'Названы мать, отец и сестра.','В списке мать, отец и сестра; брат и тётя здесь не перечислены.')
choose(s,'vocabulary',[U],['ce-sont-identify','mes-possessive','parents','mere','pere'],['parents'],'Какой смысл передаёт эта реплика о семье?',fr('Ce sont mes parents.','сё со(н) мэ пара́(н).'),['Это моя мать.','Это мои родители.','Это мой отец.'],1,'Реплика представляет родителей вместе.','Нужны «мои родители», а не один отец или одна мать.')
page(s,[('Моя семья',['Семья — общее название. Дядя и тётя — отдельные родственники. Выбирайте уже изученную форму перед каждым новым названием.'],[ex('Ma famille.','Моя семья.','ма фами́й'),ex('Mon oncle.','Мой дядя.','мо(н) но́(н)кль'),ex('Ma tante.','Моя тётя.','ма та́(н)т')]),('Как уточнить родство',['Короткий вопрос помогает узнать, кого представляют. Ответ можно собрать из знакомого начала указания и сочетания с названием родственника.'],[W['qui-est-ce']['example'],ex('C’est mon oncle.','Это мой дядя.','сэ мо(н) но́(н)кль')]),('Читаем только указанные сведения',['В короткой заметке называются конкретные члены семьи. Не добавляйте других родственников и не делайте вывод о семье в целом по неполному списку.'],[ex('Ma famille : ma mère, mon père, ma sœur.','Моя семья: моя мать, мой отец, моя сестра.','ма фами́й : ма мэ́р, мо(н) пэ́р, ма сё́р')])])

s=start(5,'Понимайте вопросы о семье и выбирайте ответы по условию. Представляйте родственников и различайте наличие детей и точное количество. Нового материала нет.',['mere','pere','parents','frere','soeur','fils','fille','enfant','famille','oncle','tante','mon-possessive','ma-possessive','mes-possessive','children-question','jai-des-enfants','qui-est-ce','cest-identify','ce-sont-identify','deux','trois','age-question'])
intro(s,1,'Здесь только знакомые слова и образцы. Сначала определите, о каком родственнике или о каком типе сведений спрашивают. Если число не названо, не выбирайте точное количество. В сборке используйте все карточки один раз.',[])
listen(s,[U],['qui-est-ce','cest-identify','mon-possessive','frere','pere','fils'],['frere'],'Кого представили в ответ на вопрос?','Qui est-ce ? C’est mon frère.','ки э́с? сэ мо(н) фрэ́р.',['Сына','Отца','Брата'],2,'В ответе представлен брат.','Правильный родственник — брат; сын и отец названы не были.')
choose(s,'reading',[U],['famille','ma-possessive','mon-possessive','mes-possessive','mere','pere','soeur'],['mes-possessive','soeur'],'Что можно сказать о сёстрах по заметке?',fr('Ma famille : ma mère, mon père, mes sœurs.','ма фами́й : ма мэ́р, мо(н) пэ́р, мэ сё́р.'),['Есть одна сестра','Сестёр несколько, точное число не названо','Сестёр ровно две'],1,'Указаны несколько сестёр без точного числа.','Форма множественного числа не сообщает, что сестёр ровно две.')
choose(s,'vocabulary',[A],['children-question','qui-est-ce','age-question'],['children-question'],'В учебном диалоге нужно узнать, есть ли у человека дети. Что спросить?',ru('Выберите вопрос о детях.'),[wc('qui-est-ce'),wc('children-question'),wc('age-question')],1,'Вопрос относится к наличию детей.','Нужен вопрос о детях, а не о личности представленного человека или возрасте.')
listen(s,[A],['jai-des-enfants','enfant','deux','trois'],['trois'],'Сколько детей назвал говорящий?','J’ai trois enfants.','жэ труа за(н)фа́(н).',['Двое','Количество не названо','Трое'],2,'Названо точное число — трое детей.','Выберите трое: в этой реплике количество указано.')
pairs(s,[A],[('oncle','Дядя'),('tante','Тётя'),('fille','Дочь')],'Вы различили знакомые семейные роли.','Дядя, тётя и дочь — разные родственники; каждому соответствует своё название.')
grammar(fill(s,[A],['ce-sont-identify','enfant','mon-possessive','ma-possessive','mes-possessive'],['mes-possessive'],'Дополните сообщение «Это мои дети».','Ce sont ','сё со́(н)',' enfants.','а(н)фа́(н)',[wc('ma-possessive'),wc('mon-possessive'),wc('mes-possessive')],2,'Ce sont mes enfants.','сё со(н) мэ за(н)фа́(н).','Для нескольких своих детей подходит знакомая форма «мои».','Нужна форма множественного числа; «мой» и «моя» в этих образцах относятся к одному человеку.'))
order(s,[A],['cest-identify','mon-possessive','fils'],[],'Представьте своего сына: «Это мой сын». Начните с образца указания.',[('C’est','сэ'),('mon','мо́(н)'),('fils.','фи́с')],'C’est mon fils.','сэ мо(н) фи́с.','После указания стоит сочетание «мой сын».','Поставьте начало указания первым, затем «мой» перед названием сына.')
choose(s,'reading',[A],['qui-est-ce','cest-identify','mon-possessive','oncle','pere'],['oncle'],'Кого представили в коротком обмене репликами?',fr('Qui est-ce ? C’est mon oncle.','ки э́с? сэ мо(н) но́(н)кль.'),['Дядю','Отца','Возраст говорящего'],0,'В ответе представлен дядя.','Вопрос «Кто это?» получил ответ о дяде; отец и возраст не названы.')
page(s,[('Различаем два вопроса',['Один знакомый вопрос уточняет, кто представлен; другой — есть ли у собеседника дети. Ответ выбирайте по тому, какие сведения запрошены.'],[W['qui-est-ce']['example'],W['children-question']['example']]),('Один человек и несколько',['Перед названиями родственников используйте показанные формы для одного или нескольких людей. Множественное число само по себе не означает ровно два.'],[ex('C’est mon fils.','Это мой сын.','сэ мо(н) фи́с'),ex('Ce sont mes enfants.','Это мои дети.','сё со(н) мэ за(н)фа́(н)')]),('Точное количество в ответе',['Сообщение о наличии детей отличается от реплики с числом. Все числа этого занятия уже встречались раньше.'],[W['jai-des-enfants']['example'],ex('J’ai trois enfants.','У меня трое детей.','жэ труа за(н)фа́(н)')])])

for s in S:
 required=list(dict.fromkeys(w for t in s['tasks'] for w in t['vocabulary_ids']))
 required+=list(dict.fromkeys(w for i in s['introductions'] for w in i['vocabulary_ids']))
 present={w['id'] for w in s['vocabulary']}
 for w in required:
  if w not in present:s['vocabulary'].append(copy.deepcopy(W[w]));present.add(w)
 s['skills']=list(dict.fromkeys(t['skill'] for t in s['tasks']))
NOTES={w['fr']:w['transcription_ru'] for w in W.values()}
NOTES.update({'s':'эс','mon':'мо́(н)','ma':'ма','mes':'мэ','Qui est-ce ?':'ки э́с?'})
def annotate(v):
 if isinstance(v,list):
  for x in v:annotate(x)
 if not isinstance(v,dict):return
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-zÀ-ÿŒœ])'+re.escape(f)+r'(?![A-Za-zÀ-ÿŒœ])',text) and not any(n['fr']==f for n in ns):ns.append(dict(fr=f,transcription_ru=p))
  if ns:v['pronunciation_notes']=ns
 for x in list(v.values()):annotate(x)
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Близкие родственники, дети и семья: названия с родом, формы «мой», «моя», «мои» и короткие вопросы по образцам.',learning_outcomes=['Понимать названия родителей, братьев и сестёр.','Называть сына, дочь, ребёнка, дядю и тётю в семейном контексте.','Выбирать показанные формы перед одним и несколькими родственниками.','Спрашивать о детях и о представленном человеке; различать наличие детей и точное количество.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u06-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'PRE_A1-U06.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('U06 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('cest-identify','Вернуть знакомое указание на один предмет перед добавлением цвета.'),('ce-sont-identify','При уместном возврате различить один предмет и несколько без нового материала о семье.'),('mon-possessive','Сохранить изученную форму перед словами мужского рода, не вводя все исключения.'),('ma-possessive','Вернуться к знакомой форме перед словом женского рода, если она нужна в контексте цветов.'),('famille','Сохранить семейный контекст для позднего повторения, не перегружая новую тему цветами и новыми родственниками.'),('deux','Вернуть точное количество предметов и не смешивать его с множественным числом без количества.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['До импорта U06 целевая база должна содержать PRE_A1-U05-L05. Подготовленный пакет не создаёт недостающую зависимость.','Человеку нужно прослушать родственные слова и целые фразы с носовыми гласными и связями перед гласным, особенно сочетания с детьми и дядей.','Выполнить языковую редактуру, предпросмотр и пробное прохождение. Оценка 6 минут на занятие не измерена.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U07',previous_unit='PRE_A1-U05',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку U06; отдельного подтверждения языкового принятия или импорта U05 нет.','Существующие ключи указания и числительных сохранены. Применение образца указания к людям не создаёт новый ключ для того же значения «это».','Родственные слова во множественном числе сохраняют свои смысловые ключи; новые значения для отдельных форм не создаются.','Дочь рассматривается как семейная роль. Для значения «девочка» новый ключ здесь не вводится.','Формы mon/ma/mes отрабатываются в показанных сочетаниях; исключения для всего класса не заявляются.','Полный образец j’ai des enfants имеет отдельный ключ как полезное выражение о детях. Прежние ключи j’ai для возраста и наличия вещи не меняются.','В L01–L04 соответственно 5, 3, 5 и 4 новых значения; в L05 новых обязательных слов или правил нет.','В заданиях сборки assessed_vocabulary_ids пуст: подписанное сообщение не оценивает знание всех слов.','Основной LEXEME_REGISTRY.json и ранее подготовленные пакеты не изменены. Новые ключи передаются отдельным delta до принятия.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
(OUT/'PRE_A1-U06.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))
