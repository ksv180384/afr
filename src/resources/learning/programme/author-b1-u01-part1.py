"""Author B1-U01 part1 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/B1'
A2=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/B1.plan.json').read_text())['level']
plan=level['units'][0]
W={}
for source in [A2/f'A2-U{n:02}.v1.json' for n in range(20,0,-1)]+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='B1.U01.UNDERSTAND';A='B1.U01.APPLY'
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
 s.update(version=1,level='B1',unit_id=plan['id'],description=d,duration_minutes=8,objectives=copy.deepcopy(plan['objectives']),skills=[],vocabulary=[copy.deepcopy(W[w]) for w in vs],tasks=[],introductions=[]); S.append(s); return s

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
word('evenement','fr:evenement:personal-event','un événement','событие личного рассказа','э(н) нэвэнма́(н)',1,ex('C’est un événement de famille.','Это семейное событие.','сэ тэ(н) нэвэнма(н) дё фами́й'),['Событие','Тариф','Место'],0,'Назовите событие, а не сам текст рассказа.',[('Un événement.','э(н) нэвэнма́(н)'),('Un récit.','э(н) рэси́'),('Un tarif.','э(н) тари́ф')],0)
word('recit','fr:recit:personal-narrative','un récit','связный рассказ о событии','э(н) рэси́',1,ex('Un récit : une fête de famille.','Рассказ: семейный праздник.','э(н) рэси: юн фэт дё фами́й'),['Срок','Рассказ','Подарок'],1,'Назовите рассказ как текст, а не событие, о котором он написан.',[('Un événement.','э(н) нэвэнма́(н)'),('Un récit.','э(н) рэси́'),('Un cadeau.','э(н) кадо́')],1)
word('auparavant','fr:auparavant:earlier-relative-time','auparavant','до этого: раньше названного момента','опарава́(н)',2,ex('Auparavant, j’avais déjà préparé le dîner.','До этого я уже приготовил ужин.','опарава(н), жавэ дэжа прэпарэ лё динэ́'),['Затем','В это же время','До этого'],2,'Вернувшийся брат — точка отсчёта. Готовка закончилась раньше. Выберите связку до этого.',[('Ensuite.','а(н)сюи́т'),('Pendant que.','па(н)да(н) кё'),('Auparavant.','опарава́(н)')],2)
for w,g,b in [('evenement','Названо само событие.','Рассказ — текст об этом событии, тариф — другое понятие.'),('recit','Назван рассказ как связный текст.','Событие и подарок не являются названием текста рассказа.'),('auparavant','Событие помещено раньше названного момента.','Затем задаёт последующее время, пока — одновременность, а не предшествование.')]:W[w]['review_items'][1]['feedback']=fb(g,b)

F='Un récit : une fête de famille. Hier, je préparais le dîner à la maison. Pendant que je préparais le dîner, mon frère est rentré avec un cadeau. Ensuite, j’ai fêté mon anniversaire avec ma famille à la maison. Le cadeau est un livre. Ma sœur est disponible vendredi. Je voudrais écrire à ma sœur pour donner des nouvelles. C’est un événement de famille.'
T='э(н) рэси: юн фэт дё фами́й. йэр, жё прэпарэ лё динэ а ла мэзо́(н). па(н)да(н) кё жё прэпарэ лё динэ, мо(н) фрэр э ра(н)трэ авэк э(н) кадо́. а(н)сюит, жэ фэтэ мо(н) нанивэрсэр авэк ма фами́й а ла мэзо́(н). лё кадо э тэ(н) ли́вр. ма сёр э диспонибль ва(н)дрэди́. жё вудрэ экрир а ма сёр пур донэ дэ нувэ́ль. сэ тэ(н) нэвэнма(н) дё фами́й.'
V=['recit','fete','famille','hier','preparer','diner','maison','pendant-que','frere','etre-pc','rentrer','cadeau','ensuite','avoir-pc','feter','anniversaire','livre','soeur','disponible','vendredi','je-voudrais','ecrire','donner-des-nouvelles','evenement']
s=start(1,'Выделите семейное событие и отличите фоновый процесс от отдельного действия.',[])
intro(s,1,'Входная опора на знакомый контраст: Je préparais le dîner — готовка как процесс в прошлом; mon frère est rentré — отдельное событие возвращения. Pendant que связывает их по одновременности. Это не утверждает, что готовка была прервана или уже закончена. Формы прошлого здесь повторяются, новая грамматика предшествования появится после отдельного примера.',['preparer','pendant-que','rentrer'],[('Je préparais le dîner','жё прэпарэ лё динэ́'),('mon frère est rentré','мо(н) фрэр э ра(н)трэ́'),('Pendant que','па(н)да(н) кё')])
choose(s,'grammar',[U],['preparer','diner','pendant-que','frere','etre-pc','rentrer'],['preparer','rentrer'],'Что служит фоном в знакомой модели?',fr('Pendant que je préparais le dîner, mon frère est rentré.','па(н)да(н) кё жё прэпарэ лё динэ, мо(н) фрэр э ра(н)трэ́'),['Готовка ужина','Возвращение брата','Прямо названная отмена готовки'],0,'Готовка — процесс, на фоне которого брат вернулся.','Возвращение — отдельное событие. Прерывание готовки не сообщается.')
intro(s,2,'Un événement — событие; un récit — рассказ об этом событии. Семейный праздник может быть событием рассказа. Заголовок задаёт тему, а действия в тексте раскрывают, что происходило. Не путайте само событие и текст о нём.',['evenement','recit','souvenir'],[('Un événement','э(н) нэвэнма́(н)'),('un récit','э(н) рэси́')])
pairs(s,[U],[('evenement','Событие рассказа'),('recit','Связный рассказ'),('souvenir','Воспоминание')],'Событие, текст и воспоминание различены.','Рассказ — способ сообщить о событии, а не другое название подарка или тарифа.')
listen(s,[U],V,['feter','anniversaire','famille'],'Какое семейное событие составляет основу рассказа?',F,T,['Изменение бронирования','День рождения с семьёй','Отмена рейса'],1,'Рассказ связан с празднованием дня рождения.','Другие бытовые проблемы здесь не названы; не выбирайте по знакомому слову вне этого текста.')
choose(s,'vocabulary',[U],['recit','evenement','tarif'],['recit'],'Выберите название текста, который связывает события в рассказ.',ru('Нужен рассказ, а не событие или цена.'),[fr('Un événement.','э(н) нэвэнма́(н)'),fr('Un tarif.','э(н) тари́ф'),fr('Un récit.','э(н) рэси́')],2,'Назван рассказ как текст.','Событие — то, о чём рассказывают, а не название самого текста.')
choose(s,'reading',[U],V,['pendant-que','preparer','rentrer'],'Что происходило, когда брат вернулся?',fr(F,T),['Автор готовил ужин; конец готовки в этот момент не указан','Автор уже закончил всю готовку, и это прямо сказано','Автор только планирует праздник в будущем'],0,'Возвращение связано с фоном готовки по одновременности.','Форма процесса не подтверждает, что готовка уже закончилась. Будущий план не заменяет описанное прошлое.')
grammar(fill(s,[U],['preparer','diner','pendant-que','frere','etre-pc','rentrer','aller-futur'],[],'Восстановите изученный фон в прошлом.','Pendant que je ','па(н)да(н) кё жё',' le dîner, mon frère est rentré.','лё динэ, мо(н) фрэр э ра(н)трэ́',[fr('vais préparer','вэ прэпарэ́'),fr('préparais','прэпарэ́')],1,'Pendant que je préparais le dîner, mon frère est rentré.','па(н)да(н) кё жё прэпарэ лё динэ, мо(н) фрэр э ра(н)трэ́.','Выбрана форма фонового процесса в прошлом.','Форма будущего плана не восстанавливает этот рассказ.'))
order(s,[U],['pendant-que','preparer','diner','frere','etre-pc','rentrer'],[],'Соберите: пока я готовил ужин, мой брат вернулся. Сначала фон с пока, затем отдельное событие.',[('Pendant que','па(н)да(н) кё'),('je préparais le dîner,','жё прэпарэ лё динэ́'),('mon frère','мо(н) фрэ́р'),('est rentré.','э ра(н)трэ́')],'Pendant que je préparais le dîner, mon frère est rentré.','па(н)да(н) кё жё прэпарэ лё динэ, мо(н) фрэр э ра(н)трэ́.','Фон предшествует сообщению об отдельном событии.','Сохраните одновременность, не добавляя окончания или отмены готовки.')
page(s,[('Событие и рассказ',['Важное семейное событие раскрывается через связанные действия. Рассказ как текст отличается от самого события.'],[W['recit']['example'],W['evenement']['example']]),('Фон и действие',['Готовка показана как процесс, а возвращение — как отдельное событие. Пока задаёт одновременность, не обязательное прерывание.'],[ex('Pendant que je préparais le dîner, mon frère est rentré.','Пока я готовил ужин, мой брат вернулся.','па(н)да(н) кё жё прэпарэ лё динэ, мо(н) фрэр э ра(н)трэ́')])])

G='Un récit : une fête de famille. À huit heures du soir, mon frère est rentré à la maison avec un cadeau. Auparavant, j’avais déjà préparé le dîner avec ma sœur. Ensuite, j’ai fêté mon anniversaire avec ma famille à la maison. Le cadeau est un livre. Je voudrais écrire à ma sœur pour donner des nouvelles. C’est un souvenir de famille.'
H='э(н) рэси: юн фэт дё фами́й. а юи тёр дю суар, мо(н) фрэр э ра(н)трэ а ла мэзо(н) авэк э(н) кадо́. опарава(н), жавэ дэжа прэпарэ лё динэ авэк ма сё́р. а(н)сюит, жэ фэтэ мо(н) нанивэрсэр авэк ма фами́й а ла мэзо́(н). лё кадо э тэ(н) ли́вр. жё вудрэ экрир а ма сёр пур донэ дэ нувэ́ль. сэ тэ(н) сувнир дё фами́й.'
Z=['recit','fete','famille','huit','heure','soir','frere','etre-pc','rentrer','maison','cadeau','auparavant','avoir-pc','deja-experience','preparer','diner','soeur','ensuite','feter','anniversaire','livre','je-voudrais','ecrire','donner-des-nouvelles','souvenir']
s=start(2,'Поймите событие, завершившееся до другого момента в прошлом, и сопоставьте его с фоном.',[])
t=copy.deepcopy(S[0]['tasks'][0]);t['id']='Q01';s['tasks'].append(t)
intro(s,2,'Ясный пример последовательности: сначала я полностью приготовил ужин; затем в восемь часов вернулся брат. Теперь точка отсчёта рассказа — возвращение: Quand mon frère est rentré, j’avais déjà préparé le dîner — «Когда брат вернулся, я уже приготовил ужин». J’avais préparé — выбранная форма plus-que-parfait: приготовление завершено раньше другого момента в прошлом. В этом образце avais соединяется с préparé; полную таблицу спряжения не вводим.',['avoir-pc','preparer','rentrer'],[('Quand mon frère est rentré, j’avais déjà préparé le dîner','ка(н) мо(н) фрэр э ра(н)трэ, жавэ дэжа прэпарэ лё динэ́'),('J’avais préparé','жавэ прэпарэ́'),('plus-que-parfait','плю кё парфэ́'),('avais','авэ́'),('préparé','прэпарэ́')],('Mon frère est rentré à huit heures. J’avais déjà préparé le dîner.','мо(н) фрэр э ра(н)трэ а юи тёр. жавэ дэжа прэпарэ лё динэ́.'))
intro(s,2,'Auparavant — до этого, раньше названного момента. Auparavant, j’avais déjà préparé le dîner повторяет предшествование возвращению брата. Сравните знакомое Pendant que je préparais le dîner, mon frère est rentré: там готовка была фоном, здесь её завершение раньше возвращения сказано прямо. Не переносите состояние готовки из одного рассказа в другой.',['auparavant','deja-experience','pendant-que'],[('Auparavant','опарава́(н)'),('Auparavant, j’avais déjà préparé le dîner','опарава(н), жавэ дэжа прэпарэ лё динэ́'),('Pendant que je préparais le dîner, mon frère est rentré','па(н)да(н) кё жё прэпарэ лё динэ, мо(н) фрэр э ра(н)трэ́')])
phrase_pairs(s,[U],[('Auparavant','опарава́(н)','Раньше названного момента',['auparavant'],['auparavant']),('J’avais déjà préparé le dîner.','жавэ дэжа прэпарэ лё динэ́','К тому моменту ужин уже был приготовлен.',['avoir-pc','deja-experience','preparer','diner'],['preparer']),('Je préparais le dîner.','жё прэпарэ лё динэ́','Готовка как процесс в прошлом',['preparer','diner'],['preparer'])],'Предшествующее завершение отделено от процесса в прошлом.','Уже приготовил к тому моменту не равно был в процессе приготовления.')
listen(s,[U],Z,['auparavant','preparer','rentrer'],'В этом новом рассказе что раньше: завершение готовки или возвращение брата?',G,H,['Брат вернулся раньше завершения готовки','Готовка уже завершилась раньше возвращения','Порядок нигде не указан'],1,'В новом рассказе ужин уже приготовлен к моменту возвращения.','Не переносите фоновую готовку из предыдущей версии: здесь завершение названо прямо.')
choose(s,'vocabulary',[U],['auparavant','ensuite','pendant-que'],['auparavant'],'Нужно указать раньше уже названного момента в прошлом.',ru('Выберите связку предшествования.'),[fr('Pendant que','па(н)да(н) кё'),fr('Ensuite','а(н)сюи́т'),fr('Auparavant','опарава́(н)')],2,'Связка относит событие к более раннему моменту.','Затем задаёт последующее время, пока — одновременность.')
choose(s,'reading',[U],Z,['auparavant','preparer','rentrer','feter','anniversaire'],'Какой порядок действий следует из нового рассказа?',fr(G,H),['Ужин приготовлен; брат вернулся; затем праздновали день рождения','Сначала праздновали, затем начали готовить после ухода брата','Готовка обязательно была прервана возвращением'],0,'Завершение готовки предшествует возвращению, а празднование следует затем.','Прерывание не сообщается. Более раннее событие определяется не только порядком предложений, но и формой с явной связкой.')
grammar(fill(s,[U],['avoir-pc','preparer','deja-experience','diner'],[],'К моменту возвращения ужин уже был приготовлен. Восстановите изученную форму предшествующего завершения.','Auparavant, ','опарава́(н)',' déjà préparé le dîner.','дэжа прэпарэ лё динэ́',[fr('j’avais','жавэ́'),fr('j’ai','жэ')],0,'Auparavant, j’avais déjà préparé le dîner.','опарава(н), жавэ дэжа прэпарэ лё динэ́.','Форма указывает завершение до другого момента в прошлом.','Составное прошлое само по себе не восстанавливает требуемую здесь выбранную форму предпрошедшего.'))
order(s,[U],['avoir-pc','deja-experience','preparer','diner','frere','etre-pc','rentrer'],[],'Соберите: когда мой брат вернулся, я уже приготовил ужин. Сначала точка отсчёта в прошлом, затем более раннее завершение.',[('Quand mon frère','ка(н) мо(н) фрэ́р'),('est rentré,','э ра(н)трэ́'),('j’avais déjà','жавэ дэжа́'),('préparé le dîner.','прэпарэ лё динэ́')],'Quand mon frère est rentré, j’avais déjà préparé le dîner.','ка(н) мо(н) фрэр э ра(н)трэ, жавэ дэжа прэпарэ лё динэ́.','Точка отсчёта и предшествующее завершение различены.','После возвращения как точки отсчёта поставьте сообщение об уже завершённой готовке.')
page(s,[('Два прошлых момента',['Сначала ужин был полностью приготовлен; затем вернулся брат. В выбранной форме предпрошедшего завершение отнесено к более раннему моменту.'],[ex('Quand mon frère est rentré, j’avais déjà préparé le dîner.','Когда мой брат вернулся, я уже приготовил ужин.','ка(н) мо(н) фрэр э ра(н)трэ, жавэ дэжа прэпарэ лё динэ́')]),('Не путать с фоном',['Процесс в прошлом не подтверждает завершения. В новом рассказе более раннее завершение названо явно; порядок предложений не отменяет этой временной связи.'],[W['auparavant']['example']])])

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
unit=dict(id=plan['id'],level='B1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Личный рассказ: важное событие, фон и действие, завершённое до другого момента в прошлом. Первая часть темы.',learning_outcomes=['Различать основное событие и фоновый процесс в личном рассказе.','Понимать выбранную модель plus-que-parfait для завершения до другого момента в прошлом.'])
metadata=dict(code=level['code'],title=level['title'],summary=level['summary'],learning_outcomes=['Понимать связные рассказы о знакомых событиях и личном опыте.','Выбирать реплики для объяснения мнения и решения знакомых проблем.','Собирать связные сообщения из готовых блоков, различая последовательность, причины и последствия.'],pronunciation_notes=[dict(fr='B1',transcription_ru='бэ э́(н)')])
P=dict(schema_version=1,package_id='programme-b1-u01-part1-v1',language_pair='fr-ru',levels=[metadata],units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'B1-U01.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('B1-U01 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']],status='partial' if o==U else 'pending_L03_L05') for o in [U,A]]
D=dict(unit_id=plan['id'],package_id=P['package_id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('recit','Вернуть связность рассказа при сообщении о договорённостях.'),('auparavant','Отличать предшествующий момент от последующего.'),('ensuite','Сохранять порядок действий.'),('pendant-que','Отличать одновременный фон от предшествующего завершения.'),('disponible','Повторить доступность по времени в договорённостях.')]],
 review_targets_for_remaining_sessions=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('evenement','Вернуть событие как причину реакции.'),('recit','Соединить событие, фон, реакцию и вывод.'),('auparavant','Сохранить явно названное предшествование.'),('souvenir','Повторить воспоминание отдельно от события и текста.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False,duration_measured=False,import_performed=False),
 open_issues=['Этап не завершён: подготовить L03–L05 после команды «Продолжи»; цель реакции, чувств и вывода пока не покрыта.','Редактору проверить естественность рассказов, приблизительные транскрипции и ясность различия процесса и предшествующего завершения.','Прослушать браузерную озвучку événement, récit, auparavant и моделей passé composé, imparfait, plus-que-parfait. Длительность аудио и занятий не измерена.','Выполнить предпросмотр и пробное прохождение на сайте. Импорт и принятие пользователем не подтверждены.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[x['id'] for x in plan['sessions'][2:]],next_unit_not_started='B1-U02',next_unit_title='Договорённости',previous_unit='A2-U20',previous_unit_import_confirmed=False),
 scope_notes=['Частичный пакет из двух законченных занятий текущей темы; отдельный package_id. Не заявляется завершение всей темы или освоение предыдущего уровня учеником.','В первом занятии два новых значения, во втором одно. Нагрузка ограничена одним временным различием; дополнительные обязательные слова ради численной квоты не вводятся.','В обоих занятиях повторяются прежние семейные события, приготовление ужина, последовательность, passé composé и imparfait. В L02 возвращается различие фона и события из L01.','Plus-que-parfait вводится только в L02 после ясной последовательности: сначала ужин полностью приготовлен, затем вернулся брат. Выбранная форма j’avais préparé не требует отдельного lexeme_key для формы глагола.','Pendant que задаёт одновременность процесса и события; прерывание и завершение из этого не выводятся. Auparavant относит действие к более раннему моменту.','Полные прежние карточки, их ключи и review_items сохранены. Прежняя déjà возвращается в общем значении уже до указанного момента; отдельного ключа ради грамматического времени не создаётся. Узкий пример учебного опыта из прежней карточки сохранён.','Материал se rendre compte, éprouver и grâce à, реакции и выводы отложены до L03–L04. L05 будет повторением без нового обязательного материала.','Обязательные ответы только выбором и сборкой готовых блоков. Нет свободного текста или обязательного устного ответа; апострофы в сборках не разорваны.','Озвучка через браузер: fr-FR, asset_id=null. Нет аудиофайлов и внешних сервисов.','Проверка базы выявила B1 с title=B1, summary=null, learning_outcomes=null, revision=0. Подготовлено описание уровня в levels. База, прежние пакеты, реестр и curricula не изменялись.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Повторение passé composé / imparfait: событие и фон; одновременность не доказывает прерывание.'),dict(session_id=S[1]['id'],material='Выбранная j’avais déjà préparé: завершение раньше другого прошлого момента; auparavant; сравнение с фоновым процессом.')]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['text_conventions']=dict(situation_status='Оригинальные вымышленные семейные рассказы; два отдельных варианта временной связи, факты не переносятся между ними.',external_services_required=False)
D['catalog_metadata']=dict(levels_included=True,curricula_included=False,database_modified=False,read_only_database_check=dict(code='B1',title='B1',summary=None,learning_outcomes=None,revision=0))
(OUT/'B1-U01.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))

