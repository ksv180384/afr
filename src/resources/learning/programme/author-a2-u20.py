"""Author A2-U20 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/A2.plan.json').read_text())['level']
plan=level['units'][19]
W={}
for source in [OUT/'A2-U19.v1.json',OUT/'A2-U18.v1.json',OUT/'A2-U17.v1.json',OUT/'A2-U16.v1.json',OUT/'A2-U15.v1.json',OUT/'A2-U14.v1.json',OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='A2.U20.UNDERSTAND';A='A2.U20.APPLY'
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
word('experience','fr:experience:personal-experience','une expérience','личный опыт, пережитое событие','юн экспэрья́(н)с',1,ex('Une expérience : j’ai visité le musée.','Личный опыт: я посетил музей.','юн экспэрья(н)с: жэ визитэ лё мюзэ́'),['Личный опыт','Будущий план','Способ оплаты'],0,'Назовите уже пережитое событие как опыт.',[('Une expérience.','юн экспэрья́(н)с'),('Un tarif.','э(н) тари́ф'),('Une annulation.','юн анюласьо́(н)')],0)
word('prochain','fr:prochain:next-in-sequence','prochain','следующий в последовательности','прошэ́(н)',2,ex('Le prochain train part à midi.','Следующий поезд отправляется в полдень.','лё прошэ(н) трэ(н) пар а миди́'),['Предыдущий','Следующий','Отменённый'],1,'Выберите сообщение о следующем поезде.',[('Le vol est annulé.','лё воль э танюлэ́'),('Le prochain train part à midi.','лё прошэ(н) трэ(н) пар а миди́'),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш')],1)
W['experience']['review_items'][1]['feedback']=fb('Назван личный опыт.','Тариф и отмена — другие понятия.')
W['prochain']['review_items'][1]['feedback']=fb('Назван следующий поезд и его отправление.','Отмена рейса и завершение задачи не обозначают следующий поезд.')

def read(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def oldtask(s,u,n,q):
 old=json.loads((OUT/f'{u}.v1.json').read_text());t=copy.deepcopy(next(t for t in old['sessions'][n-1]['tasks'] if t['id']==q));t['id']=f'Q{len(s["tasks"])+1:02}';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))
DATA=[dict(n=1,desc='Различите пережитое событие поездки и желание следующей поездки.',warm=('A2-U19',5,'Q05'),intro='Une expérience здесь — личный опыт, пережитое событие, не научный эксперимент. Une expérience : j’ai visité le musée — «Личный опыт: я посетил музей». Повторяем известные формы: J’ai visité относится к прошлому, Je voudrais visiter — к желанию. La semaine prochaine задаёт будущий период и не подтверждает, что посещение уже состоялось.',iv=['experience','visiter','la-semaine-prochaine'],notes=[('Une expérience','юн экспэрья́(н)с'),('Une expérience : j’ai visité le musée','юн экспэрья(н)с: жэ визитэ лё мюзэ́'),('J’ai visité','жэ визитэ́'),('Je voudrais visiter','жё вудрэ визитэ́'),('La semaine prochaine','ла сёмэн прошэ́н')],rows=[('experience','Личный опыт'),('visiter','Посещать место'),('la-semaine-prochaine','На следующей неделе')],f='J’ai visité le musée avec mon frère. C’est une expérience. Le musée est près du parc. La semaine prochaine, je voudrais visiter le musée avec ma sœur. Je suis disponible vendredi matin. Je voudrais choisir le train de midi.',p='жэ визитэ лё мюзэ авэк мо(н) фрэ́р. сэ тюн экспэрья́(н)с. лё мюзэ э прэ дю па́рк. ла сёмэн прошэн, жё вудрэ визитэ лё мюзэ авэк ма сё́р. жё сюи диспонибль ва(н)дрэди матэ́(н). жё вудрэ шуазир лё трэ(н) дё миди́.',vs=['avoir-pc','visiter','musee','frere','experience','pres-de','parc','la-semaine-prochaine','je-voudrais','soeur','disponible','vendredi','matin','choisir','train','midi'],av=['avoir-pc','visiter','frere'],aq='Какое посещение уже состоялось?',ac=['Музей с братом','Музей с сестрой на следующей неделе','Посадка на полуденный поезд'],aa=0,rq='Что относится к желанию на следующую неделю?',rc=['Посещение уже состоялось с сестрой','Автор хотел бы посетить музей с сестрой','Все будущие планы выполнены'],ra=1,rav=['la-semaine-prochaine','je-voudrais','visiter','soeur'],vq='Назовите личный опыт как существительное.',vc=[('Un tarif.','э(н) тари́ф'),('Un délai.','э(н) дэлэ́'),('Une expérience.','юн экспэрья́(н)с')],va=2,extra=['tarif','delai'],vid=['experience'],before='La semaine prochaine, je voudrais ',bp='ла сёмэн прошэн, жё вудрэ',after=' le musée.',ap='лё мюзэ́',fc=[('visiter','визитэ́'),('visité','визитэ́')],fa=0,full='La semaine prochaine, je voudrais visiter le musée.',fp='ла сёмэн прошэн, жё вудрэ визитэ лё мюзэ́.',fins='Повторите известную рамку желания с инфинитивом.',tokens=[('J’ai visité','жэ визитэ́'),('le musée','лё мюзэ́'),('avec','авэк'),('mon frère.','мо(н) фрэ́р')],of='J’ai visité le musée avec mon frère.',op='жэ визитэ лё мюзэ авэк мо(н) фрэ́р.',translation='Я посетил музей с братом.',oi='Соберите рассказ о прошлом: я посетил музей с братом. Сначала действие в прошлом, затем место и участник.',goals=[U]),
dict(n=2,desc='Объясните выбор следующего поезда по явно названным времени и доступности.',warm=None,intro='Prochain в Le prochain train означает следующий поезд. Знакомая la semaine prochaine остаётся отдельным готовым выражением следующей недели; здесь используем прилагательное с поездом в мужском роде. Повторяем parce que: Je voudrais choisir ce train parce que je suis disponible à midi. Причина выбора — названное свободное время; не добавляем сведения о реальном расписании.',iv=['prochain','choisir','parce-que'],notes=[('Prochain','прошэ́(н)'),('Le prochain train','лё прошэ(н) трэ́(н)'),('la semaine prochaine','ла сёмэн прошэ́н'),('parce que','парс кё'),('Je voudrais choisir ce train parce que je suis disponible à midi','жё вудрэ шуазир сё трэ(н) парс кё жё сюи диспонибль а миди́')],rows=[('prochain','Следующий'),('choisir','Выбирать вариант'),('parce-que','Потому что: причина')],f='Le prochain train part à midi. Je voudrais choisir ce train parce que je suis disponible à midi. Le tarif est de dix euros. L’autre tarif est plus cher. Je voudrais payer par carte.',p='лё прошэ(н) трэ(н) пар а миди́. жё вудрэ шуазир сё трэ(н) парс кё жё сюи диспонибль а миди́. лё тариф э дё ди зёро́. лотр тариф э плю шэ́р. жё вудрэ пэйе пар ка́рт.',vs=['prochain','train','partir','midi','je-voudrais','choisir','parce-que','disponible','tarif','dix','euro','autre','plus-comparatif','cher','payer','par-carte'],av=['parce-que','disponible','midi'],aq='Какая причина выбора прямо названа?',ac=['Автор гарантирует отсутствие задержек','Автор свободен в полдень','Все тарифы одинаковы'],aa=1,rq='Каково время следующего поезда в этом сообщении?',rc=['Полдень','Восемь утра','Пятница вечером без указанного часа'],ra=0,rav=['prochain','partir','midi'],vq='Выберите названную причину, а не иной факт.',vc=[('Le tarif est de dix euros.','лё тариф э дё ди зёро́'),('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт'),('Parce que je suis disponible à midi.','парс кё жё сюи диспонибль а миди́')],va=2,extra=[],vid=['parce-que','disponible'],before='Je voudrais choisir ce train ',bp='жё вудрэ шуазир сё трэ(н)',after=' je suis disponible à midi.',ap='жё сюи диспонибль а миди́',fc=[('parce que','парс кё'),('mais','мэ')],fa=0,full='Je voudrais choisir ce train parce que je suis disponible à midi.',fp='жё вудрэ шуазир сё трэ(н) парс кё жё сюи диспонибль а миди́.',fins='Сохраните объяснение причины.',tokens=[('Le prochain train','лё прошэ(н) трэ́(н)'),('part','пар'),('à','а'),('midi.','миди́')],of='Le prochain train part à midi.',op='лё прошэ(н) трэ(н) пар а миди́.',translation='Следующий поезд отправляется в полдень.',oi='Соберите: следующий поезд отправляется в полдень. Сначала поезд, затем действие и время.',goals=[U]),
dict(n=3,desc='Выберите просьбу о записи, уточните тариф и способ оплаты услуги.',warm=('A2-U17',3,'Q05'),intro='Повторяем Je voudrais prendre rendez-vous и Pourriez-vous préciser le tarif ? Уточнение цены, условие включения услуги и способ оплаты — разные детали. Форма просьбы о записи не подтверждает выполненную услугу или оплату. Все условия предложения вымышлены.',iv=['prendre-rendez-vous','pourriez-vous','tarif'],notes=[('Je voudrais prendre rendez-vous','жё вудрэ пра(н)др ра(н)дэ ву́'),('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф')],rows=[('prendre-rendez-vous','Записываться на приём'),('tarif','Цена указанной услуги'),('par-carte','Оплата картой')],f='— Je voudrais prendre rendez-vous vendredi à dix heures pour une coupe. Pourriez-vous préciser le tarif ? — Le tarif est de quinze euros. Le shampooing n’est pas inclus. — Je voudrais payer par carte.',p='— жё вудрэ пра(н)др ра(н)дэ ву ва(н)дрэди а ди зёр пур юн ку́п. пурье ву прэсизэ лё тари́ф? — лё тариф э дё кэ(н) зёро́. лё ша(н)пуэ(н) нэ па э(н)клю́. — жё вудрэ пэйе пар ка́рт.',vs=['je-voudrais','prendre-rendez-vous','vendredi','dix','heure','coupe','pourriez-vous','preciser','tarif','quinze','euro','shampooing','inclus','payer','par-carte'],av=['quinze','tarif','shampooing','inclus'],aq='Какие цена и условие названы?',ac=['Пятнадцать евро; мытьё не включено','Десять евро; мытьё включено','Пятнадцать евро; всё оплачено'],aa=0,rq='Что автор выбирает как способ оплаты?',rc=['Только наличные','Оплата уже прошла','Хотел бы оплатить картой'],ra=2,rav=['je-voudrais','payer','par-carte'],vq='Выберите просьбу уточнить стоимость услуги.',vc=[('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф'),('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт'),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш')],va=0,extra=['venir-de','terminer','tache-travail'],vid=['preciser','tarif'],before='Je voudrais payer ',bp='жё вудрэ пэйе',after='.',ap='',fc=[('par carte','пар ка́рт'),('en espèces','а(н) нэспэ́с')],fa=0,full='Je voudrais payer par carte.',fp='жё вудрэ пэйе пар ка́рт.',fins='Сохраните прямо выбранный способ оплаты картой.',tokens=[('Je voudrais','жё вудрэ́'),('prendre rendez-vous','пра(н)др ра(н)дэ ву́'),('vendredi','ва(н)дрэди́'),('à dix heures.','а ди зё́р')],of='Je voudrais prendre rendez-vous vendredi à dix heures.',op='жё вудрэ пра(н)др ра(н)дэ ву ва(н)дрэди а ди зё́р.',translation='Мне бы хотелось записаться на пятницу в десять часов.',oi='Соберите просьбу: мне бы хотелось записаться на пятницу в десять. Сначала желание, затем запись, день и час.',goals=[A]),
dict(n=4,desc='Напишите план через сборку, отличая будущую поездку от совершённой и вопрос от ответа.',warm=('A2-U17',4,'Q05'),intro='Повторяем La semaine prochaine, je vais visiter le musée и J’ai l’intention de prendre le train de midi: это планы, не совершённые поездки. J’espère que выражает надежду, а Est-ce que tu es disponible ? задаёт вопрос адресату. Планы и вопрос не подтверждают согласие адресата. Вся грамматика уже введена ранее.',iv=['aller-futur','avoir-intention-de','question-disponibilite-tu'],notes=[('La semaine prochaine, je vais visiter le musée','ла сёмэн прошэн, жё вэ визитэ лё мюзэ́'),('J’ai l’intention de prendre le train de midi','жэ лэ(н)та(н)сьо(н) дё пра(н)др лё трэ(н) дё миди́'),('J’espère que','жэспэр кё'),('Est-ce que tu es disponible ?','эс кё тю э диспони́бль')],rows=[('la-semaine-prochaine','На следующей неделе'),('avoir-intention-de','Намереваться'),('question-disponibilite-tu','Вопрос о времени адресата')],f='Salut ! La semaine prochaine, je vais visiter le musée avec ma sœur. J’ai l’intention de prendre le train de midi. J’espère que tu es disponible vendredi. Est-ce que tu es disponible vendredi soir ? À bientôt ! Amicalement.',p='салю́! ла сёмэн прошэн, жё вэ визитэ лё мюзэ авэк ма сё́р. жэ лэ(н)та(н)сьо(н) дё пра(н)др лё трэ(н) дё миди́. жэспэр кё тю э диспонибль ва(н)дрэди́. эс кё тю э диспонибль ва(н)дрэди суа́р? а бьэ(н)то́! амикальма́(н).',vs=['salut','la-semaine-prochaine','aller-futur','visiter','musee','soeur','avoir-intention-de','prendre-transport','train','midi','j-espere-que','disponible','vendredi','question-disponibilite-tu','soir','a-bientot','amicalement'],av=['aller-futur','la-semaine-prochaine','visiter','soeur'],aq='Какой план поездки назван?',ac=['Музей уже посещён с братом','Посетить музей с сестрой на следующей неделе','Рейс уже отменён'],aa=1,rq='Что известно об ответе адресата?',rc=['Даны надежда и вопрос; ответ не приведён','Адресат уже подтвердил поездку','Адресат уже отказался'],ra=0,rav=['j-espere-que','question-disponibilite-tu'],vq='Выберите сообщение о намерении взять полуденный поезд.',vc=[('J’ai visité le musée.','жэ визитэ лё мюзэ́'),('Est-ce que tu es disponible ?','эс кё тю э диспони́бль'),('J’ai l’intention de prendre le train de midi.','жэ лэ(н)та(н)сьо(н) дё пра(н)др лё трэ(н) дё миди́')],va=2,extra=['avoir-pc'],vid=['avoir-intention-de','prendre-transport'],before='La semaine prochaine, je ',bp='ла сёмэн прошэн, жё',after=' le musée.',ap='лё мюзэ́',fc=[('vais visiter','вэ визитэ́'),('viens de visiter','вьэ(н) дё визитэ́')],fa=0,full='La semaine prochaine, je vais visiter le musée.',fp='ла сёмэн прошэн, жё вэ визитэ лё мюзэ́.',fins='Сохраните будущий план, а не недавний результат.',tokens=[('La semaine prochaine,','ла сёмэн прошэ́н'),('je vais','жё вэ'),('visiter','визитэ́'),('le musée.','лё мюзэ́')],of='La semaine prochaine, je vais visiter le musée.',op='ла сёмэн прошэн, жё вэ визитэ лё мюзэ́.',translation='На следующей неделе я собираюсь посетить музей.',oi='Соберите будущий план: на следующей неделе я собираюсь посетить музей. Сначала период, затем план и место.',goals=[A])]
for d in DATA:
 s=start(d['n'],d['desc'],[])
 if d['warm']:oldtask(s,*d['warm'])
 else:
  t=copy.deepcopy(S[0]['tasks'][4]);t['id']='Q01';s['tasks'].append(t)
 intro(s,2,d['intro'],d['iv'],d['notes'])
 pairs(s,d['goals'],d['rows'],'Значения и функции различены.','Не смешивайте опыт, выбор, просьбу и план.')
 listen(s,d['goals'],d['vs'],d['av'],d['aq'],d['f'],d['p'],d['ac'],d['aa'],'Ответ сохраняет сведения сообщения.','Правильный смысл: '+d['ac'][d['aa']]+'. Не превращайте план в результат.')
 choose(s,'vocabulary',d['goals'],list(dict.fromkeys(d['vs']+d['extra'])),d['vid'],d['vq'],ru('Выберите нужный смысл.'),[fr(f,p) for f,p in d['vc']],d['va'],'Выбран нужный смысл.','Подходящее выражение: '+d['vc'][d['va']][0])
 read(s,d['goals'],d['vs'],d['rav'],d['rq'],d['f'],d['p'],d['rc'],d['ra'],'Вывод соответствует прямо сказанному.','Правильный смысл: '+d['rc'][d['ra']]+'. Не добавляйте неуказанного ответа или действия.')
 grammar(fill(s,d['goals'],list(dict.fromkeys(d['vs']+['mais','en-especes','venir-de'])),[],d['fins'],d['before'],d['bp'],d['after'],d['ap'],[fr(f,p) for f,p in d['fc']],d['fa'],d['full'],d['fp'],'Известная модель восстановлена.','Правильная фраза: '+d['full']))
 order(s,d['goals'],d['vs'],[],d['oi'],d['tokens'],d['of'],d['op'],'Порядок смысловых частей сохранён.','Следуйте заданному порядку, сохраняя время и функцию сообщения.')
 page(s,[('Повторяемая модель',[d['intro']],[ex(d['of'],d['translation'],d['op'])]),('Границы вывода',['Все события, тарифы и планы вымышлены для языка. Опыт, желание, вопрос, план и выполненное действие не подменяют друг друга.'],[])])
 s['tasks'][3]['feedback']['pronunciation_notes']=[dict(fr=d['vc'][d['va']][0],transcription_ru=d['vc'][d['va']][1])]
 s['tasks'][5]['feedback']['pronunciation_notes']=[dict(fr=d['full'],transcription_ru=d['fp'])]
 s['public_page']['explanation_blocks'][0]['pronunciation_notes']=[dict(fr=f,transcription_ru=p) for f,p in d['notes']]
s=start(5,'Проверьте выборку бытовых ситуаций всего уровня: поездка, рассказ, услуга, замены, проблема и планы; нового материала нет.',[])
oldtask(s,'A2-U02',5,'Q01')
oldtask(s,'A2-U04',5,'Q02')
oldtask(s,'A2-U06',5,'Q04')
d=DATA[0]
read(s,[U],d['vs']+['prochain'],['avoir-pc','visiter','frere','la-semaine-prochaine','je-voudrais','soeur'],'Как различаются состоявшееся посещение и будущий замысел?',d['f'].replace('le train de midi','le prochain train'),d['p'].replace('лё трэ(н) дё миди́','лё прошэ(н) трэ́(н)'),['Музей уже посещён с сестрой; брат только планирует','Обе поездки уже завершены','Музей посещён с братом; посещение с сестрой на следующей неделе — желание'],2,'Прошлое посещение и будущее желание относятся к разным участникам.','Не переносите результат из прошлого на следующий план.')
oldtask(s,'A2-U12',5,'Q03')
oldtask(s,'A2-U19',5,'Q06')
grammar(fill(s,['A2.U05.APPLY'],['demain','si-condition','jai-possession','billet','prendre-transport','train','midi','pendant-duree'],['si-condition'],'Восстановите знакомое условие будущей поездки: если будет билет.','Demain, ','дёмэ(н)',' j’ai le billet, je prendrai le train de midi.','жэ лё бийэ, жё пра(н)дрэ лё трэ(н) дё миди́',[fr('pendant','па(н)да́(н)'),fr('si','си')],1,'Demain, si j’ai le billet, je prendrai le train de midi.','дёмэ(н), си жэ лё бийэ, жё пра(н)дрэ лё трэ(н) дё миди́.','Наличие билета задаёт условие поездки.','Длительность не заменяет условие; наличие билета ещё не утверждено как факт.'))
order(s,[A,'A2.U18.APPLY'],['salut','la-semaine-prochaine','aller-futur','visiter','musee','avoir-intention-de','prendre-transport','train','midi','question-disponibilite-tu','disponible','vendredi','soir','a-bientot','amicalement'],[],'Соберите письмо о планах: приветствие; на следующей неделе собираюсь посетить музей; намерен взять полуденный поезд; вопрос, свободен ли адресат в пятницу вечером; до скорого; с дружеским приветом. Следуйте этому порядку.',[('Salut !','салю́'),('La semaine prochaine, je vais visiter le musée.','ла сёмэн прошэн, жё вэ визитэ лё мюзэ́'),('J’ai l’intention de prendre le train de midi.','жэ лэ(н)та(н)сьо(н) дё пра(н)др лё трэ(н) дё миди́'),('Est-ce que tu es disponible vendredi soir ?','эс кё тю э диспонибль ва(н)дрэди суа́р'),('À bientôt !','а бьэ(н)то́'),('Amicalement.','амикальма́(н)')],'Salut ! La semaine prochaine, je vais visiter le musée. J’ai l’intention de prendre le train de midi. Est-ce que tu es disponible vendredi soir ? À bientôt ! Amicalement.','салю́! ла сёмэн прошэн, жё вэ визитэ лё мюзэ́. жэ лэ(н)та(н)сьо(н) дё пра(н)др лё трэ(н) дё миди́. эс кё тю э диспонибль ва(н)дрэди суа́р? а бьэ(н)то́! амикальма́(н).','Письмо сохраняет планы, вопрос и дружеское завершение.','Не превращайте планы в прошлые события и оставьте завершающие формулы после вопроса.')
page(s,[('Выборка изученного',['В контроле встречаются транспорт, прошлый рассказ, услуга, замены предметов и адресата, просьба исправить и планы. Новых обязательных слов и грамматики нет.'],[ex('Je voudrais choisir ce train.','Мне бы хотелось выбрать этот поезд.','жё вудрэ шуазир сё трэ́(н)')]),('Не расширять результат',['Восемь заданий дают выборку материала, не исчерпывающую проверку всех целей уровня. Техническая проверка пакета и выполнение упражнений не заменяют свободную устную и письменную практику.'],[ex('La semaine prochaine, je vais visiter le musée.','На следующей неделе я собираюсь посетить музей.','ла сёмэн прошэн, жё вэ визитэ лё мюзэ́')])])

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
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Интеграция личного опыта поездки, основания выбора, услуги и будущего плана; итоговая выборка бытовых ситуаций уровня.',learning_outcomes=['Различать пережитое событие и будущий замысел поездки.','Объяснять выбор по явно названному основанию.','Уточнять условия услуги и способ оплаты.','Собирать личное письмо о планах с вопросом адресату.','Применять изученный материал в итоговой выборке бытовых ситуаций.'])
P=dict(schema_version=1,package_id='programme-a2-u20-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U20.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U20 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=plan['sessions'][n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('experience','Отличать личный опыт от будущего намерения.'),('choisir','Сохранять причину выбора, не добавляя неуказанных условий.'),('prochain','Отличать следующий вариант от уже совершённого события.'),('la-semaine-prochaine','Вернуть известный будущий период.'),('avoir-intention-de','Сохранять намерение отдельно от результата.'),('question-disponibilite-tu','Не превращать вопрос в согласие адресата.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить внешнюю зависимость A2-U19-L05 в целевой базе.','Редактору проверить естественность рассказа об опыте, причины выбора, диалога услуги и письма о планах; проверить репрезентативность контрольной выборки.','Прослушать браузерную озвучку, выполнить предпросмотр и прохождение; измерить время. Эти действия фактически не выполнялись.'],progress=dict(current_unit='A2-U20',prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='B1-U01',next_unit_title='Личный опыт'),scope_notes=['Один полный пакет пяти занятий; L05 — checkpoint по плану. Две новые лексические единицы распределены как одна, одна, ноль, ноль, ноль. В L05 нового обязательного материала и новой грамматики нет.','Expérience здесь личный опыт, не научный эксперимент. Старое choisir сохраняет ключ и полный объект.','Prochain показан в мужском роде в сочетании le prochain train; прежняя la semaine prochaine сохраняется как готовое выражение. Полная таблица согласования не вводится.','В L01 повторяется исправление ошибки из A2-U19. В L03 возвращается условие совета из A2-U17, в L04 — причина рекомендации оттуда же. Прежние карточки не изменены.','Опыт прошлого, желание, намерение, будущий план, вопрос и подтверждённый результат различаются. Запись и желаемый способ оплаты не подтверждают выполненную услугу или платёж.','Контроль содержит два listening, два reading, два vocabulary, grammar и sentence_building. Выбраны ранние и поздние бытовые темы: транспорт, фон и событие, услуга, местоимения, проблема и личное письмо о планах.','Восемь вопросов не проверяют исчерпывающе все сорок целей уровня. Накопленное покрытие всех ста занятий проверяется отдельно и не равно результату прохождения учеником.','Все поездки, цены, услуги и планы вымышлены для языка. Реальные поездки и услуги не рекомендуются; бронирования, платежи и сообщения не выполнялись.','Озвучка только браузером: fr-FR, asset_id=null. Нет аудиофайлов и внешних сервисов, обязательного микрофона или свободного ввода.','Завершение авторских материалов A2 не подтверждает весь CEFR: свободная речь и письмо требуют отдельной проверки. Импорт, публикация, прослушивание и сайт-прохождение не выполнялись; принятие пользователем не подтверждено.','Curricula не создаётся: по методике это отдельная задача после готовности уровня. Следующая тема B1-U01 не начата.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Повторение J’ai visité / Je voudrais visiter и следующей недели.'),dict(session_id=S[1]['id'],material='Следующий поезд; повторение причины выбора с parce que.'),dict(session_id=S[2]['id'],material='Повторение просьбы о записи, тарифа, включения услуги и оплаты.'),dict(session_id=S[3]['id'],material='Повторение ближайшего будущего, намерения, надежды и вопроса адресату.')]
D['checkpoint_task_map']=[dict(task_id=t['id'],skill=t['skill'],objective_ids=t['objective_ids']) for t in S[-1]['tasks']]
(OUT/'A2-U20.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
