"""Author A2-U14 part 1 only; preserve previous sessions and lexical meanings."""
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
for source in [OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
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
word('tache-travail','fr:tache:work-task','une tâche','рабочая задача','юн та́ш',1,ex('Ma tâche est d’écrire un message.','Моя задача — написать сообщение.','ма таш э дэкрир э(н) мэса́ж'),['Рабочая задача','Домашняя обязанность по уборке','Способ оплаты'],0,'Назовите рабочую задачу, не ограничивая её домашним хозяйством.',[('Une tâche.','юн та́ш'),('Un paiement.','э(н) пэма́(н)'),('Une inscription.','юн э(н)скрипсьо́(н)')],0)
word('s-occuper-de','fr:s-occuper-de:handle-task','s’occuper de','заниматься задачей, брать её на себя','сокюпэ дё',1,ex('Je m’occupe de cette tâche.','Я занимаюсь этой задачей.','жё мокюп дё сэт та́ш'),['Закончить задачу','Заниматься задачей','Быть свободным'],1,'Сообщите, что занимаетесь этой задачей.',[('Je suis disponible mardi.','жё сюи диспонибль марди́'),('Je m’occupe de cette tâche.','жё мокюп дё сэт та́ш'),('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт')],1)
word('terminer','fr:terminer:finish-task','terminer','заканчивать задачу или работу','тэрминэ́',1,ex('Je voudrais terminer cette tâche.','Мне бы хотелось закончить эту задачу.','жё вудрэ тэрминэ сэт та́ш'),['Начинать обучение','Уточнять цену','Заканчивать работу'],2,'Вы хотите закончить задачу.',[('Je voudrais m’inscrire.','жё вудрэ мэ(н)скри́р'),('Je voudrais préciser le tarif.','жё вудрэ прэсизэ лё тари́ф'),('Je voudrais terminer cette tâche.','жё вудрэ тэрминэ сэт та́ш')],2)
word('delai','fr:delai:task-completion-deadline','un délai','срок выполнения задачи','э(н) дэлэ́',2,ex('Le délai est vendredi à midi.','Срок выполнения — пятница, полдень.','лё дэлэ э ва(н)дрэди а миди́'),['Срок выполнения','Длительность прошлого эпизода','Способ оплаты'],0,'Уточните именно срок выполнения.',[('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́'),('Comment payer ?','кома(н) пэйе́'),('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф')],0)
for w,g,b in [('tache-travail','Названа рабочая задача.','Оплата и запись не являются названием рабочей задачи.'),('s-occuper-de','Сообщено, что говорящий занимается задачей.','Свободное время и способ оплаты не сообщают о работе над задачей.'),('terminer','Названо желание закончить задачу.','Запись и уточнение цены имеют другие цели.'),('delai','Вопрос касается срока выполнения.','Цена и способ оплаты не определяют срок.')]:W[w]['review_items'][1]['feedback']=fb(g,b)

def reading(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def warmup(s,unit,lesson,task):
 prior=json.loads((OUT/f'{unit}.v1.json').read_text());t=copy.deepcopy(next(t for t in prior['sessions'][lesson-1]['tasks'] if t['id']==task));t['id']='Q01';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))

