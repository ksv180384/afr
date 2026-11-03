"""Author A2-U15 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/A2.plan.json').read_text())['level']
plan=level['units'][14]
W={}
for source in [OUT/'A2-U14.v1.json',OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='A2.U15.UNDERSTAND';A='A2.U15.APPLY'
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
word('titre','fr:titre:message-headline','un titre','заголовок сообщения','э(н) ти́тр',1,ex('Le titre : Une fête au parc.','Заголовок: праздник в парке.','лё титр: юн фэт о па́рк'),['Заголовок','Время','Причина'],0,'Назовите заголовок, а не отправителя.',[('Un titre.','э(н) ти́тр'),('La mairie.','ла мэри́'),('Un délai.','э(н) дэлэ́')],0)
word('mairie','fr:mairie:municipal-office','la mairie','мэрия, муниципальная администрация','ла мэри́',1,ex('La mairie annonce une fête.','Мэрия объявляет о празднике.','ла мэри ано(н)с юн фэ́т'),['Праздник','Мэрия','Заголовок'],1,'Кто объявляет о празднике: мэрия?',[('Le titre.','лё ти́тр'),('La mairie.','ла мэри́'),('Le parc.','лё па́рк')],1)
word('annoncer','fr:annoncer:announce-event','annoncer','объявлять о событии','ано(н)сэ́',1,ex('La mairie annonce une fête.','Мэрия объявляет о празднике.','ла мэри ано(н)с юн фэ́т'),['Покупать','Заканчивать','Объявлять о событии'],2,'Выберите сообщение, что мэрия объявляет праздник.',[('La mairie annonce une fête.','ла мэри ано(н)с юн фэ́т'),('Je voudrais acheter un cadeau.','жё вудрэ аштэ э(н) кадо́'),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш')],0)
word('avoir-lieu','fr:avoir-lieu:event-occurrence','avoir lieu','происходить, состояться о событии','авуар льё',2,ex('La fête a lieu vendredi au parc.','Праздник состоится в пятницу в парке.','ла фэт а льё ва(н)дрэди о па́рк'),['Объявлять','Происходить о событии','Уточнять'],1,'Выберите сообщение о том, где состоится праздник.',[('La mairie annonce une fête.','ла мэри ано(н)с юн фэ́т'),('Le titre : Une fête.','лё титр: юн фэ́т'),('La fête a lieu au parc.','ла фэт а льё о па́рк')],2)
word('travaux','fr:travaux:construction-works','des travaux','строительные или ремонтные работы','дэ траво́',4,ex('Des travaux dans le parc.','Ремонтные работы в парке.','дэ траво да(н) лё па́рк'),['Рабочая задача с сообщением','Ремонтные работы','Способ оплаты'],1,'Назовите ремонтные работы в парке.',[('Un paiement.','э(н) пэма́(н)'),('Des travaux dans le parc.','дэ траво да(н) лё па́рк'),('Une inscription.','юн э(н)скрипсьо́(н)')],1)
word('en-raison-de','fr:en-raison-de:stated-cause','en raison de','по причине, из-за указанного обстоятельства','а(н) рэзо(н) дё',4,ex('En raison de travaux, la fête a lieu au musée.','Из-за ремонтных работ праздник состоится в музее.','а(н) рэзо(н) дё траво, ла фэт а льё о мюзэ́'),['По причине','Не позднее','Уже месяц'],0,'Выберите причину изменения места: ремонтные работы.',[('En raison de travaux.','а(н) рэзо(н) дё траво́'),('D’ici vendredi.','диси ва(н)дрэди́'),('Depuis un mois.','дёпюи э(н) муа́')],0)
for w,g,b in [('titre','Назван заголовок.','Мэрия — источник, срок — другое сведение.'),('mairie','Названа мэрия как источник объявления.','Заголовок и парк не обозначают муниципальную администрацию.'),('annoncer','Сообщено об объявлении праздника.','Покупка и завершение задачи — другие действия.'),('avoir-lieu','Названо место проведения события.','Сам факт объявления и заголовок не называют место проведения.'),('travaux','Названы ремонтные работы.','Оплата и запись на обучение не обозначают ремонт.'),('en-raison-de','Ремонтные работы названы причиной.','Крайний срок и текущая длительность не задают эту причинную связь.')]:W[w]['review_items'][1]['feedback']=fb(g,b)

def read(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def warm(s,u,n,q):
 old=json.loads((OUT/f'{u}.v1.json').read_text());t=copy.deepcopy(next(t for t in old['sessions'][n-1]['tasks'] if t['id']==q));t['id']='Q01';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))

# Each dataset contains a complete independently authored document, questions and feedback.
DATA=[dict(n=1,desc='Отличите заголовок, источник объявления и названное событие.',warm=('A2-U14',5,'Q02'),intro='Un titre — заголовок сообщения. La mairie — мэрия, здесь источник объявления. Annoncer — объявлять; выбранная форма annonce: La mairie annonce une fête. Заголовок кратко называет событие и не является сам по себе именем отправителя.',iv=['titre','mairie','annoncer'],notes=[('Un titre','э(н) ти́тр'),('La mairie','ла мэри́'),('Annoncer','ано(н)сэ́'),('annonce','ано́(н)с'),('La mairie annonce une fête','ла мэри ано(н)с юн фэ́т')],rows=[('titre','Заголовок'),('mairie','Мэрия как источник'),('annoncer','Объявлять о событии')],f='Le titre : Une fête au parc. La mairie annonce une fête vendredi à huit heures du soir. La fête est au parc. La mairie annonce une fête pour la famille. Le parc est près du musée.',p='лё титр: юн фэт о па́рк. ла мэри ано(н)с юн фэт ва(н)дрэди а юи тёр дю суа́р. ла фэт э то па́рк. ла мэри ано(н)с юн фэт пур ла фами́й. лё парк э прэ дю мюзэ́.',vs=['titre','mairie','annoncer','fete','parc','vendredi','huit','heure','soir','famille','pres-de','musee'],av=['mairie','annoncer','fete'],aq='Кто объявляет и о каком событии?',ac=['Мэрия; праздник','Парк; покупка','Семья; доставка'],aa=0,rq='Что называет заголовок?',rc=['Запись на обучение','Праздник в парке','Завершение рабочей задачи'],ra=1,vq='Назовите источник этого объявления.',vc=[('Le parc.','лё па́рк'),('Le titre.','лё ти́тр'),('La mairie.','ла мэри́')],va=2,vid=['mairie'],before='La mairie ',bp='ла мэри',after=' une fête.',ap='юн фэ́т',fc=[('annonce','ано́(н)с'),('annoncer','ано(н)сэ́')],fa=0,full='La mairie annonce une fête.',fp='ла мэри ано(н)с юн фэ́т.',fins='Выберите изученную форму глагола объявлять.',tokens=[('La mairie','ла мэри́'),('annonce','ано́(н)с'),('une fête','юн фэ́т'),('au parc.','о па́рк')],of='La mairie annonce une fête au parc.',op='ла мэри ано(н)с юн фэт о па́рк.',oi='Соберите: мэрия объявляет праздник в парке. Сначала источник, затем действие, событие и место.',goals=[U]),
dict(n=2,desc='Поймите главное событие и отличите его от объявления о нём.',warm=None,intro='Avoir lieu означает происходить или состояться о событии. Выбранная форма: La fête a lieu vendredi au parc — «Праздник состоится в пятницу в парке». Объявить событие и назвать его проведение — разные части сообщения. Форма a lieu сама по себе не означает, что событие уже прошло.',iv=['avoir-lieu','annoncer','fete'],notes=[('Avoir lieu','авуар льё'),('La fête a lieu vendredi au parc','ла фэт а льё ва(н)дрэди о па́рк'),('a lieu','а льё')],rows=[('avoir-lieu','Происходить о событии'),('annoncer','Объявлять о событии'),('titre','Заголовок сообщения')],f='La mairie annonce une fête pour la famille. La fête a lieu vendredi à huit heures du soir au parc. Le titre est : Une fête au parc. Le parc est près du musée. La fête a lieu vendredi.',p='ла мэри ано(н)с юн фэт пур ла фами́й. ла фэт а льё ва(н)дрэди а юи тёр дю суар о па́рк. лё титр э: юн фэт о па́рк. лё парк э прэ дю мюзэ́. ла фэт а льё ва(н)дрэди́.',vs=['mairie','annoncer','fete','famille','avoir-lieu','vendredi','huit','heure','soir','parc','titre','pres-de','musee'],av=['avoir-lieu','fete'],aq='Какое главное событие названо?',ac=['Доставка посылки','Праздник для семьи','Завершение работы'],aa=1,rq='Что подтверждено о событии?',rc=['Названы праздник, пятница и парк','Он уже обязательно закончился','Мэрия находится в музее'],ra=0,vq='Выберите реплику о проведении праздника в парке.',vc=[('La mairie annonce une fête.','ла мэри ано(н)с юн фэ́т'),('Le titre : Une fête.','лё титр: юн фэ́т'),('La fête a lieu au parc.','ла фэт а льё о па́рк')],va=2,vid=['avoir-lieu'],before='La fête ',bp='ла фэт',after=' au parc.',ap='о па́рк',fc=[('a lieu','а льё'),('annonce','ано́(н)с')],fa=0,full='La fête a lieu au parc.',fp='ла фэт а льё о па́рк.',fins='Восстановите проведение события, а не действие объявления.',tokens=[('La fête','ла фэт'),('a lieu','а льё'),('vendredi','ва(н)дрэди́'),('au parc.','о па́рк')],of='La fête a lieu vendredi au parc.',op='ла фэт а льё ва(н)дрэди о па́рк.',oi='Соберите: праздник состоится в пятницу в парке. Сначала событие, затем рамка проведения, день и место.',goals=[U]),
dict(n=3,desc='Различите источник, время и место проведения события.',warm=('A2-U12',3,'Q05'),intro='Повторяем La fête a lieu vendredi à huit heures du soir au musée. Событие — праздник; время — пятница, восемь вечера; место проведения — музей. Если La mairie annonce это сообщение, источник — мэрия, а не автоматически место события. Прежние дата или место из другого объявления не переносятся в новый текст.',iv=['avoir-lieu','mairie','annoncer'],notes=[('La fête a lieu vendredi à huit heures du soir au musée','ла фэт а льё ва(н)дрэди а юи тёр дю суар о мюзэ́'),('La mairie annonce','ла мэри ано́(н)с')],rows=[('mairie','Мэрия как источник'),('avoir-lieu','Проведение события'),('titre','Заголовок')],f='La mairie annonce une fête. La fête a lieu vendredi à huit heures du soir au musée. Le titre : Une fête au musée. Le musée est près du parc. La fête a lieu vendredi, pas mardi.',p='ла мэри ано(н)с юн фэ́т. ла фэт а льё ва(н)дрэди а юи тёр дю суар о мюзэ́. лё титр: юн фэт о мюзэ́. лё мюзэ э прэ дю па́рк. ла фэт а льё ва(н)дрэди, па марди́.',vs=['mairie','annoncer','fete','avoir-lieu','vendredi','huit','heure','soir','musee','titre','pres-de','parc','mardi'],av=['vendredi','huit','musee'],aq='Когда и где состоится праздник?',ac=['Во вторник в парке','В пятницу в восемь вечера в музее','В пятницу в полдень в мэрии'],aa=1,rq='Что является местом проведения, а что источником?',rc=['Музей — место; мэрия — источник','Мэрия — место; музей — источник','Парк — место; заголовок — источник'],ra=0,vq='Выберите время и место именно этого события.',vc=[('Vendredi à midi au parc.','ва(н)дрэди а миди о па́рк'),('Mardi à huit heures au musée.','марди а юи тёр о мюзэ́'),('Vendredi à huit heures du soir au musée.','ва(н)дрэди а юи тёр дю суар о мюзэ́')],va=2,vid=['vendredi','huit','musee'],before='La fête a lieu vendredi ',bp='ла фэт а льё ва(н)дрэди',after=' au musée.',ap='о мюзэ́',fc=[('à midi','а миди́'),('à huit heures du soir','а юи тёр дю суа́р')],fa=1,full='La fête a lieu vendredi à huit heures du soir au musée.',fp='ла фэт а льё ва(н)дрэди а юи тёр дю суар о мюзэ́.',fins='Сохраните названное вечернее время.',tokens=[('La fête','ла фэт'),('a lieu vendredi','а льё ва(н)дрэди́'),('à huit heures du soir','а юи тёр дю суа́р'),('au musée.','о мюзэ́')],of='La fête a lieu vendredi à huit heures du soir au musée.',op='ла фэт а льё ва(н)дрэди а юи тёр дю суар о мюзэ́.',oi='Соберите: праздник состоится в пятницу в восемь вечера в музее. Сначала событие, затем день, час и место.',goals=[A]),
dict(n=4,desc='Поймите причину изменения места в коротком общественном объявлении.',warm=('A2-U12',4,'Q05'),intro='Des travaux — строительные или ремонтные работы, не просто рабочая задача с сообщением. En raison de означает по причине. En raison de travaux dans le parc, la fête a lieu au musée — «Из-за работ в парке праздник состоится в музее». Причина, место работ и место праздника — три разных сведения. Все условия объявления вымышлены.',iv=['travaux','en-raison-de','avoir-lieu'],notes=[('Des travaux','дэ траво́'),('En raison de','а(н) рэзо(н) дё'),('En raison de travaux dans le parc, la fête a lieu au musée','а(н) рэзо(н) дё траво да(н) лё парк, ла фэт а льё о мюзэ́')],rows=[('travaux','Ремонтные работы'),('en-raison-de','По причине'),('avoir-lieu','Проведение события')],f='La mairie annonce une fête vendredi à huit heures du soir. En raison de travaux dans le parc, la fête a lieu au musée. Le titre : Une fête au musée. Les travaux sont dans le parc, pas au musée.',p='ла мэри ано(н)с юн фэт ва(н)дрэди а юи тёр дю суа́р. а(н) рэзо(н) дё траво да(н) лё парк, ла фэт а льё о мюзэ́. лё титр: юн фэт о мюзэ́. лэ траво со(н) да(н) лё парк, па о мюзэ́.',vs=['mairie','annoncer','fete','vendredi','huit','heure','soir','en-raison-de','travaux','parc','avoir-lieu','musee','titre'],av=['en-raison-de','travaux','musee'],aq='Почему праздник состоится в музее?',ac=['Из-за работ в парке','Из-за оплаты курса','Потому что сообщение уже отправлено'],aa=0,rq='Где работы и где праздник?',rc=['Работы и праздник в музее','Работы в музее; праздник в парке','Работы в парке; праздник в музее'],ra=2,vq='Выберите указанную причину.',vc=[('En raison de travaux.','а(н) рэзо(н) дё траво́'),('D’ici vendredi.','диси ва(н)дрэди́'),('Depuis un mois.','дёпюи э(н) муа́')],va=0,vid=['en-raison-de','travaux'],before='En raison de ',bp='а(н) рэзо(н) дё',after=', la fête a lieu au musée.',ap='ла фэт а льё о мюзэ́',fc=[('travaux','траво́'),('paiement','пэма́(н)')],fa=0,full='En raison de travaux, la fête a lieu au musée.',fp='а(н) рэзо(н) дё траво, ла фэт а льё о мюзэ́.',fins='Сохраните причину, названную в объявлении.',tokens=[('En raison de travaux','а(н) рэзо(н) дё траво́'),('dans le parc,','да(н) лё па́рк'),('la fête a lieu','ла фэт а льё'),('au musée.','о мюзэ́')],of='En raison de travaux dans le parc, la fête a lieu au musée.',op='а(н) рэзо(н) дё траво да(н) лё парк, ла фэт а льё о мюзэ́.',oi='Соберите: из-за работ в парке праздник состоится в музее. Сначала причина с местом работ, затем событие с местом проведения.',goals=[A])]
for d in DATA:
 s=start(d['n'],d['desc'],[])
 if d['warm']:warm(s,*d['warm'])
 else:
  t=copy.deepcopy(S[0]['tasks'][4]);t['id']='Q01';s['tasks'].append(t)
 intro(s,2,d['intro'],d['iv'],d['notes'])
 pairs(s,d['goals'],d['rows'],'Смыслы карточек различены.','Сохраните значение каждого выражения и его роль в сообщении.')
 listen(s,d['goals'],d['vs'],d['av'],d['aq'],d['f'],d['p'],d['ac'],d['aa'],'Выбран ответ по явно названным сведениям.','Правильный ответ: '+d['ac'][d['aa']]+'. Не переносите сведения другого события.')
 choose(s,'vocabulary',d['goals'],list(dict.fromkeys(d['vs']+({1:[],2:[],3:['midi'],4:['dici-deadline','depuis','un','mois']}[d['n']]))),d['vid'],d['vq'],ru('Сохраните сведения этого сообщения.'),[fr(f,p) for f,p in d['vc']],d['va'],'Выбрано выражение с нужным смыслом.','Подходящая реплика: '+d['vc'][d['va']][0]+'. Сохраните нужную роль сведений.')
 read(s,d['goals'],d['vs'],{1:['titre','fete','parc'],2:['avoir-lieu','fete','vendredi','parc'],3:['mairie','musee'],4:['travaux','parc','musee']}[d['n']],d['rq'],d['f'],d['p'],d['rc'],d['ra'],'Ответ сохраняет событие и явно названные условия.','Правильный смысл: '+d['rc'][d['ra']]+'. Не смешивайте источник, причину, место и время.')
 fill(s,d['goals'],list(dict.fromkeys(d['vs']+({1:[],2:[],3:['midi'],4:['paiement']}[d['n']]))),[],d['fins'],d['before'],d['bp'],d['after'],d['ap'],[fr(f,p) for f,p in d['fc']],d['fa'],d['full'],d['fp'],'Восстановлена фраза по условию.','Нужная фраза: '+d['full'])
 order(s,d['goals'],d['vs'],[],d['oi'],d['tokens'],d['of'],d['op'],'Сведения идут в заданном порядке.','Сохраните событие и соответствующие ему уточнения.')
 page(s,[('Главная информация',[d['intro']],[ex(d['of'],{1:'Мэрия объявляет праздник в парке.',2:'Праздник состоится в пятницу в парке.',3:'Праздник состоится в пятницу в восемь вечера в музее.',4:'Из-за работ в парке праздник состоится в музее.'}[d['n']],d['op'])]),('Проверить детали',['Источник, событие, время, место и причина читаются отдельно. Не добавляйте обстоятельств, не названных в этом вымышленном сообщении.'],[ex(d['full'],{1:'Мэрия объявляет праздник.',2:'Праздник состоится в парке.',3:'Праздник состоится в пятницу в восемь вечера в музее.',4:'Из-за работ праздник состоится в музее.'}[d['n']],d['fp'])])])
s=start(5,'Проверьте выборку праздничных, торговых, учебных, рабочих и общественных сообщений без нового материала.',[])
for u,n,q in [('A2-U11',5,'Q04'),('A2-U12',5,'Q02'),('A2-U13',5,'Q04')]:
 warm(s,u,n,q);s['tasks'][-1]['id']=f'Q{len(s["tasks"]):02}'
d=DATA[3]
read(s,[U],d['vs'],['mairie','annoncer','fete'],'Каковы источник и главное событие сообщения?',d['f'],d['p'],['Источник — музей, событие — ремонт мэрии','Источник — мэрия, событие — праздник','Источник — парк, событие — доставка'],1,'Мэрия объявляет праздник.','Ремонт объясняет место праздника; музей не назван источником.')
phrase_pairs(s,['A2.U14.APPLY'],[('Je suis en train d’écrire un message.','жё сюи а(н) трэ(н) дэкрир э(н) мэса́ж','Процесс письма сейчас',['etre-en-train-de','ecrire','message'],['etre-en-train-de']),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш','Недавнее завершение задачи',['venir-de','terminer','tache-travail'],['venir-de']),('Aidez-moi, s’il vous plaît.','эдэ муа, силь ву плэ́','Просьба о помощи',['aidez-moi','sil-vous-plait'],['aidez-moi'])],'Различены процесс, результат и просьба.','Ни желание, ни просьба не подтверждают выполнения.')
phrase_pairs(s,[A],[('En raison de travaux','а(н) рэзо(н) дё траво́','Причина: ремонтные работы',['en-raison-de','travaux'],['en-raison-de','travaux']),('Vendredi à huit heures du soir','ва(н)дрэди а юи тёр дю суа́р','Время: пятница, восемь вечера',['vendredi','huit','heure','soir'],['vendredi','huit']),('Au musée','о мюзэ́','Место: в музее',['musee'],['musee'])],'Причина, время и место различены.','Не превращайте место работ в место проведения события.')
grammar(fill(s,['A2.U05.APPLY'],['demain','si-condition','jai-possession','billet','prendre-transport','train','midi','pendant-duree'],['si-condition'],'Восстановите изученное условие поездки: если будет билет.','Demain, ','дёмэ(н)',' j’ai le billet, je prendrai le train de midi.','жэ лё бийэ, жё пра(н)дрэ лё трэ(н) дё миди́',[fr('pendant','па(н)да́(н)'),fr('si','си')],1,'Demain, si j’ai le billet, je prendrai le train de midi.','дёмэ(н), си жэ лё бийэ, жё пра(н)дрэ лё трэ(н) дё миди́.','Наличие билета — условие поездки.','Предлог длительности не восстанавливает эту условную рамку.'))
order(s,[A],['fete','avoir-lieu','vendredi','huit','heure','soir','musee'],[],'Соберите: праздник состоится в пятницу в восемь вечера в музее. Сначала событие, затем день, час и место.',[('La fête','ла фэт'),('a lieu vendredi','а льё ва(н)дрэди́'),('à huit heures du soir','а юи тёр дю суа́р'),('au musée.','о мюзэ́')],'La fête a lieu vendredi à huit heures du soir au musée.','ла фэт а льё ва(н)дрэди а юи тёр дю суар о мюзэ́.','Время и место сохранены.','Не переносите место или время из другого сообщения.')
page(s,[('Читать по ролям сведений',['Источник сообщает о событии. Место работ может отличаться от места события; причина не является временем. Контроль использует только изученный материал.'],[ex('La mairie annonce une fête.','Мэрия объявляет праздник.','ла мэри ано(н)с юн фэ́т')]),('Выборка прежних тем',['Повторяются благодарность, доставка, оплата обучения, рабочий процесс и результат, объявление и знакомое условие поездки. Восемь заданий не являются исчерпывающей оценкой всех умений блока.'],[ex('La fête a lieu au musée.','Праздник состоится в музее.','ла фэт а льё о мюзэ́')])])

for s,d in zip(S[:4],DATA):
 s['tasks'][3]['feedback']['pronunciation_notes']=[dict(fr=d['vc'][d['va']][0],transcription_ru=d['vc'][d['va']][1])]
 s['tasks'][5]['feedback']['pronunciation_notes']=[dict(fr=d['full'],transcription_ru=d['fp'])]
 s['public_page']['explanation_blocks'][0]['pronunciation_notes']=copy.deepcopy(s['introductions'][0]['pronunciation_notes'])

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
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Короткие новости и объявления: заголовок, источник, событие, место, время и причина; контроль изученного блока.',learning_outcomes=['Понимать заголовок и главное событие.','Отличать источник от места проведения.','Понимать время и место события.','Различать причину, место работ и место праздника.','Применять изученные модели в контрольной выборке сообщений.'])
P=dict(schema_version=1,package_id='programme-a2-u15-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U15.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U15 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=plan['sessions'][n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('annoncer','Отличать объявление от самого события.'),('avoir-lieu','Сохранять место и время проведения.'),('en-raison-de','Вернуть явно названную причину.'),('travaux','Не подменять ремонтные работы обычной рабочей задачей.'),('titre','Отличать заголовок от источника.'),('mairie','Сохранять источник отдельно от места события.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить внешнюю зависимость A2-U14-L05 в целевой базе.','Редактору проверить естественность заголовков, объявлений и объяснений причин; прослушать французскую озвучку.','Выполнить предпросмотр и пробное прохождение; измерить время аудио и занятий. Фактически это не выполнялось.'],progress=dict(current_unit='A2-U15',prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='A2-U16',next_unit_title='Природа и отдых'),scope_notes=['Один полный пакет пяти занятий. Первая тема после объединённого A2-U14; его файл и версии не менялись.','Новые значения распределены как три, одно, ноль, два, ноль. В L05 нет новых обязательных слов и моделей; вид checkpoint сохранён по плану.','Announce и avoir lieu различаются как объявление и проведение; выбранные формы annonce / a lieu объяснены до проверки.','Источник, заголовок, событие, время, место и причина читаются раздельно. Место работ не равно месту праздника, источник не обязан быть местом проведения.','Travaux означает строительные или ремонтные работы, не прежнее travail как работа в распорядке.','В L01 повторён срок задачи из A2-U14. В L03 повторена доставка из A2-U12, в L04 — возврат и будущий план возмещения из той же темы. Прежние объекты карточек сохраняются.','Контроль содержит два listening, два reading, два vocabulary, grammar и sentence_building. Представлены U11–U15 и условие поездки U05. Это выборка, не исчерпывающая оценка каждого умения.','Все новости, источники, работы и изменения места вымышлены. Они не описывают действующие события, муниципальные распоряжения или реальные ограничения посещения.','Озвучка только браузером: fr-FR, asset_id=null. Нет внешних аудиосервисов, файлов, обязательного текста или микрофона.','Импорт, публикация, отправка сообщений, прослушивание и сайт-прохождение не выполнялись. Принятие пользователем не подтверждено.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='La mairie annonce; источник и заголовок.'),dict(session_id=S[1]['id'],material='La fête a lieu; проведение события.'),dict(session_id=S[2]['id'],material='Повторение дня, часа и места отдельно от источника.'),dict(session_id=S[3]['id'],material='En raison de travaux; причина и два разных места.')]
(OUT/'A2-U15.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
