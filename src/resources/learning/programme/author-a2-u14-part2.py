"""Author A2-U14 part 2 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/A2.plan.json').read_text())['level']
plan=level['units'][13]
W={}
for source in [OUT/'A2-U14.v1.json',OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='A2.U14.UNDERSTAND';A='A2.U14.APPLY'
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
 s.update(version=1,level='A2',unit_id=plan['id'],description=d,duration_minutes=7,objectives=copy.deepcopy(plan['objectives']),skills=[],vocabulary=[copy.deepcopy(W[w]) for w in vs],tasks=[],introductions=[]); S.append(s); return s

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
 if aft=='.' and not ap:aft='';f=f.removesuffix('.');p=p.removesuffix('.')
 t=base(s,'vocabulary',gs,vs,av,ins,ru('Выберите одну карточку для пропуска.'),g,b)
 t.update(type='fill_blank',input_mode='choice',text_before=bef,text_before_transcription_ru=bp,text_after=aft,text_after_transcription_ru=ap,choices=opts(cs),correct_choice_id=f'c{a}',completed_phrases=[dict(fr=f,transcription_ru=p)])

 return t

def page(s,blocks):
 s['public_page']=dict(slug=s['id'].lower().replace('_','-'),summary=s['description'],seo_title=s['title']+' — практика французского',seo_description=s['description'],explanation_blocks=[dict(heading=h,paragraphs=ps,examples=es) for h,ps,es in blocks])

def grammar(t):
 t['skill']='grammar'
 return t

def phrase_pairs(s,gs,rows,g,b):
 vs=list(dict.fromkeys(w for f,p,r,ws,av in rows for w in ws)); avs=list(dict.fromkeys(w for f,p,r,ws,av in rows for w in av))
 t=base(s,'vocabulary',gs,vs,avs,'Соедините реплику или выражение с его смыслом.',ru('Используйте каждую карточку один раз.'),g,b)
 t.update(type='matching_pairs',left_items=[dict(id=f'l{i}',**fr(f,p)) for i,(f,p,r,ws,av) in enumerate(rows)],right_items=[dict(id=f'r{i}',**ru(r)) for i,(f,p,r,ws,av) in reversed(list(enumerate(rows)))],correct_pairs=[dict(left_id=f'l{i}',right_id=f'r{i}',assessed_vocabulary_ids=av) for i,(f,p,r,ws,av) in enumerate(rows)])
 return t

def addword(i,key,f,r,p,e,n,reviews):
 W[i]=dict(id=i,lexeme_key=key,fr=f,ru=[r],transcription_ru=p,example=e,review_items=reviews);FIRST[i]=n
def word(i,key,f,r,p,n,example,meaning_choices,meaning_answer,use_prompt,use_choices,use_answer):
 addword(i,key,f,r,p,example,n,[rev(i+'-meaning',fr(f,p),meaning_choices,meaning_answer,'Правильное значение: '+r+'.','Нужно значение «'+r+'»; остальные варианты называют другое.'),rev(i+'-use',ru(use_prompt),[fr(a,b) for a,b in use_choices],use_answer,'Выбран вариант, который сохраняет условие ситуации.','Нужная реплика: '+use_choices[use_answer][0]+'. '+use_prompt)])
word('etre-en-train-de','fr:etre-en-train-de:action-in-progress','être en train de','быть в процессе выполнения действия','этр а(н) трэ(н) дё',3,ex('Je suis en train d’écrire un message.','Я сейчас пишу сообщение.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж'),['Действие идёт сейчас','Действие только что закончено','Только желание сделать'],0,'Вы прямо сейчас пишете сообщение.',[('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),('Je suis en train d’écrire un message.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж'),('Je voudrais écrire un message.','жё вудрэ экрир э(н) мэса́ж')],1)
word('venir-de','fr:venir-de:recently-completed-action','venir de','только что выполнить действие','вёнир дё',3,ex('Je viens de terminer cette tâche.','Я только что закончил эту задачу.','жё вьэ(н) дё тэрминэ сэт та́ш'),['В процессе сейчас','Хочу выполнить','Только что выполнил'],2,'Нужно сообщить, что задача только что закончена.',[('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),('Je voudrais terminer cette tâche.','жё вудрэ тэрминэ сэт та́ш'),('Je m’occupe de cette tâche.','жё мокюп дё сэт та́ш')],0)
W['etre-en-train-de']['review_items'][1]['feedback']=fb('Сообщено о процессе письма прямо сейчас.','Желание и недавнее завершение не обозначают текущий процесс.')
W['venir-de']['review_items'][1]['feedback']=fb('Сообщено о недавнем завершении задачи.','Работа над задачей и желание закончить не подтверждают завершение.')

def read(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def warm(s,lesson):
 prior=json.loads((OUT/'A2-U11.v1.json').read_text());t=copy.deepcopy(next(t for t in prior['sessions'][lesson-1]['tasks'] if t['id']=='Q05'));t['id']='Q01';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))

F='— Je suis en train d’écrire un message. Je m’occupe de cette tâche. Le délai est vendredi à midi. — Je viens de terminer cette tâche. Je voudrais envoyer le message vendredi à midi.'
T='— жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж. жё мокюп дё сэт та́ш. лё дэлэ э ва(н)дрэди а миди́. — жё вьэ(н) дё тэрминэ сэт та́ш. жё вудрэ а(н)вуайе лё мэсаж ва(н)дрэди а миди́.'
V=['etre-en-train-de','ecrire','message','s-occuper-de','tache-travail','delai','vendredi','midi','venir-de','terminer','je-voudrais','envoyer']
s=start(3,'Различите действие в процессе и только что завершённую задачу.',[]);warm(s,3)
intro(s,2,'Être en train de в выбранной форме Je suis en train d’écrire un message — «Я сейчас пишу сообщение»: процесс идёт. Перед écrire предлог сокращается до d’. Venir de в форме Je viens de terminer cette tâche — «Я только что закончил задачу»: завершение уже произошло недавно. Это готовые рамки; не смешиваем желание, процесс и результат. Закончить написание не означает автоматически отправить сообщение.',['etre-en-train-de','venir-de','terminer'],[('Être en train de','этр а(н) трэ(н) дё'),('Je suis en train d’écrire un message','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж'),('écrire','экри́р'),('d’','д'),('Venir de','вёнир дё'),('Je viens de terminer cette tâche','жё вьэ(н) дё тэрминэ сэт та́ш')],('Je suis en train d’écrire un message. Je viens de terminer cette tâche.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж. жё вьэ(н) дё тэрминэ сэт та́ш.'))
phrase_pairs(s,[A],[('Je suis en train d’écrire un message.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж','Сейчас пишу сообщение.',['etre-en-train-de','ecrire','message'],['etre-en-train-de']),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш','Только что закончил задачу.',['venir-de','terminer','tache-travail'],['venir-de']),('Je voudrais terminer cette tâche.','жё вудрэ тэрминэ сэт та́ш','Хотел бы закончить задачу.',['je-voudrais','terminer','tache-travail'],['je-voudrais'])],'Процесс, недавний результат и желание различены.','Не превращайте процесс или желание в завершённое действие.')
listen(s,[A],V,['etre-en-train-de','venir-de'],'Два отдельных сообщения: что говорится о ходе работы?',F,T,['Первый пишет сейчас; второй только что закончил задачу','Оба только собираются начать','Первый уже отправил сообщение; второй пишет сейчас'],0,'Первый описывает процесс, второй — недавнее завершение.','Отправка не подтверждена: во втором сообщении она выражена как желание.')
choose(s,'vocabulary',[A],V,['venir-de'],'Нужно сообщить о только что завершённой задаче.',ru('Сохраните недавний результат.'),[fr('Je voudrais terminer cette tâche.','жё вудрэ тэрминэ сэт та́ш'),fr('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),fr('Je suis en train d’écrire un message.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж')],1,'Выбран недавний результат.','Желание и текущий процесс не подтверждают завершения.')
read(s,[A],V,['venir-de','je-voudrais','envoyer'],'Что во втором сообщении уже произошло, а что только желательно?',F,T,['Уже отправлено; работа не закончена','Ничего не закончено','Задача только что закончена; отправка ещё не подтверждена'],2,'Завершение названо прямо, отправка — желание.','Не переносите значение завершения на другое действие.')
grammar(fill(s,[A],['venir-de','terminer','tache-travail','je-voudrais'],['venir-de'],'Восстановите только что закончил, а не хотел бы закончить.','Je ','жё',' terminer cette tâche.','тэрминэ сэт та́ш',[fr('viens de','вьэ(н) дё'),fr('voudrais','вудрэ́')],0,'Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш.','Восстановлено недавнее завершение.','Желание не сообщает о результате.'))
order(s,[A],['etre-en-train-de','ecrire','message'],[],'Соберите: я прямо сейчас пишу сообщение. Начните с говорящего, затем рамка процесса, действие и предмет.',[('Je suis','жё сюи'),('en train','а(н) трэ(н)'),('d’écrire','дэкри́р'),('un message.','э(н) мэса́ж')],'Je suis en train d’écrire un message.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж.','Назван текущий процесс письма.','Сохраните рамку процесса и цельный блок с апострофом.')
page(s,[('Процесс сейчас',['В выбранной рамке действие выполняется прямо сейчас. Это не только намерение.'],[W['etre-en-train-de']['example']]),('Недавний результат',['Другая рамка сообщает о только что выполненном действии. Завершение одной задачи не доказывает отправку сообщения.'],[W['venir-de']['example']])])

G='Ma tâche est d’écrire un message. Je m’occupe de cette tâche. Aidez-moi, s’il vous plaît. Je voudrais terminer cette tâche d’ici vendredi à midi. Pourriez-vous préciser le délai ? Je suis disponible mardi matin.'
H='ма таш э дэкрир э(н) мэса́ж. жё мокюп дё сэт та́ш. эдэ муа, силь ву плэ́. жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́. пурье ву прэсизэ лё дэлэ́? жё сюи диспонибль марди матэ́(н).'
Z=['tache-travail','ecrire','message','s-occuper-de','aidez-moi','sil-vous-plait','je-voudrais','terminer','dici-deadline','vendredi','midi','pourriez-vous','preciser','delai','disponible','mardi','matin']
s=start(4,'Попросите помощи и уточните срок, не подменяя просьбу подтверждением выполнения.',[]);warm(s,4)
intro(s,2,'Повторяем Aidez-moi, s’il vous plaît — «Помогите мне, пожалуйста». Вежливость выражена знакомой формулой пожалуйста. Pourriez-vous préciser le délai ? просит уточнить срок. Это разные цели: помощь в работе и уточнение условия. Просьба о помощи не сообщает, что работа уже выполнена.',['aidez-moi','sil-vous-plait','delai'],[('Aidez-moi, s’il vous plaît','эдэ муа, силь ву плэ́'),('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́')])
phrase_pairs(s,[A,U],[('Aidez-moi, s’il vous plaît.','эдэ муа, силь ву плэ́','Помогите мне, пожалуйста.',['aidez-moi','sil-vous-plait'],['aidez-moi']),('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́','Не могли бы вы уточнить срок?',['pourriez-vous','preciser','delai'],['preciser','delai']),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш','Я только что закончил задачу.',['venir-de','terminer','tache-travail'],['venir-de'])],'Помощь, уточнение и результат различены.','Не заменяйте просьбу сообщением о готовой работе.')
listen(s,[A],Z,['aidez-moi'],'Какая просьба прямо касается помощи?',G,H,['Уточнить цену','Помогите мне, пожалуйста','Сообщение уже отправлено'],1,'Прямо просят помочь.','Цена и завершённая отправка в этой просьбе не названы.')
choose(s,'vocabulary',[U],['pourriez-vous','preciser','delai','tarif','horaire'],['delai'],'Нужно уточнить срок выполнения.',ru('Выберите вопрос о нужном условии.'),[fr('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́'),fr('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф'),fr('Pourriez-vous préciser l’horaire ?','пурье ву прэсизэ лорэ́р')],0,'Вопрос касается срока.','Цена и общее расписание не заменяют крайний срок задачи.')
read(s,[U,A],Z,['dici-deadline','aidez-moi'],'Какие просьба и крайний срок выражены?',G,H,['Просят помощи; закончить уже удалось','Просят изменить цену; строго в пятницу в полдень','Просят помощи; хотят закончить не позднее пятницы, полудня'],2,'Помощь запрошена, а завершение к сроку выражено как желание.','Крайний срок допускает более раннее выполнение и не подтверждает результат.')
fill(s,[A],['aidez-moi','sil-vous-plait','merci'],['aidez-moi'],'Восстановите просьбу помочь, а не благодарность.','Pour cette tâche, ','пур сэт таш',', s’il vous plaît.','силь ву плэ́',[fr('merci','мэрси́'),fr('aidez-moi','эдэ муа́')],1,'Pour cette tâche, aidez-moi, s’il vous plaît.','пур сэт таш, эдэ муа, силь ву плэ́.','Сформулирована просьба о помощи.','Благодарность сама по себе не просит помощи.')
order(s,[A],['aidez-moi','sil-vous-plait','tache-travail'],[],'Соберите: помогите мне, пожалуйста, с этой задачей. Начните с просьбы, затем пожалуйста и уточнение задачи.',[('Aidez-moi,','эдэ муа́'),('s’il vous plaît,','силь ву плэ́'),('pour','пур'),('cette tâche.','сэт та́ш')],'Aidez-moi, s’il vous plaît, pour cette tâche.','эдэ муа, силь ву плэ, пур сэт та́ш.','Просьба помощи относится к данной задаче.','После просьбы поставьте пожалуйста, затем уточнение задачи.')
page(s,[('Просить помощи',['Знакомая просьба дополняется пожалуйста и конкретной задачей. Она не является заявлением о результате.'],[ex('Aidez-moi, s’il vous plaît.','Помогите мне, пожалуйста.','эдэ муа, силь ву плэ́')]),('Уточнять условие',['Отдельный вопрос о сроке сохраняет свою цель. Цена и расписание не подменяют срок выполнения.'],[ex('Pourriez-vous préciser le délai ?','Не могли бы вы уточнить срок?','пурье ву прэсизэ лё дэлэ́')])])

J='Je viens de terminer cette tâche. Ma tâche est d’écrire un message. Je voudrais envoyer le message vendredi à midi. Le délai est vendredi à midi. Je suis disponible vendredi matin.'
K='жё вьэ(н) дё тэрминэ сэт та́ш. ма таш э дэкрир э(н) мэса́ж. жё вудрэ а(н)вуайе лё мэсаж ва(н)дрэди а миди́. лё дэлэ э ва(н)дрэди а миди́. жё сюи диспонибль ва(н)дрэди матэ́(н).'
B=['venir-de','terminer','tache-travail','ecrire','message','je-voudrais','envoyer','vendredi','midi','delai','disponible','matin']
s=start(5,'Объедините задачу, срок, текущий процесс, просьбу и недавний результат без нового материала.',[])
listen(s,[A],B,['venir-de','terminer','envoyer'],'Что уже выполнено, а что ещё не подтверждено?',J,K,['Задача только что закончена; отправка выражена как желание','Сообщение уже доставлено','Ничего не закончено'],0,'Завершение задачи названо, отправка ещё не подтверждена.','Не распространяйте завершение на действие, выраженное как желание.')
read(s,[U],B,['delai','vendredi','midi'],'Какой срок указан?',J,K,['Вторник утром','Пятница, полдень','Пятница утром'],1,'Срок — пятница, полдень.','Утро относится к доступности говорящего, а не к сроку.')
phrase_pairs(s,[A],[('Je suis en train d’écrire un message.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж','Пишу прямо сейчас.',['etre-en-train-de','ecrire','message'],['etre-en-train-de']),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш','Только что закончил задачу.',['venir-de','terminer','tache-travail'],['venir-de']),('Aidez-moi, s’il vous plaît.','эдэ муа, силь ву плэ́','Помогите мне, пожалуйста.',['aidez-moi','sil-vous-plait'],['aidez-moi'])],'Процесс, результат и помощь различены.','Не подменяйте одну функцию другой.')
listen(s,[A],Z,['aidez-moi','dici-deadline'],'Какие помощь и желание выполнения выражены?',G,H,['Работа уже завершена и отправлена','Просят оплатить задачу','Просят помощи и хотят закончить к пятнице, полудню'],2,'Есть просьба помочь и желание закончить к сроку.','Результат и отправка не подтверждены.')
read(s,[A],V,['etre-en-train-de','venir-de'],'Два отдельных сообщения: как различается состояние работы?',F,T,['В первом процесс; во втором недавнее завершение','В обоих только желание начать','В обоих подтверждена отправка'],0,'Процесс и недавний результат названы разными рамками.','Не делайте вывод об отправке по рамке завершения другой задачи.')
choose(s,'vocabulary',[U],['pourriez-vous','preciser','delai','tarif','horaire'],['delai'],'Выберите вопрос о сроке выполнения.',ru('Нужен крайний срок задачи.'),[fr('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф'),fr('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́'),fr('Pourriez-vous préciser l’horaire ?','пурье ву прэсизэ лорэ́р')],1,'Вопрос относится к сроку.','Цена и расписание — другие условия.')
grammar(fill(s,[A],['venir-de','terminer','tache-travail','je-voudrais'],['venir-de'],'Восстановите только что завершил.','Je ','жё',' terminer cette tâche.','тэрминэ сэт та́ш',[fr('voudrais','вудрэ́'),fr('viens de','вьэ(н) дё')],1,'Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш.','Восстановлен недавний результат.','Желание не утверждает, что задача завершена.'))
order(s,[A],['venir-de','terminer','tache-travail','merci','aide'],[],'Соберите: я только что закончил эту задачу. Спасибо за помощь. Сначала результат, затем благодарность.',[('Je viens de','жё вьэ(н) дё'),('terminer','тэрминэ́'),('cette tâche.','сэт та́ш'),('Merci pour','мэрси пур'),('l’aide.','лэ́д')],'Je viens de terminer cette tâche. Merci pour l’aide.','жё вьэ(н) дё тэрминэ сэт та́ш. мэрси пур лэ́д.','Результат предшествует благодарности.','Сначала завершите сообщение о выполнении, затем поблагодарите за помощь.')
page(s,[('Состояние работы',['Повторяются процесс сейчас, желание и недавнее завершение. Нового обязательного материала нет.'],[W['etre-en-train-de']['example'],W['venir-de']['example']]),('Срок и результат',['Время доступности не равно крайнему сроку. Закончить написание сообщения не означает уже отправить его.'],[ex('Merci pour l’aide.','Спасибо за помощь.','мэрси пур лэ́д')])])

for s in S:
 required=list(dict.fromkeys(w for t in s['tasks'] for w in t['vocabulary_ids']))
 required+=list(dict.fromkeys(w for i in s['introductions'] for w in i['vocabulary_ids']))
 s['vocabulary']=[w for w in s['vocabulary'] if w['id'] in required]
 present={w['id'] for w in s['vocabulary']}
 for w in required:
  if w not in present:s['vocabulary'].append(copy.deepcopy(W[w]));present.add(w)
 s['skills']=list(dict.fromkeys(t['skill'] for t in s['tasks']))
NOTES={w['fr']:w['transcription_ru'] for w in W.values()}
NOTES.update({W[w]['fr'].capitalize():W[w]['transcription_ru'] for w in FIRST})

def annotate(v):
 if isinstance(v,list):
  for x in v:annotate(x)
 if not isinstance(v,dict):return
 if 'lexeme_key' in v and v.get('id') in OLD:return
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-zÀ-ÿŒœ])'+re.escape(f)+r'(?![A-Za-zÀ-ÿŒœ])',text) and not any(n['fr']==f for n in ns):ns.append(dict(fr=f,transcription_ru=p))
  if ns:v['pronunciation_notes']=ns
 for x in list(v.values()):annotate(x)
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Простая рабочая задача, срок, ход выполнения, просьба о помощи и сообщение о недавнем результате.',learning_outcomes=['Понимать содержание задачи и крайний срок.','Различать текущий процесс и недавний результат.','Просить помощи и уточнять срок.','Отличать завершение написания от отправки сообщения.'])
P=dict(schema_version=1,package_id='programme-a2-u14-part2-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U14.part2.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U14.part2 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=plan['sessions'][n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('etre-en-train-de','Сохранять текущий процесс отдельно от результата.'),('venir-de','Повторить недавнее завершение.'),('terminer','Отличать завершение работы от отправки сообщения.'),('delai','Сохранять срок отдельно от доступного времени.'),('aidez-moi','Вернуть просьбу о помощи.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить зависимость A2-U14-L02 в целевой базе; этот пакет не импортирует первую часть.','Редактору проверить естественность рабочих реплик, помощь и различие процесса, желания и результата.','Прослушать браузерную озвучку en train d’écrire, viens de terminer и просьб. Длительность не измерена.','Выполнить предпросмотр и прохождение на сайте; фактически это не выполнялось.'],progress=dict(current_unit='A2-U14',prepared_session_ids=[x['id'] for x in plan['sessions']],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='A2-U15',next_unit_title='Сообщения и объявления'),scope_notes=['Команда «Следующий этап» завершает ранее незаконченный A2-U14; A2-U15 не начат.','Пакет продолжения содержит только L03–L05, с отдельным package_id и внешней зависимостью от L02. Первая часть и её версии не изменены.','Две новые рамки вводятся в L03 до проверки; в L04 и L05 новых обязательных значений нет.','Формы Je suis en train d’écrire и Je viens de terminer учатся как простые рамки, не как полные таблицы спряжения.','Завершение написания не доказывает отправку или доставку. Желание не равно выполнению, доступное утро не равно крайнему сроку.','В L03 повторены поздравления из A2-U11, в L04 — фон и событие из той же темы. Прежние ключи и полные карточки сохранены.','Aidez-moi и s’il vous plaît повторяются без нового ключа; просьба помощи отличается от уточнения срока.','Все рабочие ситуации и сроки вымышлены. Сообщения не отправлялись, база и реестр не изменены. Принятие и импорт пользователем не подтверждены.','Озвучка только браузером, fr-FR, asset_id=null, без файлов и внешних сервисов.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Je suis en train d’écrire / Je viens de terminer; процесс и недавний результат.'),dict(session_id=S[1]['id'],material='Повторение Aidez-moi, s’il vous plaît и вопроса о сроке.')]
(OUT/'A2-U14.part2.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