s=start(1,'Поймите содержание простой задачи и отличите работу над ней от желания закончить.',[])
warmup(s,'A2-U13',2,'Q05')
intro(s,2,'Une tâche здесь — рабочая задача, а не только домашняя обязанность. Ma tâche est d’écrire un message — «Моя задача — написать сообщение». S’occuper de в выбранной форме Je m’occupe de cette tâche означает «Я занимаюсь этой задачей». Terminer — заканчивать. Je voudrais terminer выражает желание закончить, а не готовый результат.',['tache-travail','s-occuper-de','terminer'],[('Une tâche','юн та́ш'),('Ma tâche est d’écrire un message','ма таш э дэкрир э(н) мэса́ж'),('S’occuper de','сокюпэ дё'),('Je m’occupe de cette tâche','жё мокюп дё сэт та́ш'),('Terminer','тэрминэ́'),('Je voudrais terminer','жё вудрэ тэрминэ́')],('Ma tâche est d’écrire un message. Je m’occupe de cette tâche. Je voudrais terminer cette tâche.','ма таш э дэкрир э(н) мэса́ж. жё мокюп дё сэт та́ш. жё вудрэ тэрминэ сэт та́ш.'))
pairs(s,[U],[('tache-travail','Рабочая задача'),('s-occuper-de','Заниматься задачей'),('terminer','Заканчивать работу')],'Различены задача, работа над ней и завершение.','Заниматься задачей не означает, что она уже закончена.')
listen(s,[U],['tache-travail','ecrire','message','s-occuper-de','je-voudrais','terminer','envoyer','vendredi','midi','travailler','bureau','mardi','matin'],['tache-travail','ecrire','message'],'В чём состоит названная задача?', 'Ma tâche est d’écrire un message. Je m’occupe de cette tâche. Je voudrais terminer cette tâche et envoyer le message vendredi à midi. Je travaille au bureau mardi matin. Je voudrais écrire un message.','ма таш э дэкрир э(н) мэса́ж. жё мокюп дё сэт та́ш. жё вудрэ тэрминэ сэт таш э а(н)вуайе лё мэсаж ва(н)дрэди а миди́. жё травай о бюро марди матэ́(н). жё вудрэ экрир э(н) мэса́ж.',['Написать сообщение','Оплатить обучение','Посмотреть квартиру'],0,'Задача — написать сообщение.','Рабочее место и время не заменяют содержание задачи.')
choose(s,'vocabulary',[U],['s-occuper-de','tache-travail','je-voudrais','terminer','disponible','mardi'],['s-occuper-de'],'Выберите сообщение: я занимаюсь этой задачей.',ru('Сохраните работу над задачей, а не только желание закончить.'),[fr('Je voudrais terminer cette tâche.','жё вудрэ тэрминэ сэт та́ш'),fr('Je m’occupe de cette tâche.','жё мокюп дё сэт та́ш'),fr('Je suis disponible mardi.','жё сюи диспонибль марди́')],1,'Говорящий сообщает о работе над задачей.','Желание закончить не утверждает, что человек уже занимается задачей; доступность — другое сведение.')
reading(s,[U],['tache-travail','ecrire','message','s-occuper-de','je-voudrais','terminer','envoyer','vendredi','midi','travailler','bureau','mardi','matin'],['je-voudrais','terminer','envoyer'],'Что текст позволяет сказать о завершении и отправке?', 'Ma tâche est d’écrire un message. Je m’occupe de cette tâche. Je voudrais terminer cette tâche et envoyer le message vendredi à midi. Je travaille au bureau mardi matin. Je voudrais écrire un message.','ма таш э дэкрир э(н) мэса́ж. жё мокюп дё сэт та́ш. жё вудрэ тэрминэ сэт таш э а(н)вуайе лё мэсаж ва(н)дрэди а миди́. жё травай о бюро марди матэ́(н). жё вудрэ экрир э(н) мэса́ж.',['Сообщение уже отправлено','Все задачи уже закончены','Выражено желание закончить и отправить; результат не подтверждён'],2,'Желание закончить и отправить не является подтверждением результата.','Не превращайте план или желание в уже выполненное действие.')
fill(s,[U],['s-occuper-de','tache-travail','travailler'],['s-occuper-de'],'Восстановите выбранную рамку: я занимаюсь этой задачей.','Je ','жё',' de cette tâche.','дё сэт та́ш',[fr('m’occupe','мокюп'),fr('travaille','трава́й')],0,'Je m’occupe de cette tâche.','жё мокюп дё сэт та́ш.','Рамка сохраняет значение заниматься задачей.','Работать не образует эту выбранную рамку с данным предлогом.')
order(s,[U],['s-occuper-de','tache-travail','je-voudrais','terminer'],[],'Соберите: я занимаюсь этой задачей. Мне бы хотелось закончить. Сначала работа над задачей, затем желание.',[('Je m’occupe','жё мокюп'),('de cette tâche.','дё сэт та́ш'),('Je voudrais','жё вудрэ́'),('terminer.','тэрминэ́')],'Je m’occupe de cette tâche. Je voudrais terminer.','жё мокюп дё сэт та́ш. жё вудрэ тэрминэ́.','Работа над задачей и желание закончить различены.','Сначала завершите сообщение о задаче, затем добавьте желание закончить.')
page(s,[('Что нужно сделать',['Рабочая задача называет конкретное действие: здесь написать сообщение. Домашняя обязанность остаётся отдельным знакомым контекстом.'],[W['tache-travail']['example']]),('Работа и результат',['Заниматься задачей и хотеть закончить не равны сообщению о выполнении. Проверяйте форму самой реплики.'],[W['s-occuper-de']['example'],W['terminer']['example']])])

