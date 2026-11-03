"""Author A2-U17 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/A2.plan.json').read_text())['level']
plan=level['units'][16]
W={}
for source in [OUT/'A2-U16.v1.json',OUT/'A2-U15.v1.json',OUT/'A2-U14.v1.json',OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='A2.U17.UNDERSTAND';A='A2.U17.APPLY'
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
word('tu-devrais','fr:tu-devrais:advice-action','tu devrais','тебе стоило бы: совет сделать действие','тю дёврэ́',1,ex('Tu devrais choisir le sentier calme.','Тебе стоило бы выбрать тихую тропу.','тю дёврэ шуазир лё са(н)тье ка́льм'),['Совет сделать действие','Подтверждённое завершение','Запрет'],0,'Дайте совет выбрать тихую тропу.',[('Tu devrais choisir le sentier calme.','тю дёврэ шуазир лё са(н)тье ка́льм'),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),('Il est interdit de faire du bruit.','иль э тэ(н)тэрди дё фэр дю брюи́')],0)
word('a-ta-place','fr:a-ta-place:advice-perspective','à ta place','на твоём месте: позиция дающего совет','а та пла́с',1,ex('À ta place, je choisirais ce sentier.','На твоём месте я выбрал бы эту тропу.','а та плас, жё шуазирэ сё са(н)тье́'),['Место проведения','На твоём месте: позиция совета','Крайний срок'],1,'Сообщите, что выбрали бы эту тропу на месте собеседника.',[('Je suis disponible mardi.','жё сюи диспонибль марди́'),('À ta place, je choisirais ce sentier.','а та плас, жё шуазирэ сё са(н)тье́'),('Le délai est vendredi.','лё дэлэ э ва(н)дрэди́')],1)
word('il-vaut-mieux','fr:il-vaut-mieux:preferable-action','il vaut mieux','лучше сделать: рекомендация','иль во мьё́',2,ex('Il vaut mieux éviter le sentier bruyant.','Лучше избегать шумной тропы.','иль во мьё зэвитэ лё са(н)тье брюийа́(н)'),['Запрещено','Уже сделано','Лучше сделать'],2,'Рекомендуйте избегать шумной тропы, не объявляя запрет.',[('Il est interdit de faire du bruit.','иль э тэ(н)тэрди дё фэр дю брюи́'),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),('Il vaut mieux éviter le sentier bruyant.','иль во мьё зэвитэ лё са(н)тье брюийа́(н)')],2)
word('eviter','fr:eviter:avoid-option','éviter','избегать указанного варианта','эвитэ́',2,ex('Il vaut mieux éviter le sentier bruyant.','Лучше избегать шумной тропы.','иль во мьё зэвитэ лё са(н)тье брюийа́(н)'),['Избегать варианта','Уточнять срок','Оплачивать'],0,'Назовите избегание шумной тропы.',[('Éviter le sentier bruyant.','эвитэ лё са(н)тье брюийа́(н)'),('Choisir le sentier bruyant.','шуазир лё са(н)тье брюийа́(н)'),('Suivre le sentier bruyant.','сюивр лё са(н)тье брюийа́(н)')],0)
for w,g,b in [('tu-devrais','Реплика советует выбрать тихую тропу.','Результат и запрет не являются этой формой совета.'),('a-ta-place','Говорящий представляет себя на месте собеседника и предлагает выбор.','Доступное время и срок не выражают эту позицию совета.'),('il-vaut-mieux','Высказана рекомендация избегать шумной тропы.','Запрет и недавний результат выполняют другие функции.'),('eviter','Названо избегание варианта.','Выбирать и следовать по тропе не означают избегать её.')]:W[w]['review_items'][1]['feedback']=fb(g,b)

def read(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def warm(s,u,n,q):
 old=json.loads((OUT/f'{u}.v1.json').read_text());t=copy.deepcopy(next(t for t in old['sessions'][n-1]['tasks'] if t['id']==q));t['id']='Q01';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))
DATA=[dict(n=1,desc='Поймите рекомендацию и позицию на твоём месте, не подменяя их готовым результатом.',warm=('A2-U16',4,'Q05'),intro='Tu devrais + действие — «тебе стоило бы»: совет в этой ситуации. Tu devrais choisir le sentier calme — «Тебе стоило бы выбрать тихую тропу». À ta place — «на твоём месте». В модели À ta place, je choisirais ce sentier форма choisirais знакомого choisir означает «я выбрал бы». Учим выбранную форму, без полной таблицы условного наклонения. Совет не сообщает, что выбор уже сделан, и не равен прямо объявленному запрету.',iv=['tu-devrais','a-ta-place','choisir'],notes=[('Tu devrais','тю дёврэ́'),('Tu devrais choisir le sentier calme','тю дёврэ шуазир лё са(н)тье ка́льм'),('À ta place','а та пла́с'),('À ta place, je choisirais ce sentier','а та плас, жё шуазирэ сё са(н)тье́'),('choisirais','шуазирэ́'),('choisir','шуази́р')],rows=[('tu-devrais','Тебе стоило бы'),('a-ta-place','На твоём месте'),('choisir','Выбирать вариант')],f='Tu devrais choisir le sentier calme. À ta place, je choisirais ce sentier. Le sentier va de la forêt au lac. L’autre sentier est plus bruyant. Je voudrais faire une randonnée avec ma sœur.',p='тю дёврэ шуазир лё са(н)тье ка́льм. а та плас, жё шуазирэ сё са(н)тье́. лё са(н)тье ва дё ла форэ о ла́к. лотр са(н)тье э плю брюийа́(н). жё вудрэ фэр юн ра(н)донэ авэк ма сё́р.',vs=['tu-devrais','choisir','sentier','calme','a-ta-place','aller','foret','lac','autre','plus-comparatif','bruyant','je-voudrais','randonnee','soeur'],av=['tu-devrais','calme'],aq='Что рекомендуют сделать?',ac=['Выбрать тихую тропу','Обязательно оплатить курс','Отправить сообщение, которое уже доставлено'],aa=0,rq='Какой вывод о выборе подтверждён?',rc=['Выбор уже выполнен','Дан совет выбрать; выполнение не подтверждено','Установлен юридический запрет прогулки'],ra=1,rav=['tu-devrais','a-ta-place'],vq='Выберите совет собеседнику выбрать тихую тропу.',vc=[('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),('Il est interdit de faire du bruit.','иль э тэ(н)тэрди дё фэр дю брюи́'),('Tu devrais choisir le sentier calme.','тю дёврэ шуазир лё са(н)тье ка́льм')],va=2,extra=['venir-de','terminer','tache-travail','il-est-interdit-de','faire-du-bruit'],vid=['tu-devrais'],before='Tu ',bp='тю',after=' choisir le sentier calme.',ap='шуазир лё са(н)тье ка́льм',fc=[('devrais','дёврэ́'),('choisis','шуази́')],fa=0,full='Tu devrais choisir le sentier calme.',fp='тю дёврэ шуазир лё са(н)тье ка́льм.',fins='Восстановите изученную рамку тебе стоило бы выбрать.',tokens=[('À ta place,','а та пла́с'),('je','жё'),('choisirais','шуазирэ́'),('ce sentier.','сё са(н)тье́')],of='À ta place, je choisirais ce sentier.',op='а та плас, жё шуазирэ сё са(н)тье́.',translation='На твоём месте я выбрал бы эту тропу.',oi='Соберите: на твоём месте я выбрал бы эту тропу. Сначала позиция совета, затем говорящий, выбранная форма и предмет.',goals=[U]),
dict(n=2,desc='Различите рекомендацию избегать варианта и прямо объявленный запрет.',warm=None,intro='Il vaut mieux + действие — «лучше сделать». Éviter — избегать варианта. Il vaut mieux éviter le sentier bruyant — «Лучше избегать шумной тропы». Это рекомендация в ответ на желание тишины, а не сообщение о запрете. Знакомое Il est interdit de прямо объявляет запрет; оно имеет другую функцию.',iv=['il-vaut-mieux','eviter','il-est-interdit-de'],notes=[('Il vaut mieux','иль во мьё́'),('Éviter','эвитэ́'),('Il vaut mieux éviter le sentier bruyant','иль во мьё зэвитэ лё са(н)тье брюийа́(н)'),('Il est interdit de','иль э тэ(н)тэрди дё')],rows=[('il-vaut-mieux','Лучше сделать'),('eviter','Избегать'),('tu-devrais','Тебе стоило бы')],f='— Je voudrais un sentier calme pour faire une randonnée. — Il vaut mieux éviter le sentier bruyant. Tu devrais choisir le sentier qui est calme. Le sentier calme va de la forêt au lac.',p='— жё вудрэ э(н) са(н)тье кальм пур фэр юн ра(н)донэ́. — иль во мьё зэвитэ лё са(н)тье брюийа́(н). тю дёврэ шуазир лё са(н)тье ки э ка́льм. лё са(н)тье кальм ва дё ла форэ о ла́к.',vs=['je-voudrais','sentier','calme','randonnee','il-vaut-mieux','eviter','bruyant','tu-devrais','choisir','qui-relatif','aller','foret','lac'],av=['il-vaut-mieux','eviter','bruyant'],aq='Какой вариант рекомендуют избегать?',ac=['Тихую тропу','Шумную тропу','Любую прогулку'],aa=1,rq='Что эта реплика сообщает о шумной тропе?',rc=['Её советуют избегать при желании тишины','Она официально закрыта для всех','Она уже пройдена'],ra=0,rav=['il-vaut-mieux','eviter'],vq='Выберите рекомендацию избегать шумной тропы.',vc=[('Il est interdit de faire du bruit.','иль э тэ(н)тэрди дё фэр дю брюи́'),('Je viens de terminer cette tâche.','жё вьэ(н) дё тэрминэ сэт та́ш'),('Il vaut mieux éviter le sentier bruyant.','иль во мьё зэвитэ лё са(н)тье брюийа́(н)')],va=2,extra=['il-est-interdit-de','faire-du-bruit','venir-de','terminer','tache-travail'],vid=['il-vaut-mieux','eviter'],before='Il vaut mieux ',bp='иль во мьё',after=' le sentier bruyant.',ap='лё са(н)тье брюийа́(н)',fc=[('éviter','эвитэ́'),('choisir','шуази́р')],fa=0,full='Il vaut mieux éviter le sentier bruyant.',fp='иль во мьё зэвитэ лё са(н)тье брюийа́(н).',fins='Сохраните совет избегать, а не выбирать шумную тропу.',tokens=[('Il vaut mieux','иль во мьё́'),('éviter','эвитэ́'),('le sentier','лё са(н)тье́'),('bruyant.','брюийа́(н)')],of='Il vaut mieux éviter le sentier bruyant.',op='иль во мьё зэвитэ лё са(н)тье брюийа́(н).',translation='Лучше избегать шумной тропы.',oi='Соберите: лучше избегать шумной тропы. Сначала рамка рекомендации, затем действие и вариант.',goals=[U]),
dict(n=3,desc='Сохраните условие, при котором даётся совет о выборе прогулки.',warm=('A2-U14',3,'Q05'),intro='Повторяем si — если. Выбранная форма знакомого vouloir: tu veux — «ты хочешь». Si tu veux faire une randonnée, tu devrais choisir le sentier calme — «Если ты хочешь совершить прогулку, тебе стоило бы выбрать тихую тропу». После si здесь настоящее время. Условие не является само по себе утверждением, что желание уже установлено как факт.',iv=['si-condition','je-veux','tu-devrais'],notes=[('si','си'),('vouloir','вулуа́р'),('tu veux','тю вё'),('Si tu veux faire une randonnée, tu devrais choisir le sentier calme','си тю вё фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм')],rows=[('si-condition','Если: условие'),('tu-devrais','Тебе стоило бы'),('a-ta-place','На твоём месте')],f='Si tu veux faire une randonnée, tu devrais choisir le sentier calme. Le sentier va de la forêt au lac. L’autre sentier est plus bruyant. À ta place, je choisirais le sentier calme.',p='си тю вё фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм. лё са(н)тье ва дё ла форэ о ла́к. лотр са(н)тье э плю брюийа́(н). а та плас, жё шуазирэ лё са(н)тье ка́льм.',vs=['si-condition','je-veux','randonnee','tu-devrais','choisir','sentier','calme','aller','foret','lac','autre','plus-comparatif','bruyant','a-ta-place'],av=['si-condition','je-veux','randonnee'],aq='Какое условие названо перед советом?',ac=['Если уже всё закончено','Если хочешь совершить прогулку','Если посылка доставлена'],aa=1,rq='Как понимать желание прогулки в первой фразе?',rc=['Оно поставлено как условие совета','Оно доказано как выполненное действие','Оно названо официальным запретом'],ra=0,rav=['si-condition'],vq='Выберите именно условие совета.',vc=[('À ta place.','а та пла́с'),('Parce que l’autre sentier est bruyant.','парс кё лотр са(н)тье э брюийа́(н)'),('Si tu veux faire une randonnée.','си тю вё фэр юн ра(н)донэ́')],va=2,extra=['parce-que'],vid=['si-condition'],before='Si tu ',bp='си тю',after=' faire une randonnée, tu devrais choisir le sentier calme.',ap='фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм',fc=[('veux','вё'),('vouloir','вулуа́р')],fa=0,full='Si tu veux faire une randonnée, tu devrais choisir le sentier calme.',fp='си тю вё фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм.',fins='Восстановите выбранную форму настоящего времени ты хочешь.',tokens=[('Si tu veux','си тю вё'),('faire une randonnée,','фэр юн ра(н)донэ́'),('tu devrais choisir','тю дёврэ шуази́р'),('le sentier calme.','лё са(н)тье ка́льм')],of='Si tu veux faire une randonnée, tu devrais choisir le sentier calme.',op='си тю вё фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм.',translation='Если хочешь совершить прогулку, тебе стоило бы выбрать тихую тропу.',oi='Соберите условный совет: если хочешь совершить прогулку, тебе стоило бы выбрать тихую тропу. Сначала условие, затем совет.',goals=[A]),
dict(n=4,desc='Свяжите рекомендацию с прямо названной причиной.',warm=('A2-U14',2,'Q05'),intro='Повторяем parce que — потому что: Tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant. Совет — выбрать тихую тропу, причина — чрезмерный шум другой. Si задаёт условие, parce que объясняет причину. Ни цена, ни безопасность, ни отсутствие людей из слова тихий автоматически не выводятся.',iv=['parce-que','si-condition','tu-devrais'],notes=[('parce que','парс кё'),('Tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant','тю дёврэ шуазир лё са(н)тье кальм парс кё лотр са(н)тье э тро брюийа́(н)'),('Si','си')],rows=[('parce-que','Потому что: причина'),('si-condition','Если: условие'),('eviter','Избегать варианта')],f='Tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant. Le sentier calme va de la forêt au lac. À ta place, je choisirais ce sentier. Je voudrais faire une randonnée.',p='тю дёврэ шуазир лё са(н)тье кальм парс кё лотр са(н)тье э тро брюийа́(н). лё са(н)тье кальм ва дё ла форэ о ла́к. а та плас, жё шуазирэ сё са(н)тье́. жё вудрэ фэр юн ра(н)донэ́.',vs=['tu-devrais','choisir','sentier','calme','parce-que','autre','trop','bruyant','aller','foret','lac','a-ta-place','je-voudrais','randonnee'],av=['parce-que','trop','bruyant'],aq='Какова прямо названная причина рекомендации?',ac=['Другая тропа слишком шумная','Тихая тропа самая дешёвая','На тихой тропе никогда нет людей'],aa=0,rq='Чего текст не позволяет утверждать?',rc=['Названа причина выбора','Другая тропа слишком шумная','Тихий вариант обязательно самый дешёвый'],ra=2,rav=['parce-que','calme'],vq='Выберите причину, а не условие.',vc=[('Parce que l’autre sentier est trop bruyant.','парс кё лотр са(н)тье э тро брюийа́(н)'),('Si tu veux faire une randonnée.','си тю вё фэр юн ра(н)донэ́'),('À ta place.','а та пла́с')],va=0,extra=['si-condition','je-veux'],vid=['parce-que'],before='Tu devrais choisir le sentier calme ',bp='тю дёврэ шуазир лё са(н)тье кальм',after=' l’autre sentier est trop bruyant.',ap='лотр са(н)тье э тро брюийа́(н)',fc=[('parce que','парс кё'),('si','си')],fa=0,full='Tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant.',fp='тю дёврэ шуазир лё са(н)тье кальм парс кё лотр са(н)тье э тро брюийа́(н).',fins='Сохраните объяснение причины, а не условие.',tokens=[('Tu devrais choisir','тю дёврэ шуази́р'),('le sentier calme','лё са(н)тье ка́льм'),('parce que','парс кё'),('l’autre sentier est trop bruyant.','лотр са(н)тье э тро брюийа́(н)')],of='Tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant.',op='тю дёврэ шуазир лё са(н)тье кальм парс кё лотр са(н)тье э тро брюийа́(н).',translation='Тебе стоило бы выбрать тихую тропу, потому что другая слишком шумная.',oi='Соберите совет с причиной: тебе стоило бы выбрать тихую тропу, потому что другая слишком шумная. Сначала совет, затем причина.',goals=[A])]
for d in DATA:
 s=start(d['n'],d['desc'],[])
 if d['warm']:warm(s,*d['warm'])
 else:
  t=copy.deepcopy(S[0]['tasks'][4]);t['id']='Q01';s['tasks'].append(t)
 intro(s,2,d['intro'],d['iv'],d['notes'])
 pairs(s,d['goals'],d['rows'],'Конструкции различены по смыслу.','Не смешивайте совет, условие и причину.')
 listen(s,d['goals'],d['vs'],d['av'],d['aq'],d['f'],d['p'],d['ac'],d['aa'],'Ответ соответствует словам сообщения.','Правильный смысл: '+d['ac'][d['aa']]+'. Не добавляйте неуказанных сведений.')
 choose(s,'vocabulary',d['goals'],list(dict.fromkeys(d['vs']+d['extra'])),d['vid'],d['vq'],ru('Выберите выражение нужного смысла.'),[fr(f,p) for f,p in d['vc']],d['va'],'Выбрана нужная конструкция.','Нужное выражение: '+d['vc'][d['va']][0])
 read(s,d['goals'],d['vs'],d['rav'],d['rq'],d['f'],d['p'],d['rc'],d['ra'],'Вывод сохраняет явно названный смысл.','Правильный смысл: '+d['rc'][d['ra']]+'. Не превращайте совет в выполненное действие или неуказанное правило.')
 grammar(fill(s,d['goals'],list(dict.fromkeys(d['vs']+d['extra'])),[],d['fins'],d['before'],d['bp'],d['after'],d['ap'],[fr(f,p) for f,p in d['fc']],d['fa'],d['full'],d['fp'],'Изученная конструкция восстановлена.','Правильная фраза: '+d['full']))
 order(s,d['goals'],d['vs'],[],d['oi'],d['tokens'],d['of'],d['op'],'Порядок совета и уточнений сохранён.','Следуйте заданной последовательности, не меняя условие и причину.')
 page(s,[('Изученная модель',[d['intro']],[ex(d['of'],d['translation'],d['op'])]),('Границы вывода',['Это вымышленная языковая ситуация, не рекомендация реального маршрута. Ориентируйтесь только на сказанные цель и свойства; совет не подтверждает сделанный выбор.'],[])])
 s['tasks'][3]['feedback']['pronunciation_notes']=[dict(fr=d['vc'][d['va']][0],transcription_ru=d['vc'][d['va']][1])]
 s['tasks'][5]['feedback']['pronunciation_notes']=[dict(fr=d['full'],transcription_ru=d['fp'])]
 s['public_page']['explanation_blocks'][0]['pronunciation_notes']=[dict(fr=f,transcription_ru=p) for f,p in d['notes']]
s=start(5,'Объедините совет, избегание варианта, условие и причину в выборе вымышленного решения без нового материала.',[])
F='Si tu veux faire une randonnée, tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant. À ta place, je choisirais ce sentier. Le sentier va de la forêt au lac.'
T='си тю вё фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье кальм парс кё лотр са(н)тье э тро брюийа́(н). а та плас, жё шуазирэ сё са(н)тье́. лё са(н)тье ва дё ла форэ о ла́к.'
V=['si-condition','je-veux','randonnee','tu-devrais','choisir','sentier','calme','parce-que','autre','trop','bruyant','a-ta-place','aller','foret','lac']
listen(s,[U],V,['tu-devrais','calme'],'Какое действие рекомендуют?',F,T,['Выбрать тихую тропу','Выбрать шумную тропу','Считать прогулку уже законченной'],0,'Рекомендация — выбрать тихую тропу.','Совет не подтверждает, что прогулка уже состоялась.')
read(s,[A],V,['si-condition','je-veux','randonnee'],'Какое условие относится к совету?',F,T,['Если работа уже отправлена','Если собеседник хочет совершить прогулку','Потому что тропа самая дешёвая'],1,'Условие — желание совершить прогулку.','Причина и цена не заменяют условие. Цена вообще не названа.')
phrase_pairs(s,[U],[('Tu devrais choisir le sentier calme.','тю дёврэ шуазир лё са(н)тье ка́льм','Совет выбрать тихую тропу',['tu-devrais','choisir','sentier','calme'],['tu-devrais']),('Il vaut mieux éviter le sentier bruyant.','иль во мьё зэвитэ лё са(н)тье брюийа́(н)','Рекомендация избегать шумной тропы',['il-vaut-mieux','eviter','sentier','bruyant'],['il-vaut-mieux','eviter']),('À ta place, je choisirais ce sentier.','а та плас, жё шуазирэ сё са(н)тье́','Выбор с позиции на твоём месте',['a-ta-place','choisir','sentier'],['a-ta-place'])],'Три изученные формы совета различены.','Рекомендация не является сообщением об уже выполненном выборе.')
d=DATA[1]
listen(s,[U],d['vs'],['il-vaut-mieux','eviter','bruyant'],'Что рекомендуют не выбирать при желании тишины?',d['f'],d['p'],['Любую прогулку','Тихую тропу','Шумную тропу'],2,'Рекомендуют избегать шумной тропы.','Это конкретный вариант, а не запрет всех прогулок.')
d=DATA[3]
read(s,[A],d['vs'],['parce-que','trop','bruyant'],'Почему дан совет выбрать тихую тропу?',d['f'],d['p'],['Другая тропа слишком шумная','Цена гарантированно самая низкая','Там гарантированно нет людей'],0,'Прямо названа чрезмерная шумность другой тропы.','Цена и количество людей не сообщаются.')
choose(s,'vocabulary',[A],V,['parce-que'],'Выберите объяснение причины, а не условие.',ru('Различите потому что и если.'),[fr('Si tu veux faire une randonnée.','си тю вё фэр юн ра(н)донэ́'),fr('Parce que l’autre sentier est trop bruyant.','парс кё лотр са(н)тье э тро брюийа́(н)'),fr('À ta place.','а та пла́с')],1,'Выбрано объяснение причины.','Условие и позиция на твоём месте не заменяют причину.')
grammar(fill(s,[A],['si-condition','je-veux','randonnee','tu-devrais','choisir','sentier','calme'],[],'После если восстановите изученную форму настоящего времени ты хочешь.','Si tu ','си тю',' faire une randonnée, tu devrais choisir le sentier calme.','фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм',[fr('vouloir','вулуа́р'),fr('veux','вё')],1,'Si tu veux faire une randonnée, tu devrais choisir le sentier calme.','си тю вё фэр юн ра(н)донэ, тю дёврэ шуазир лё са(н)тье ка́льм.','Выбрана личная форма настоящего времени.','Инфинитив не восстанавливает изученную личную форму после подлежащего.'))
order(s,[A],['tu-devrais','choisir','sentier','calme','parce-que','autre','trop','bruyant'],[],'Соберите: тебе стоило бы выбрать тихую тропу, потому что другая слишком шумная. Сначала рекомендация, затем её причина.',[('Tu devrais choisir','тю дёврэ шуази́р'),('le sentier calme','лё са(н)тье ка́льм'),('parce que','парс кё'),('l’autre sentier est trop bruyant.','лотр са(н)тье э тро брюийа́(н)')],'Tu devrais choisir le sentier calme parce que l’autre sentier est trop bruyant.','тю дёврэ шуазир лё са(н)тье кальм парс кё лотр са(н)тье э тро брюийа́(н).','Совет и основание сохранены в заданном порядке.','После рекомендации поставьте связку причины и само основание.')
page(s,[('Функция реплики',['Совет не сообщает, что выбор уже сделан, и не равен запрету. Условие и причина читаются отдельно. Нового обязательного материала нет.'],[W['tu-devrais']['example'],W['il-vaut-mieux']['example']]),('Основание выбора',['В вымышленной ситуации названы желание тишины и шум другой тропы. Цена, реальная безопасность и отсутствие людей из этих сведений не выводятся.'],[W['a-ta-place']['example']])])
W['a-ta-place']['review_items'][0]['prompt']=fr(W['a-ta-place']['example']['fr'],W['a-ta-place']['example']['transcription_ru'])

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
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Простые формы совета, избегание варианта, условие и прямо названная причина в вымышленных языковых ситуациях.',learning_outcomes=['Понимать tu devrais и позицию à ta place.','Отличать рекомендацию избегать варианта от запрета.','Понимать условие совета с si и настоящим временем.','Отличать условие и объяснение причины.','Выбирать и собирать совет с явно названным основанием.'])
P=dict(schema_version=1,package_id='programme-a2-u17-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U17.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U17 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=plan['sessions'][n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('tu-devrais','Отличать совет от готового результата.'),('a-ta-place','Сохранять позицию дающего совет.'),('il-vaut-mieux','Отличать рекомендацию от запрета.'),('eviter','Не подменять избегать действиями выбрать и следовать.'),('si-condition','Отличать условие от причины.'),('parce-que','Сохранять прямо названное основание.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить внешнюю зависимость A2-U16-L05 в целевой базе.','Редактору проверить естественность советов, условной формы choisirais и выбранной формы tu veux; прослушать французскую озвучку, в том числе mieux éviter.','Выполнить предпросмотр и пробное прохождение, измерить время. Эти действия фактически не выполнялись.'],progress=dict(current_unit='A2-U17',prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='A2-U18',next_unit_title='Личные письма'),scope_notes=['Один полный пакет пяти занятий. В каждом 6–8 заданий; L05 — практика, не контроль.','Четыре новых выражения распределены как два, два, ноль, ноль, ноль. Выбранные грамматические формы и новые конструкции объяснены до проверки; L05 нового обязательного материала не вводит.','Tu devrais и je choisirais показаны как конкретные формы, без полной таблицы условного наклонения. Choisir сохраняет прежний ключ и полный объект; новый ключ ради формы не создаётся.','Tu veux — выбранная форма того же значения желания, что прежнее je veux. Сохраняется fr:je-veux:want и полный объект; новая форма объяснена до проверки.','Si и parce que сохраняют прежние ключи. Условие не утверждается само по себе как установленный факт; причина не подменяется условием.','В L01 повторяется названное основание выбора прогулки из A2-U16. В L03 — ход выполнения из A2-U14, в L04 — крайний срок оттуда же. Прежние карточки не изменяются.','Совет в показанных ситуациях не равен запрету, обязанности или выполненному выбору. Il vaut mieux éviter не объявляет закрытие реального маршрута.','Все советы относятся к вымышленным языковым ситуациям. Реальные туристические маршруты, безопасность, медицина, финансы и выбор услуг не рекомендуются.','Цена, отсутствие других людей и реальная безопасность не выводятся из тишины. Основание выбора ограничено словами текста.','Для приблизительной транскрипции mieux éviter выбран связный вариант с з перед гласным. Справочник просмотрен, реальное аудио не прослушивалось.','Озвучка только браузером: fr-FR, asset_id=null. Нет аудиофайлов, внешних сервисов озвучки, свободного текста и обязательного микрофона. Импорт и публикация не выполнялись, принятие пользователем не подтверждено.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Tu devrais; À ta place, je choisirais как выбранная форма.'),dict(session_id=S[1]['id'],material='Il vaut mieux éviter; рекомендация отдельно от запрета.'),dict(session_id=S[2]['id'],material='Si tu veux + действие; настоящее время в условии.'),dict(session_id=S[3]['id'],material='Рекомендация с parce que; причина отдельно от условия.')]
D['linguistic_sources']=[dict(title='Académie française — Liaisons',url='https://www.academie-francaise.fr/questions-de-langue',scope='Общие правила связывания; раздел Liaisons просмотрен. Не является прослушиванием конкретной фразы.')]
(OUT/'A2-U17.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
