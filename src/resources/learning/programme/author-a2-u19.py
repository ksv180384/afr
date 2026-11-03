"""Author A2-U19 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/A2.plan.json').read_text())['level']
plan=level['units'][18]
W={}
for source in [OUT/'A2-U18.v1.json',OUT/'A2-U17.v1.json',OUT/'A2-U16.v1.json',OUT/'A2-U15.v1.json',OUT/'A2-U14.v1.json',OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='A2.U19.UNDERSTAND';A='A2.U19.APPLY'
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
word('perdre','fr:perdre:lose-possession','perdre','терять вещь','пэ́рдр',1,ex('J’ai perdu mon sac.','Я потерял сумку.','жэ пэрдю мо(н) са́к'),['Терять вещь','Отменять событие','Оплачивать'],0,'Нужно сообщить, что сумка потеряна.',[('J’ai perdu mon sac.','жэ пэрдю мо(н) са́к'),('Je voudrais acheter un sac.','жё вудрэ аштэ э(н) са́к'),('Je suis disponible mardi.','жё сюи диспонибль марди́')],0)
word('ne-plus','fr:ne-plus:no-longer-state','ne … plus','больше не: изменение состояния','нё … плю',1,ex('Je n’ai plus mon sac.','У меня больше нет моей сумки.','жё нэ плю мо(н) са́к'),['Никогда не','Больше не','Более: сравнение'],1,'У вас больше нет своей сумки. Выберите сообщение о текущем изменившемся состоянии.',[('Je n’ai jamais perdu mon sac.','жё нэ жамэ пэрдю мо(н) са́к'),('Je n’ai plus mon sac.','жё нэ плю мо(н) са́к'),('J’ai mon sac.','жэ мо(н) са́к')],1)
word('signaler','fr:signaler:report-problem','signaler','сообщать о проблеме','синьялэ́',2,ex('Je voudrais signaler une erreur.','Мне бы хотелось сообщить об ошибке.','жё вудрэ синьялэ юн эрё́р'),['Праздновать','Сохранять тишину','Сообщать о проблеме'],2,'Сообщите о наличии ошибки.',[('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт'),('Je voudrais fêter mon anniversaire.','жё вудрэ фэтэ мо(н) нанивэрсэ́р'),('Je voudrais signaler une erreur.','жё вудрэ синьялэ юн эрё́р')],2)
word('erreur','fr:erreur:mistake','une erreur','ошибка','юн эрё́р',2,ex('Je voudrais signaler une erreur.','Мне бы хотелось сообщить об ошибке.','жё вудрэ синьялэ юн эрё́р'),['Ошибка','Доставка','Номер как идентификатор'],0,'Назовите ошибку, а не номер комнаты.',[('Une erreur.','юн эрё́р'),('Le numéro.','лё нюмэро́'),('Une livraison.','юн ливрэзо́(н)')],0)
word('vol-avion','fr:vol:flight','un vol','авиарейс','э(н) воль',3,ex('Le vol de mardi est annulé.','Рейс во вторник отменён.','лё воль дё марди э танюлэ́'),['Поезд','Авиарейс','Гостиничный номер'],1,'Назовите авиарейс, а не поезд.',[('Un train.','э(н) трэ́(н)'),('Un vol.','э(н) воль'),('Une chambre.','юн ша́(н)бр')],1)
word('annulation','fr:annulation:cancellation','une annulation','отмена события или рейса','юн анюласьо́(н)',3,ex('L’annulation du vol.','Отмена рейса.','ланюласьо(н) дю во́ль'),['Срок','Ошибка','Отмена'],2,'Назовите отмену рейса.',[('Le délai.','лё дэлэ́'),('Le numéro.','лё нюмэро́'),('L’annulation du vol.','ланюласьо(н) дю во́ль')],2)
word('annuler','fr:annuler:cancel-event','annuler','отменять событие или рейс','анюлэ́',3,ex('Le vol est annulé.','Рейс отменён.','лё воль э танюлэ́'),['Отменять','Оплачивать','Покупать'],0,'Нужно сообщить, что рейс отменён.',[('Le vol est annulé.','лё воль э танюлэ́'),('Le vol a lieu mardi.','лё воль а льё марди́'),('Je voudrais préciser le tarif.','жё вудрэ прэсизэ лё тари́ф')],0)
word('corriger','fr:corriger:correct-mistake','corriger','исправлять ошибку','корижэ́',4,ex('Pourriez-vous corriger cette erreur ?','Не могли бы вы исправить эту ошибку?','пурье ву корижэ сэт эрё́р'),['Сообщать о событии','Исправлять ошибку','Заканчивать прогулку'],1,'Попросите исправить ошибку, а не только сообщите о ней.',[('Je voudrais signaler une erreur.','жё вудрэ синьялэ юн эрё́р'),('Pourriez-vous corriger cette erreur ?','пурье ву корижэ сэт эрё́р'),('Je suis disponible vendredi.','жё сюи диспонибль ва(н)дрэди́')],1)
for w,g,b in [('perdre','Названа потеря сумки.','Покупка и свободное время не сообщают о потере.'),('ne-plus','Сообщено, что сейчас своей сумки больше нет.','Никогда не терял — отрицание прежнего опыта, а не текущее отсутствие; наличие сумки имеет другой смысл.'),('signaler','Сообщается о наличии проблемы.','Оплата и празднование не сообщают об ошибке.'),('erreur','Названа ошибка.','Номер и доставка — другие понятия.'),('vol-avion','Назван авиарейс.','Поезд и номер гостиницы не являются авиарейсом.'),('annulation','Названа отмена рейса.','Срок и идентификатор — другие сведения.'),('annuler','Рейс прямо назван отменённым.','Проведение рейса и вопрос о тарифе не сохраняют отмену.'),('corriger','Сформулирована просьба исправить ошибку.','Сообщение о проблеме не равно просьбе исправить; доступность — другое сведение.')]:W[w]['review_items'][1]['feedback']=fb(g,b)
W['ne-plus']['review_items'][0]['prompt']=fr(W['ne-plus']['example']['fr'],W['ne-plus']['example']['transcription_ru'])

def read(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def warm(s,u,n,q):
 old=json.loads((OUT/f'{u}.v1.json').read_text());t=copy.deepcopy(next(t for t in old['sessions'][n-1]['tasks'] if t['id']==q));t['id']='Q01';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))
DATA=[dict(n=1,desc='Поймите потерю вещи и отличите больше нет от никогда не терял.',warm=('A2-U18',2,'Q05'),intro='Perdre — терять вещь. Выбранная форма прошлого: J’ai perdu mon sac — «Я потерял сумку». Ne … plus в модели Je n’ai plus mon sac означает «У меня больше нет моей сумки». Это не сравнительное plus. Повторяем jamais: Je n’ai jamais perdu mon sac — «Я никогда не терял сумку». Текущее отсутствие и отрицание прежнего опыта — разные сведения.',iv=['perdre','ne-plus','jamais-experience'],notes=[('Perdre','пэ́рдр'),('J’ai perdu mon sac','жэ пэрдю мо(н) са́к'),('Ne … plus','нё … плю'),('Je n’ai plus mon sac','жё нэ плю мо(н) са́к'),('plus','плю'),('jamais','жамэ́'),('Je n’ai jamais perdu mon sac','жё нэ жамэ пэрдю мо(н) са́к')],rows=[('perdre','Терять вещь'),('ne-plus','Больше не: изменение состояния'),('jamais-experience','Никогда: отрицание опыта')],f='J’ai perdu mon sac à la gare. Je n’ai plus mon sac. Il est petit. Je voudrais demander de l’aide pour mon sac. Je suis disponible mardi matin. Je voudrais préciser l’heure.',p='жэ пэрдю мо(н) сак а ла га́р. жё нэ плю мо(н) са́к. иль э пёти́. жё вудрэ дёма(н)дэ дё лэд пур мо(н) са́к. жё сюи диспонибль марди матэ́(н). жё вудрэ прэсизэ лёр.',vs=['heure','avoir-pc','perdre','sac','gare','ne-plus','jai-possession','petit','je-voudrais','demander','aide','disponible','mardi','matin','preciser'],av=['perdre','sac','gare'],aq='Что потеряно и где?',ac=['Сумка на вокзале','Книга в музее','Ключ в парке'],aa=0,rq='Какое состояние прямо названо сейчас?',rc=['Сумка точно найдена','Своей сумки у говорящего больше нет','Говорящий никогда не терял сумку'],ra=1,rav=['ne-plus','jai-possession','sac'],vq='Выберите сообщение у меня больше нет моей сумки.',vc=[('Je n’ai jamais perdu mon sac.','жё нэ жамэ пэрдю мо(н) са́к'),('J’ai mon sac.','жэ мо(н) са́к'),('Je n’ai plus mon sac.','жё нэ плю мо(н) са́к')],va=2,extra=['jamais-experience'],vid=['ne-plus'],before='Je n’ai ',bp='жё нэ',after=' mon sac.',ap='мо(н) са́к',fc=[('plus','плю'),('déjà','дэжа́')],fa=0,full='Je n’ai plus mon sac.',fp='жё нэ плю мо(н) са́к.',fins='Восстановите текущее изменившееся состояние больше нет.',tokens=[('J’ai','жэ'),('perdu','пэрдю́'),('mon sac','мо(н) са́к'),('à la gare.','а ла га́р')],of='J’ai perdu mon sac à la gare.',op='жэ пэрдю мо(н) сак а ла га́р.',translation='Я потерял сумку на вокзале.',oi='Соберите: я потерял сумку на вокзале. Сначала форма прошлого, затем действие, вещь и место.',goals=[U]),
dict(n=2,desc='Поймите ошибку с гостиничным номером и сообщите о ней.',warm=None,intro='Signaler — сообщать о проблеме; une erreur — ошибка. Je voudrais signaler une erreur — «Мне бы хотелось сообщить об ошибке». Chambre здесь — гостиничный номер, а numéro — его идентификатор. Если бронь на номер два, а указан номер три, числа нельзя менять местами. Ce n’est pas la chambre de ma réservation — «Это не номер из моего бронирования».',iv=['signaler','erreur','chambre-hotel'],notes=[('Signaler','синьялэ́'),('une erreur','юн эрё́р'),('Je voudrais signaler une erreur','жё вудрэ синьялэ юн эрё́р'),('Chambre','ша́(н)бр'),('numéro','нюмэро́'),('Ce n’est pas la chambre de ma réservation','сё нэ па ла ша(н)бр дё ма рэзэрвасьо́(н)')],rows=[('signaler','Сообщать о проблеме'),('erreur','Ошибка'),('chambre-hotel','Гостиничный номер'),('numero','Номер как идентификатор')],f='Ma réservation est pour la chambre deux. Le numéro de cette chambre est trois. Je voudrais signaler une erreur. Ce n’est pas la chambre de ma réservation. Je voudrais préciser la réservation.',p='ма рэзэрвасьо(н) э пур ла ша(н)бр дё́. лё нюмэро дё сэт ша(н)бр э труа́. жё вудрэ синьялэ юн эрё́р. сё нэ па ла ша(н)бр дё ма рэзэрвасьо́(н). жё вудрэ прэсизэ ла рэзэрвасьо́(н).',vs=['reservation','chambre-hotel','deux','numero','trois','je-voudrais','signaler','erreur','preciser'],av=['reservation','deux','trois','chambre-hotel'],aq='Какой номер в бронировании и какой указан у этой комнаты?',ac=['В бронировании три; здесь два','В бронировании два; здесь три','Оба номера два'],aa=1,rq='О какой проблеме сообщает человек?',rc=['Данный гостиничный номер не совпадает с бронированием','Бронирование уже исправлено','Рейс отменён'],ra=0,rav=['signaler','erreur','reservation'],vq='Выберите сообщение о наличии ошибки.',vc=[('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт'),('J’espère que tu es disponible.','жэспэр кё тю э диспони́бль'),('Je voudrais signaler une erreur.','жё вудрэ синьялэ юн эрё́р')],va=2,extra=['payer','par-carte','j-espere-que','disponible'],vid=['signaler','erreur'],before='Je voudrais ',bp='жё вудрэ',after=' une erreur.',ap='юн эрё́р',fc=[('signaler','синьялэ́'),('payer','пэйе́')],fa=0,full='Je voudrais signaler une erreur.',fp='жё вудрэ синьялэ юн эрё́р.',fins='Восстановите сообщить об ошибке.',tokens=[('Je voudrais','жё вудрэ́'),('signaler','синьялэ́'),('une','юн'),('erreur.','эрё́р')],of='Je voudrais signaler une erreur.',op='жё вудрэ синьялэ юн эрё́р.',translation='Мне бы хотелось сообщить об ошибке.',oi='Соберите: мне бы хотелось сообщить об ошибке. Сначала вежливая рамка, затем действие и ошибка с артиклем.',goals=[U]),
dict(n=3,desc='Поймите сообщение об отмене авиарейса, не добавляя неуказанную замену или возмещение.',warm=('A2-U16',3,'Q05'),intro='Un vol здесь — авиарейс. Une annulation — отмена. Annuler — отменять; в выбранной готовой форме Le vol est annulé — «Рейс отменён». Je voudrais signaler l’annulation du vol сообщает об этой проблеме. Отмена не равна задержке и не подтверждает, что другой рейс или возмещение уже предоставлены.',iv=['vol-avion','annulation','annuler'],notes=[('Un vol','э(н) воль'),('Une annulation','юн анюласьо́(н)'),('Annuler','анюлэ́'),('Le vol est annulé','лё воль э танюлэ́'),('Je voudrais signaler l’annulation du vol','жё вудрэ синьялэ ланюласьо(н) дю во́ль')],rows=[('vol-avion','Авиарейс'),('annulation','Отмена'),('annuler','Отменять событие')],f='Le vol de mardi à dix heures est annulé. Je voudrais signaler l’annulation du vol. Le vol n’a pas lieu mardi. Je voudrais demander de l’aide. Je suis disponible vendredi matin.',p='лё воль дё марди а ди зёр э танюлэ́. жё вудрэ синьялэ ланюласьо(н) дю во́ль. лё воль на па льё марди́. жё вудрэ дёма(н)дэ дё лэ́д. жё сюи диспонибль ва(н)дрэди матэ́(н).',vs=['vol-avion','mardi','dix','heure','annuler','je-voudrais','signaler','annulation','avoir-lieu','demander','aide','disponible','vendredi','matin'],av=['vol-avion','mardi','dix','annuler'],aq='Какой рейс назван отменённым?',ac=['Пятничный в полдень','Вторничный в десять','Вторничный в восемь вечера'],aa=1,rq='Что подтверждено сообщением?',rc=['Названный рейс отменён; замена не указана','Другой рейс уже забронирован','Деньги уже возвращены'],ra=0,rav=['annuler','annulation'],vq='Выберите сообщение об отменённом рейсе.',vc=[('Le vol a lieu mardi.','лё воль а льё марди́'),('Je voudrais préciser le tarif.','жё вудрэ прэсизэ лё тари́ф'),('Le vol est annulé.','лё воль э танюлэ́')],va=2,extra=['preciser','tarif'],vid=['annuler','vol-avion'],before='Le vol est ',bp='лё воль э',after='.',ap='',fc=[('annulé','анюлэ́'),('disponible','диспони́бль')],fa=0,full='Le vol est annulé.',fp='лё воль э танюлэ́.',fins='Восстановите прямо названное состояние отменён.',tokens=[('Je voudrais','жё вудрэ́'),('signaler','синьялэ́'),('l’annulation','ланюласьо́(н)'),('du vol.','дю во́ль')],of='Je voudrais signaler l’annulation du vol.',op='жё вудрэ синьялэ ланюласьо(н) дю во́ль.',translation='Мне бы хотелось сообщить об отмене рейса.',oi='Соберите: мне бы хотелось сообщить об отмене рейса. Сначала просьба, затем действие, отмена и рейс.',goals=[A]),
dict(n=4,desc='Попросите исправить ошибку и отличите будущий план сотрудника от подтверждённого результата.',warm=('A2-U16',4,'Q05'),intro='Corriger — исправлять ошибку. Pourriez-vous corriger cette erreur ? — «Не могли бы вы исправить эту ошибку?». Это просьба сделать действие, а signaler — сообщить о проблеме. В ответе Je vais corriger cette erreur сотрудник сообщает о будущем плане. Это не подтверждение, что ошибка уже исправлена. Для недавнего результата повторяем знакомую рамку с новым глаголом: Je viens de corriger cette erreur — «Я только что исправил эту ошибку».',iv=['corriger','venir-de','aller-futur'],notes=[('Corriger','корижэ́'),('Pourriez-vous corriger cette erreur ?','пурье ву корижэ сэт эрё́р'),('signaler','синьялэ́'),('Je vais corriger cette erreur','жё вэ корижэ сэт эрё́р'),('Je viens de corriger cette erreur','жё вьэ(н) дё корижэ сэт эрё́р')],rows=[('corriger','Исправлять ошибку'),('signaler','Сообщать о проблеме'),('erreur','Ошибка')],f='— Ma réservation est pour la chambre deux. Le numéro de cette chambre est trois. Je voudrais signaler une erreur. Pourriez-vous corriger cette erreur ? — Je vais corriger cette erreur. Je suis disponible mardi matin.',p='— ма рэзэрвасьо(н) э пур ла ша(н)бр дё́. лё нюмэро дё сэт ша(н)бр э труа́. жё вудрэ синьялэ юн эрё́р. пурье ву корижэ сэт эрё́р? — жё вэ корижэ сэт эрё́р. жё сюи диспонибль марди матэ́(н).',vs=['reservation','chambre-hotel','deux','numero','trois','je-voudrais','signaler','erreur','pourriez-vous','corriger','aller-futur','disponible','mardi','matin'],av=['pourriez-vous','corriger','erreur'],aq='О чём прямо просит первая реплика?',ac=['Исправить ошибку','Только назвать свободное время','Отменить любой рейс'],aa=0,rq='Что позволяет утверждать ответ сотрудника?',rc=['Ошибка уже исправлена','Другой номер уже предоставлен','Сотрудник собирается исправить ошибку; результат ещё не подтверждён'],ra=2,rav=['aller-futur','corriger'],vq='Выберите просьбу исправить ошибку, а не только назвать её.',vc=[('Pourriez-vous corriger cette erreur ?','пурье ву корижэ сэт эрё́р'),('Je voudrais signaler une erreur.','жё вудрэ синьялэ юн эрё́р'),('Je suis disponible mardi.','жё сюи диспонибль марди́')],va=0,extra=[],vid=['corriger'],before='Pourriez-vous ',bp='пурье ву',after=' cette erreur ?',ap='сэт эрё́р',fc=[('corriger','корижэ́'),('payer','пэйе́')],fa=0,full='Pourriez-vous corriger cette erreur ?',fp='пурье ву корижэ сэт эрё́р?',fins='Восстановите просьбу исправить ошибку.',tokens=[('Pourriez-vous','пурье ву́'),('corriger','корижэ́'),('cette','сэт'),('erreur ?','эрё́р')],of='Pourriez-vous corriger cette erreur ?',op='пурье ву корижэ сэт эрё́р?',translation='Не могли бы вы исправить эту ошибку?',oi='Соберите просьбу: не могли бы вы исправить эту ошибку? Сначала вежливая рамка, затем действие и ошибка.',goals=[A])]
for d in DATA:
 s=start(d['n'],d['desc'],[])
 if d['warm']:warm(s,*d['warm'])
 else:
  t=copy.deepcopy(S[0]['tasks'][4]);t['id']='Q01';s['tasks'].append(t)
 intro(s,2,d['intro'],d['iv'],d['notes'])
 pairs(s,d['goals'],d['rows'],'Названия проблемы и действия различены.','Не смешивайте состояние, просьбу и результат.')
 listen(s,d['goals'],d['vs'],d['av'],d['aq'],d['f'],d['p'],d['ac'],d['aa'],'Ответ сохраняет названные сведения.','Правильный смысл: '+d['ac'][d['aa']]+'. Не добавляйте неуказанных действий.')
 choose(s,'vocabulary',d['goals'],list(dict.fromkeys(d['vs']+d['extra'])),d['vid'],d['vq'],ru('Сохраните нужную функцию реплики.'),[fr(f,p) for f,p in d['vc']],d['va'],'Выбрана нужная функция.','Нужное выражение: '+d['vc'][d['va']][0])
 read(s,d['goals'],d['vs'],d['rav'],d['rq'],d['f'],d['p'],d['rc'],d['ra'],'Вывод соответствует прямо сказанному.','Правильный смысл: '+d['rc'][d['ra']]+'. Будущий план или просьба не подтверждают результата.')
 fill(s,d['goals'],list(dict.fromkeys(d['vs']+['deja-experience','payer'])),[],d['fins'],d['before'],d['bp'],d['after'],d['ap'],[fr(f,p) for f,p in d['fc']],d['fa'],d['full'],d['fp'],'Смысл модели сохранён.','Правильная фраза: '+d['full'])
 order(s,d['goals'],d['vs'],[],d['oi'],d['tokens'],d['of'],d['op'],'Порядок и смысл частей сохранены.','Следуйте заданному порядку, не меняя предмет проблемы и цель просьбы.')
 page(s,[('Изученная модель',[d['intro']],[ex(d['of'],d['translation'],d['op'])]),('Границы сообщения',['Это вымышленный диалог для французского. Сообщение о проблеме, просьба, будущий план и подтверждённое исправление не отождествляются. Неуказанные действия и условия не добавляются.'],[])])
 s['tasks'][3]['feedback']['pronunciation_notes']=[dict(fr=d['vc'][d['va']][0],transcription_ru=d['vc'][d['va']][1])]
 s['tasks'][5]['feedback']['pronunciation_notes']=[dict(fr=d['full'],transcription_ru=d['fp'])]
 s['public_page']['explanation_blocks'][0]['pronunciation_notes']=[dict(fr=f,transcription_ru=p) for f,p in d['notes']]
s=start(5,'Объедините описание потери, ошибки, отмены и вежливое обращение к сотруднику без нового материала.',[])
d=DATA[0]
listen(s,[U],d['vs'],['perdre','sac','gare','ne-plus'],'Что произошло с вещью и что сказано о текущем состоянии?',d['f'],d['p'],['Сумка потеряна на вокзале; сейчас своей сумки больше нет','Сумка уже найдена','Говорящий никогда раньше ничего не терял'],0,'Названы потеря на вокзале и текущее отсутствие сумки.','Нахождение и отрицание всех прошлых потерь не сообщаются.')
d=DATA[1]
read(s,[U],d['vs'],['reservation','chambre-hotel','deux','trois'],'В чём состоит ошибка с номером?',d['f'],d['p'],['В бронировании три, здесь два','В бронировании два, здесь три','Рейс отменён'],1,'Сравниваются разные идентификаторы: два и три.','Не меняйте числа местами и не переносите проблему другого сообщения.')
phrase_pairs(s,[U],[('J’ai perdu mon sac.','жэ пэрдю мо(н) са́к','Сумка потеряна: событие в прошлом',['avoir-pc','perdre','sac'],['perdre']),('Je n’ai plus mon sac.','жё нэ плю мо(н) са́к','Сейчас своей сумки больше нет',['ne-plus','jai-possession','sac'],['ne-plus']),('Je n’ai jamais perdu mon sac.','жё нэ жамэ пэрдю мо(н) са́к','Никогда раньше не терял свою сумку',['jamais-experience','avoir-pc','perdre','sac'],['jamais-experience'])],'Различены отдельные высказывания разных ситуаций: событие, состояние и опыт.','Больше нет не означает никогда не терял; не объединяйте противоположные реплики в один рассказ.')
d=DATA[2]
listen(s,[A],d['vs'],['annuler','annulation'],'Что известно о замене отменённого рейса?',d['f'],d['p'],['Она уже подтверждена на пятницу','Деньги уже получены','Отмена названа; замена не сообщается'],2,'В тексте названа отмена, но не предоставление замены.','Свободное время говорящего не является бронированием другого рейса.')
F='— Ma réservation est pour la chambre deux. Le numéro de cette chambre est trois. Je voudrais signaler une erreur. — Je viens de corriger cette erreur. — Merci pour l’aide. Je voudrais préciser le numéro.'
T='— ма рэзэрвасьо(н) э пур ла ша(н)бр дё́. лё нюмэро дё сэт ша(н)бр э труа́. жё вудрэ синьялэ юн эрё́р. — жё вьэ(н) дё корижэ сэт эрё́р. — мэрси пур лэ́д. жё вудрэ прэсизэ лё нюмэро́.'
V=['reservation','chambre-hotel','deux','numero','trois','je-voudrais','signaler','erreur','venir-de','corriger','merci','aide','preciser']
read(s,[A],V,['venir-de','corriger'],'Какое состояние исправления прямо сообщает сотрудник в этом отдельном диалоге?',F,T,['Он только что исправил ошибку','Он только планирует исправить','Он сообщил новый номер рейса'],0,'Рамка только что сообщает о недавнем исправлении ошибки.','Не заменяйте недавний результат будущим планом. Конкретный новый номер сотрудник не называет.')
choose(s,'vocabulary',[A],['pourriez-vous','corriger','erreur','je-voudrais','signaler','preciser','tarif'],['corriger'],'Попросите исправить ошибку.',ru('Нужна просьба о действии, а не только сообщение о проблеме.'),[fr('Je voudrais signaler une erreur.','жё вудрэ синьялэ юн эрё́р'),fr('Pourriez-vous corriger cette erreur ?','пурье ву корижэ сэт эрё́р'),fr('Pourriez-vous préciser le tarif ?','пурье ву прэсизэ лё тари́ф')],1,'Выбрана просьба исправить.','Сообщение о проблеме и уточнение цены выполняют другие функции.')
grammar(fill(s,[U],['avoir-pc','jamais-experience','ne-plus','perdre','sac'],['jamais-experience'],'Восстановите отсутствие прежнего опыта: никогда не терял свою сумку.','Je n’ai ','жё нэ',' perdu mon sac.','пэрдю мо(н) са́к',[fr('plus','плю'),fr('jamais','жамэ́')],1,'Je n’ai jamais perdu mon sac.','жё нэ жамэ пэрдю мо(н) са́к.','Отрицается прежний опыт потери.','Изученная рамка больше нет относится к изменению состояния и не заменяет здесь никогда.'))
order(s,[A],['reservation','chambre-hotel','deux','pourriez-vous','corriger','erreur'],[],'При брони на номер два предоставлен другой номер. Соберите обращение: моя бронь на номер два. Не могли бы вы исправить эту ошибку? Сначала условие бронирования, затем просьба.',[('Ma réservation','ма рэзэрвасьо́(н)'),('est pour la chambre deux.','э пур ла ша(н)бр дё́'),('Pourriez-vous','пурье ву́'),('corriger','корижэ́'),('cette erreur ?','сэт эрё́р')],'Ma réservation est pour la chambre deux. Pourriez-vous corriger cette erreur ?','ма рэзэрвасьо(н) э пур ла ша(н)бр дё́. пурье ву корижэ сэт эрё́р?','Бронирование и просьба сохранены.','Сначала завершите информацию о брони, затем сформулируйте действие исправления.')
page(s,[('Назвать проблему',['Потеря вещи, ошибка с номером и отмена рейса — разные ситуации. Нового обязательного материала нет.'],[W['perdre']['example'],W['signaler']['example']]),('Отличить план и результат',['Сообщить о проблеме не равно попросить исправить её. Будущий план сотрудника отличается от прямо названного недавнего результата; замена рейса и возврат денег не выводятся из отмены.'],[W['corriger']['example']])])

refs={1:{4:['jamais-experience','avoir-pc','perdre','sac','ne-plus','jai-possession'],6:['ne-plus','jai-possession','sac','deja-experience'],7:['avoir-pc','perdre','sac','gare']},2:{4:['je-voudrais','signaler','erreur','payer','par-carte','j-espere-que','disponible'],6:['je-voudrais','signaler','erreur','payer'],7:['je-voudrais','signaler','erreur']},3:{4:['vol-avion','avoir-lieu','mardi','je-voudrais','preciser','tarif','annuler'],6:['vol-avion','annuler','disponible'],7:['je-voudrais','signaler','annulation','vol-avion']},4:{4:['pourriez-vous','corriger','erreur','je-voudrais','signaler','disponible','mardi'],6:['pourriez-vous','corriger','erreur','payer'],7:['pourriez-vous','corriger','erreur']}}
for num,rs in refs.items():
 for q,ids in rs.items():S[num-1]['tasks'][q-1]['vocabulary_ids']=ids

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
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Описание потери вещи, ошибка с гостиничным номером, отмена авиарейса и просьба исправить ситуацию; состояние, опыт, план и результат.',learning_outcomes=['Понимать сообщение о потере и текущем отсутствии вещи.','Отличать больше не от никогда не.','Понимать несоответствие номера бронированию.','Понимать отмену рейса без неуказанных последствий.','Выбирать просьбу исправить и различать будущий план и недавний результат.'])
P=dict(schema_version=1,package_id='programme-a2-u19-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U19.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U19 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=plan['sessions'][n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('perdre','Сохранять событие потери отдельно от текущего состояния.'),('ne-plus','Отличать больше не от никогда и от сравнения.'),('jamais-experience','Повторить отрицание опыта с прежним ключом.'),('signaler','Отличать сообщение о проблеме от исправления.'),('annulation','Сохранять отмену отдельно от замены и возмещения.'),('corriger','Отличать просьбу, будущий план и недавний результат.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить внешнюю зависимость A2-U18-L05 в целевой базе.','Редактору проверить естественность объяснения потери, гостиничной ошибки, отмены и исправления, выбранные формы perdu / annulé.','Прослушать браузерную озвучку; выполнить предпросмотр и прохождение, измерить время. Эти действия фактически не выполнялись.'],progress=dict(current_unit='A2-U19',prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='A2-U20',next_unit_title='Опыт и будущее'),scope_notes=['Один полный пакет пяти занятий. Восемь новых значений распределены как два, два, три, одно, ноль. Ни одно введение не содержит более трёх карточек; в L05 нового обязательного материала нет.','Perdu — выбранная форма знакомимого в этом занятии perdre в составном прошлом. Модель объяснена до проверки, не вводится полный список неправильных причастий.','Ne … plus означает больше не, не подменяется прежним plus в сравнении. Jamais сохраняет fr:jamais:never-in-experience-negation и полный объект: отрицание опыта переносится с обучения на потерю вещи, без нового ключа ради смены действия.','Chambre-hotel сохраняет значение гостиничного номера, не подменяется chambre как спальня. Numéro — прежний идентификатор; два и три — разные указанные номера, не оценки качества.','Annulation — существительное отмены, annuler — действие; выбранная готовая форма Le vol est annulé показана до проверки. Vol здесь только авиарейс, не другие значения слова.','Просьба сообщить о проблеме отличается от просьбы исправить. Je vais corriger — будущий план, Je viens de corriger — недавний результат. Обе рамки показаны до итогового задания.','Отмена не подтверждает автоматически предоставленную замену, бронь или возврат денег. Доступность в пятницу не становится новым рейсом.','В L01 повторены личные новости и порядок событий из A2-U18. В L03 — правила вымышленного парка из A2-U16; в L04 — основание выбора тропы оттуда же. Прежние карточки не изменяются.','Все потери, гостиницы, рейсы, ошибки и исправления вымышлены для языковой практики. Не сообщаются действующие права пассажиров, правила перевозчика, реальные рейсы, гарантии размещения или выплаты.','Нет реальных бронирований, платежей, обращений или отправленных сообщений. Ответы только выбираются или собираются; микрофон и набор текста не требуются.','Озвучка только браузером: fr-FR, asset_id=null. Аудиофайлы и внешние сервисы не используются. Импорт, публикация и прослушивание не выполнялись; принятие пользователем не подтверждено.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='J’ai perdu / Je n’ai plus mon sac / Je n’ai jamais perdu mon sac; событие, состояние и опыт.'),dict(session_id=S[1]['id'],material='Je voudrais signaler une erreur; бронирование и номер предоставленной комнаты.'),dict(session_id=S[2]['id'],material='Le vol est annulé; существительное annulation; Le vol n’a pas lieu.'),dict(session_id=S[3]['id'],material='Pourriez-vous corriger; Je vais corriger / Je viens de corriger.')]
(OUT/'A2-U19.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