s=start(2,'Уточните срок выполнения и отличите крайний срок от строго назначенного времени.',[])
t=copy.deepcopy(S[0]['tasks'][4]);t['id']='Q01';s['tasks'].append(t)
intro(s,2,'Un délai — срок выполнения задачи. В этой ситуации Le délai est vendredi à midi: крайний срок — пятница, полдень. Знакомое d’ici задаёт «не позднее»: Je voudrais terminer cette tâche d’ici vendredi à midi. Допускается закончить раньше. Указание срока не сообщает, что работа уже выполнена.',['delai','dici-deadline','terminer'],[('Un délai','э(н) дэлэ́'),('Le délai est vendredi à midi','лё дэлэ э ва(н)дрэди а миди́'),('d’ici','диси́'),('Je voudrais terminer cette tâche d’ici vendredi à midi','жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́')],('Pourriez-vous préciser le délai ? Le délai est vendredi à midi. Je voudrais terminer cette tâche d’ici vendredi à midi.','пурье ву прэсизэ лё дэлэ́? лё дэлэ э ва(н)дрэди а миди́. жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́.'))
phrase_pairs(s,[U],[('Un délai','э(н) дэлэ́','Срок выполнения',['delai'],['delai']),('D’ici vendredi à midi','диси ва(н)дрэди а миди́','Не позднее пятницы, полудня',['dici-deadline','vendredi','midi'],['dici-deadline']),('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́','Не могли бы вы уточнить срок выполнения?',['pourriez-vous','preciser','delai'],['delai','preciser'])],'Различены срок и просьба его уточнить.','Не позднее допускает более раннее завершение, а не только точный момент.')
listen(s,[U],['pourriez-vous','preciser','delai','vendredi','midi','s-occuper-de','tache-travail','je-voudrais','terminer','dici-deadline','ecrire','message'],['delai','vendredi','midi'],'Какой крайний срок назван?', '— Pourriez-vous préciser le délai ? — Le délai est vendredi à midi. — Je m’occupe de cette tâche. Je voudrais terminer cette tâche d’ici vendredi à midi. Ma tâche est d’écrire un message.','— пурье ву прэсизэ лё дэлэ́? — лё дэлэ э ва(н)дрэди а миди́. — жё мокюп дё сэт та́ш. жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́. ма таш э дэкрир э(н) мэса́ж.',['Вторник, полдень','Пятница, полдень','Пятница, десять вечера'],1,'Названа пятница, полдень.','Не меняйте день и час; сообщение о работе над задачей не меняет срок.')
choose(s,'vocabulary',[U],['pourriez-vous','preciser','delai','tarif','horaire'],['delai'],'Выберите просьбу уточнить срок выполнения задачи.',ru('Нужен срок, а не цена или расписание.'),[fr('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф'),fr('Pourriez-vous préciser l’horaire ?','пурье ву прэсизэ лорэ́р'),fr('Pourriez-vous préciser le délai ?','пурье ву прэсизэ лё дэлэ́')],2,'Вопрос направлен на срок выполнения.','Цена и расписание не заменяют срок данной задачи.')
reading(s,[U],['pourriez-vous','preciser','delai','vendredi','midi','s-occuper-de','tache-travail','je-voudrais','terminer','dici-deadline','ecrire','message'],['dici-deadline','terminer'],'Как понимать время завершения в ответе?', '— Pourriez-vous préciser le délai ? — Le délai est vendredi à midi. — Je m’occupe de cette tâche. Je voudrais terminer cette tâche d’ici vendredi à midi. Ma tâche est d’écrire un message.','— пурье ву прэсизэ лё дэлэ́? — лё дэлэ э ва(н)дрэди а миди́. — жё мокюп дё сэт та́ш. жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́. ма таш э дэкрир э(н) мэса́ж.',['Не позднее пятницы, полудня; можно раньше','Только ровно в пятницу в полдень','Уже закончено и отправлено'],0,'Крайний срок допускает более раннее завершение.','Названное желание закончить к сроку не подтверждает фактическое выполнение или отправку.')
fill(s,[U],['je-voudrais','terminer','tache-travail','dici-deadline','vendredi','midi'],['dici-deadline'],'Восстановите не позднее пятницы, полудня.','Je voudrais terminer cette tâche ','жё вудрэ тэрминэ сэт таш',' vendredi à midi.','ва(н)дрэди а миди́',[fr('pendant','па(н)да́(н)'),fr('d’ici','диси́')],1,'Je voudrais terminer cette tâche d’ici vendredi à midi.','жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́.','Выбран показатель крайнего срока.','Предлог длительности не задаёт эту модель не позднее.')
order(s,[U],['je-voudrais','terminer','tache-travail','dici-deadline','vendredi','midi'],[],'Соберите: мне бы хотелось закончить эту задачу не позднее пятницы, полудня. Начните с желания, затем действие, задачу и крайний срок.',[('Je voudrais','жё вудрэ́'),('terminer','тэрминэ́'),('cette tâche','сэт та́ш'),('d’ici vendredi à midi.','диси ва(н)дрэди а миди́')],'Je voudrais terminer cette tâche d’ici vendredi à midi.','жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́.','Сохранены задача и крайний срок.','После задачи поставьте готовое уточнение не позднее пятницы, полудня.')
page(s,[('Уточнить срок',['Срок выполнения отличается от цены и общего расписания. Вопрос должен называть нужную деталь.'],[W['delai']['example']]),('Не позднее',['Знакомая рамка крайнего срока допускает более раннее завершение. Она не равна указанию сделать строго в названный момент.'],[ex('Je voudrais terminer cette tâche d’ici vendredi à midi.','Мне бы хотелось закончить эту задачу не позднее пятницы, полудня.','жё вудрэ тэрминэ сэт таш диси ва(н)дрэди а миди́')])])
S[1]['tasks'][5]['vocabulary_ids'].append('pendant-duree')


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
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Рабочие задачи: описание действия и уточнение крайнего срока. Первая часть темы, два занятия.',learning_outcomes=['Понимать содержание простой рабочей задачи.','Отличать работу над задачей и желание закончить от подтверждённого результата.','Уточнять срок выполнения.','Понимать крайний срок как не позднее с возможностью закончить раньше.'])
P=dict(schema_version=1,package_id='programme-a2-u14-part1-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U14.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U14 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=reason) for w,reason in [('terminer','Вернуть завершение отдельно от текущей работы.'),('s-occuper-de','Повторить работу над задачей.'),('delai','Сохранить крайний срок отдельно от результата.'),('dici-deadline','Повторить не позднее, допускающее более раннее завершение.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить внешнюю зависимость A2-U13-L05 в целевой базе.','Редактору проверить естественность описания задачи, рамку s’occuper de и объяснение срока; прослушать браузерную озвучку.','Пройти занятия на сайте и измерить время; фактически это не выполнялось.','Подготовить L03–L05: ход выполнения, помощь и итог; итоговое покрытие APPLY пока не заявлено.'],progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[x['id'] for x in plan['sessions'][2:]],next_unit_not_started='A2-U15',next_unit_title='Сообщения и объявления',previous_unit='A2-U13',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),scope_notes=['Полная первая часть этапа, два занятия с отдельным package_id. Следующая часть должна включать L03–L05 с зависимостью от L02; текущие ID и версии сохраняются.','Tâche как рабочая задача не заменяет прежнее tâche ménagère как домашнюю обязанность. S’occuper de не подменяется прежним occupé по расписанию.','D’ici, écrire, envoyer и travail используют прежние ключи. Упоминание желания закончить не подтверждает выполнение или отправку.','Délai показано как крайний срок конкретной задачи, не как длительность. Можно завершить раньше. Все задачи и сроки вымышлены.','В L01 повторён выбор программы из A2-U13. Повторение A2-U11 в L03/L04 и рамки venir de / être en train de остаются для продолжения текущего этапа.','Новые значения распределены как три и одно. Форма m’occupe объяснена до проверки.','Озвучка только браузером: fr-FR, asset_id=null. Сообщения не отправлялись, задания в реальной рабочей системе не создавались.','Принятие и импорт пакетов пользователем не подтверждены. Основной реестр и база не изменялись.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Ma tâche est d’écrire; Je m’occupe de cette tâche; Je voudrais terminer как желание, не результат.'),dict(session_id=S[1]['id'],material='Le délai est vendredi à midi; d’ici vendredi à midi как крайний срок.')]
(OUT/'A2-U14.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
