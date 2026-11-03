"""Author PRE_A1-U13 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][12]
W={}
for source in [OUT/f'PRE_A1-U{n:02}.v1.json' for n in range(12,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='PRE_A1.U13.UNDERSTAND';A='PRE_A1.U13.APPLY'
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

def phrase_pairs(s,gs,rows,g,b):
 vs=list(dict.fromkeys(w for f,p,r,ws,av in rows for w in ws)); avs=list(dict.fromkeys(w for f,p,r,ws,av in rows for w in av))
 t=base(s,'vocabulary',gs,vs,avs,'Соедините реплику или выражение с его смыслом.',ru('Используйте каждую карточку один раз.'),g,b)
 t.update(type='matching_pairs',left_items=[dict(id=f'l{i}',**fr(f,p)) for i,(f,p,r,ws,av) in enumerate(rows)],right_items=[dict(id=f'r{i}',**ru(r)) for i,(f,p,r,ws,av) in reversed(list(enumerate(rows)))],correct_pairs=[dict(left_id=f'l{i}',right_id=f'r{i}',assessed_vocabulary_ids=av) for i,(f,p,r,ws,av) in enumerate(rows)])
 return t

def addword(i,key,f,r,p,e,n,reviews):
 W[i]=dict(id=i,lexeme_key=key,fr=f,ru=[r],transcription_ru=p,example=e,review_items=reviews);FIRST[i]=n
DAYS=[('lundi','понедельник','лё(н)ди́',1),('mardi','вторник','марди́',1),('mercredi','среда','мэркрёди́',1),('jeudi','четверг','жёди́',2),('vendredi','пятница','ва(н)дрэди́',2),('samedi','суббота','самди́',2),('dimanche','воскресенье','дима́(н)ш',2)]
for idx,(i,r,p,n) in enumerate(DAYS):
 candidates=[i]+[x[0] for x in DAYS[:3] if x[0]!=i][:2]
 pos=idx%3;candidates.insert(pos,candidates.pop(0))
 labels={x[0]:(x[1],x[2]) for x in DAYS}
 addword(i,'fr:'+i+':weekday',i,r,p,ex(i.capitalize()+'.',r.capitalize()+' (краткий ответ о дне).',p),n,[rev(i+'-meaning',fr(i.capitalize(),p),[labels[c][0].capitalize() for c in candidates],pos,'Назван нужный день недели.','Название означает «'+r+'»; два других названия относятся к другим дням.'),rev(i+'-use',ru('В условии нужно назвать день «'+r+'». Выберите краткий ответ о дне встречи.'),[fr(c.capitalize()+'.',labels[c][1]) for c in reversed(candidates)],2-pos,'Выбрано название дня из условия.','Нужно название «'+r+'»; остальные варианты меняют день встречи.')])
addword('aujourdhui','fr:aujourdhui:today','aujourd’hui','сегодня','ожурдюи́',ex('Aujourd’hui : lundi.','Сегодня — понедельник.','ожурдюи лё(н)ди́'),3,[rev('aujourdhui-meaning',fr('Aujourd’hui : lundi.','ожурдюи лё(н)ди́'),['Сегодня — понедельник','Завтра — понедельник','Назван только понедельник, без указания на сегодня'],0,'День назван сегодняшним.','Начальное слово означает «сегодня», а не завтра; оно уточняет связь дня с текущим днём в условии.'),rev('aujourdhui-use',ru('В условии нужно поставить метку «сегодня» рядом с понедельником. Какую подпись выбрать?'),[fr('Demain : lundi.','дёмэ(н) лё(н)ди́'),fr('Aujourd’hui : lundi.','ожурдюи лё(н)ди́'),fr('Lundi.','лё(н)ди́')],1,'Подпись прямо указывает «сегодня».','Нужна метка сегодняшнего дня; просто название дня и метка завтрашнего дня не подходят.')])
addword('demain','fr:demain:tomorrow','demain','завтра','дёмэ́(н)',ex('Demain : mardi.','Завтра — вторник.','дёмэ(н) марди́'),3,[rev('demain-meaning',fr('Demain : mardi.','дёмэ(н) марди́'),['Сегодня — вторник','Назван только вторник','Завтра — вторник'],2,'День назван завтрашним.','Начальное слово означает «завтра», а не сегодня; оно добавляет временную метку к дню недели.'),rev('demain-use',ru('Выберите короткий ответ, который означает именно «завтра».'),[fr('Demain.','дёмэ́(н)'),fr('Aujourd’hui.','ожурдюи́'),fr('Mardi.','марди́')],0,'Выбрано слово «завтра».','Нужно слово «завтра»; сегодня и название вторника не имеют того же значения.')])
addword('rendez-vous','fr:rendez-vous:appointment','un rendez-vous','назначенная встреча','э(н) ра(н)дэ-ву́',ex('Rendez-vous demain.','Встреча завтра (краткая запись).','ра(н)дэ-ву дёмэ́(н)'),3,[rev('rendez-vous-meaning',fr('Un rendez-vous','э(н) ра(н)дэ-ву́'),['Назначенная встреча','Завтрашний день','Название воскресенья'],0,'Это название назначенной встречи.','Правильный смысл — назначенная встреча; слово само по себе не называет день.'),rev('rendez-vous-use',ru('Нужна краткая запись «Встреча завтра». Какую выбрать?'),[fr('Demain : mardi.','дёмэ(н) марди́'),fr('Aujourd’hui : lundi.','ожурдюи лё(н)ди́'),fr('Rendez-vous demain.','ра(н)дэ-ву дёмэ́(н)')],2,'Краткая запись сообщает о встрече завтра.','Нужно назвать встречу; подписи только с днями недели её не называют.')])
addword('hier','fr:hier:yesterday','hier','вчера','йэ́р',ex('Hier : dimanche.','Вчера — воскресенье (подпись в календаре).','йэр дима́(н)ш'),4,[rev('hier-meaning',fr('Hier : dimanche.','йэр дима́(н)ш'),['Завтра — воскресенье','Вчера — воскресенье','Сегодня — воскресенье'],1,'Это подпись о вчерашнем дне.','Начальное слово означает «вчера»; не заменяйте его сегодняшним или завтрашним днём.'),rev('hier-use',ru('Нужно подписать вчерашний день, а не рассказать о действии в прошлом. Какую метку выбрать?'),[fr('Demain','дёмэ́(н)'),fr('Aujourd’hui','ожурдюи́'),fr('Hier','йэ́р')],2,'Выбрана метка вчерашнего дня.','Правильная метка — «вчера»; остальные относятся к сегодня и завтра.')])
addword('quand','fr:quand:when-question','quand','когда (вопрос о времени)','ка́(н)',ex('Rendez-vous quand ?','Встреча когда?','ра(н)дэ-ву ка́(н)'),4,[rev('quand-meaning',fr('Quand ?','ка́(н)'),['Когда?','Завтра.','Вчера.'],0,'Это вопрос о времени.','Слово задаёт вопрос «когда?», а не называет вчера или завтра.'),rev('quand-use',ru('День встречи ещё не назван. Как кратко спросить, когда встреча?'),[fr('Rendez-vous demain.','ра(н)дэ-ву дёмэ́(н)'),fr('Rendez-vous quand ?','ра(н)дэ-ву ка́(н)'),fr('Rendez-vous lundi.','ра(н)дэ-ву лё(н)ди́')],1,'Реплика спрашивает о времени встречи.','Нужен вопрос; два других варианта уже сообщают день встречи.')])

def daylist(ids): return [wc(x) for x in ids]
def calendar(s,tasknum,ids,text):
 intro(s,tasknum,text,ids,[(W[x]['fr'],W[x]['transcription_ru']) for x in ids],(' ; '.join(W[x]['fr'] for x in ids),' ; '.join(W[x]['transcription_ru'] for x in ids)))

s=start(1,'Узнавайте понедельник, вторник и среду на слух и в коротком списке. Повторите знакомое действие чтения.',['lundi','mardi','mercredi','elle','lire','livre'])
choose(s,'reading',['PRE_A1.U12.UNDERSTAND'],['elle','lire','livre'],['lire'],'Что делает человек в знакомой реплике?',fr('Elle lit un livre.','эль ли э(н) ли́вр.'),['Пишет слово','Читает книгу','Пьёт воду'],1,'Она читает книгу.','Нужно чтение книги; письмо и питьё этой репликой не названы.')
calendar(s,2,['lundi','mardi','mercredi'],'Первые три дня нашей недели: lundi — понедельник, mardi — вторник, mercredi — среда. Запомните этот порядок. Названия дней пишутся со строчной буквы; в начале отдельного ответа возможна прописная. В коротком ответе о конкретном дне достаточно одного названия.')
pairs(s,[U],[('lundi','Понедельник'),('mardi','Вторник'),('mercredi','Среда')],'Вы различили первые три дня недели.','Соедините названия с точными значениями: понедельник, вторник и среда.')
listen(s,[U],['mardi','lundi','mercredi'],['mardi'],'Какой день назван?','Mardi.','марди́.',['Среда','Понедельник','Вторник'],2,'Назван вторник.','В аудио звучит название вторника; понедельник и среда называются иначе.')
choose(s,'vocabulary',[U],['lundi','mardi','mercredi'],['mercredi'],'В условии встреча назначена на среду. Выберите краткий ответ о дне.',ru('Нужен именно день из условия.'),daylist(['mardi','mercredi','lundi']),1,'Выбрано название среды.','Нужно название среды; остальные варианты меняют назначенный день.')
choose(s,'reading',[U],['lundi','mardi','mercredi'],['lundi'],'В записи перечислены дни. Какой из них означает понедельник?',fr('mardi ; lundi ; mercredi','марди́; лё(н)ди́; мэркрёди́'),daylist(['mercredi','lundi','mardi']),1,'Название понедельника найдено в записи.','Понедельник — нужное название, даже если в записи дни стоят не по порядку.')
fill(s,[U],['lundi','mardi','mercredi'],['mardi'],'Восстановите изученный порядок трёх дней.','lundi, ','лё(н)ди́',', mercredi','мэркрёди́',daylist(['mercredi','mardi','lundi']),1,'lundi, mardi, mercredi','лё(н)ди́, марди́, мэркрёди́','Между понедельником и средой поставлен вторник.','В показанном порядке после понедельника перед средой стоит вторник.')
order(s,[U],['lundi','mardi','mercredi'],[],'Соберите список первых трёх дней недели, начиная с понедельника.',[('lundi,','лё(н)ди́'),('mardi,','марди́'),('mercredi.','мэркрёди́')],'lundi, mardi, mercredi.','лё(н)ди́, марди́, мэркрёди́.','Получился изученный порядок трёх дней.','Начните с понедельника, затем поставьте вторник и среду.')
page(s,[('Три первых дня',['Запоминайте название каждого дня вместе с его звучанием. Порядок в учебном списке начинается с понедельника.'],[ex('lundi, mardi, mercredi','понедельник, вторник, среда','лё(н)ди́, марди́, мэркрёди́')]),('Краткий ответ о дне',['Если в условии спрашивают о конкретном дне встречи, можно выбрать одно название дня. Это не сообщение о действии и не рассказ о прошлом.'],[W['lundi']['example'],W['mercredi']['example']]),('Написание названий',['В списке названия дней начинаются со строчной буквы. В начале отдельного ответа возможна прописная. При выборе ответа ничего печатать не нужно.'],[W['mardi']['example']])])
s=start(2,'Добавьте четверг, пятницу, субботу и воскресенье. Узнавайте все семь названий и восстанавливайте показанный порядок.',['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'])
choose(s,'reading',[U],['lundi','mardi','mercredi'],['mercredi'],'Какой день назван в знакомой записи?',fr('Mercredi.','мэркрёди́.'),['Вторник','Среда','Понедельник'],1,'Названа среда.','Правильное значение — среда; понедельник и вторник называются иначе.')
calendar(s,2,['jeudi','vendredi'],'После среды идут jeudi — четверг и vendredi — пятница. Слушайте название целиком: название пятницы отличается от уже знакомого названия среды.')
pairs(s,[U],[('mercredi','Среда'),('jeudi','Четверг'),('vendredi','Пятница')],'Вы различили среду, четверг и пятницу.','Сопоставьте целые названия: похожее окончание не означает одинаковый день.')
listen(s,[U],['jeudi','mercredi','vendredi'],['jeudi'],'Какой день назван?','Jeudi.','жёди́.',['Пятница','Среда','Четверг'],2,'Назван четверг.','В аудио — четверг; среда и пятница имеют другие названия.')
calendar(s,4,['samedi','dimanche'],'Завершают неделю samedi — суббота и dimanche — воскресенье. В нашем учебном порядке после пятницы стоит суббота, затем воскресенье; после воскресенья снова начинается понедельник. Это порядок недельного цикла, а не дата на вашем устройстве.')
pairs(s,[U],[('jeudi','Четверг'),('vendredi','Пятница'),('samedi','Суббота'),('dimanche','Воскресенье')],'Вы сопоставили четыре новых дня с их значениями.','Различайте четверг, пятницу, субботу и воскресенье: каждому названию соответствует один день.')
choose(s,'reading',[U],['samedi','dimanche','vendredi'],['dimanche'],'В записке перечислены дни в произвольном порядке. Какой день записан между субботой и пятницей?',fr('samedi ; dimanche ; vendredi','самди́; дима́(н)ш; ва(н)дрэди́'),['Четверг','Воскресенье','Понедельник'],1,'В этой записке между субботой и пятницей стоит воскресенье.','Читайте именно записку: между двумя указанными названиями записано воскресенье; порядок здесь произвольный.')
fill(s,[U],['vendredi','samedi','dimanche','jeudi'],['samedi'],'Восстановите обычный порядок в конце учебной недели.','vendredi, ','ва(н)дрэди́',', dimanche','дима́(н)ш',daylist(['jeudi','dimanche','samedi']),2,'vendredi, samedi, dimanche','ва(н)дрэди́, самди́, дима́(н)ш','Между пятницей и воскресеньем поставлена суббота.','В изученном недельном порядке после пятницы перед воскресеньем стоит суббота.')
order(s,[U],['jeudi','vendredi','samedi','dimanche'],[],'Соберите четыре последних дня учебной недели по порядку, начиная с четверга.',[('jeudi,','жёди́'),('vendredi,','ва(н)дрэди́'),('samedi,','самди́'),('dimanche.','дима́(н)ш')],'jeudi, vendredi, samedi, dimanche.','жёди́, ва(н)дрэди́, самди́, дима́(н)ш.','Получился изученный порядок последних четырёх дней.','После четверга поставьте пятницу, затем субботу и воскресенье.')
page(s,[('После среды',['Добавьте четверг и пятницу к трём уже знакомым названиям. Слушайте всё слово, особенно если два названия кажутся похожими.'],[ex('mercredi, jeudi, vendredi','среда, четверг, пятница','мэркрёди́, жёди́, ва(н)дрэди́')]),('Конец недели',['После пятницы идут суббота и воскресенье. Затем в нашем учебном цикле снова начинается понедельник.'],[ex('vendredi, samedi, dimanche','пятница, суббота, воскресенье','ва(н)дрэди́, самди́, дима́(н)ш'),ex('dimanche, lundi','воскресенье, понедельник','дима́(н)ш, лё(н)ди́')]),('Название и место в записке',['В задании может быть произвольный список дней. Если спрашивают о содержании записки, читайте её порядок; если о недельном цикле, используйте объяснённую последовательность.'],[W['jeudi']['example'],W['dimanche']['example']])])

s=start(3,'Различайте сегодня и завтра в коротких календарных подписях. Выбирайте день назначенной встречи по готовой записи.',['aujourdhui','demain','rendez-vous','lundi','mardi','jeudi','vendredi','jaime','fromage'])
choose(s,'reading',['PRE_A1.U10.APPLY'],['jaime','fromage'],['jaime'],'Какой смысл у знакомой реплики о еде?',fr('J’aime le fromage.','жэм лё фрома́ж.'),['Сыр нравится','Просит передать сыр','Сыр не нравится'],0,'Говорящий сообщает, что сыр нравится.','Здесь предпочтение, а не просьба и не отрицание.')
intro(s,2,'Aujourd’hui — «сегодня», demain — «завтра». Читаем краткие календарные подписи: Aujourd’hui : lundi. — «Сегодня — понедельник», Demain : mardi. — «Завтра — вторник». Двоеточие отделяет метку времени от названия дня. Все нужные данные даны в условии; текущая дата вашего устройства не нужна. Слово с апострофом сохраняйте целиком.',['aujourdhui','demain'],[('Aujourd’hui','ожурдюи́'),('demain','дёмэ́(н)'),('Aujourd’hui : lundi.','ожурдюи лё(н)ди́'),('Demain : mardi.','дёмэ(н) марди́')],('Aujourd’hui : lundi. Demain : mardi.','ожурдюи лё(н)ди́. дёмэ(н) марди́.'))
phrase_pairs(s,[A,U],[('Aujourd’hui : lundi.','ожурдюи лё(н)ди́.','Сегодня — понедельник.',['aujourdhui','lundi'],['aujourdhui','lundi']),('Demain : mardi.','дёмэ(н) марди́.','Завтра — вторник.',['demain','mardi'],['demain','mardi']),('Mardi.','марди́.','Вторник: без уточнения «сегодня» или «завтра».',['mardi'],['mardi'])],'Вы различили метки времени и отдельное название дня.','Нужно сохранить всю подпись: метка сообщает сегодня или завтра, название указывает день недели.')
listen(s,[A,U],['aujourdhui','demain','vendredi','jeudi'],['aujourdhui','vendredi'],'Какой смысл у календарной подписи?','Aujourd’hui : vendredi.','ожурдюи ва(н)дрэди́.',['Завтра — пятница','Сегодня — четверг','Сегодня — пятница'],2,'Подпись означает, что сегодня пятница.','Сохраните оба элемента: сегодня и пятница; замена любого из них меняет подпись.')
intro(s,4,'Un rendez-vous — «назначенная встреча». В краткой записи перед меткой времени используем название без артикля: Rendez-vous demain. — «Встреча завтра». Можно уточнить день: Rendez-vous demain, vendredi. — «Встреча завтра, в пятницу». Это готовые короткие записи; новое время глагола не требуется.',['rendez-vous'],[('Un rendez-vous','э(н) ра(н)дэ-ву́'),('Rendez-vous demain.','ра(н)дэ-ву дёмэ́(н)'),('Rendez-vous demain, vendredi.','ра(н)дэ-ву дёмэ(н), ва(н)дрэди́')],('Rendez-vous demain, vendredi.','ра(н)дэ-ву дёмэ(н), ва(н)дрэди́.'))
choose(s,'vocabulary',[A],['rendez-vous','demain','aujourdhui','vendredi','lundi'],['rendez-vous','demain'],'Нужна краткая запись «Встреча завтра». Что выбрать?',ru('Сохраните название события и время из условия.'),[fr('Aujourd’hui : lundi.','ожурдюи лё(н)ди́.'),fr('Rendez-vous demain.','ра(н)дэ-ву дёмэ́(н).'),fr('Demain : vendredi.','дёмэ(н) ва(н)дрэди́.')],1,'Запись прямо сообщает о встрече завтра.','Нужно назвать встречу и завтра; остальные подписи говорят только о днях.')
choose(s,'reading',[A,U],['aujourdhui','jeudi','rendez-vous','demain','vendredi'],['demain','vendredi'],'В какой день назначена встреча по записке?',fr('Aujourd’hui : jeudi. Rendez-vous demain, vendredi.','ожурдюи жёди́. ра(н)дэ-ву дёмэ(н), ва(н)дрэди́.'),['Сегодня, в четверг','Завтра, в пятницу','В записке день встречи не указан'],1,'Встреча назначена завтра, в пятницу.','Четверг обозначает сегодня в условии; запись о встрече прямо указывает завтра и пятницу.')
fill(s,[A],['rendez-vous','demain','aujourdhui','vendredi'],['demain'],'Дополните запись «Встреча завтра, в пятницу».','Rendez-vous ','ра(н)дэ-ву́',', vendredi.','ва(н)дрэди́',daylist(['aujourdhui','demain']),1,'Rendez-vous demain, vendredi.','ра(н)дэ-ву дёмэ(н), ва(н)дрэди́.','Выбрана метка завтрашнего дня.','По условию встреча завтра; метка сегодняшнего дня меняет её время.')
order(s,[A],['rendez-vous','demain','vendredi'],[],'Соберите «Встреча завтра, в пятницу». Начните с названия встречи, затем поставьте метку «завтра» и уточнение дня.',[('Rendez-vous','ра(н)дэ-ву́'),('demain,','дёмэ́(н)'),('vendredi.','ва(н)дрэди́')],'Rendez-vous demain, vendredi.','ра(н)дэ-ву дёмэ(н), ва(н)дрэди́.','Получилась нужная краткая запись о встрече.','Начните с названия встречи, затем поставьте завтра и пятницу.')
page(s,[('Сегодня и завтра',['Календарные подписи называют день относительно данных условия. Не используйте фактическую дату своего устройства для решения задания.'],[W['aujourdhui']['example'],W['demain']['example']]),('Краткая запись о встрече',['В словарном примере существительное показано с артиклем. В изученной краткой записи перед временем оно стоит без артикля.'],[W['rendez-vous']['example'],ex('Rendez-vous demain, vendredi.','Встреча завтра, в пятницу.','ра(н)дэ-ву дёмэ(н), ва(н)дрэди́')]),('Читаем всю записку',['Отдельная подпись о сегодняшнем дне ещё не называет время встречи. Найдите запись о встрече и сохраните её метку и день.'],[ex('Aujourd’hui : jeudi. Rendez-vous demain, vendredi.','Сегодня — четверг. Встреча завтра, в пятницу.','ожурдюи жёди́. ра(н)дэ-ву дёмэ(н), ва(н)дрэди́')])])
s=start(4,'Узнавайте вчерашний день в календарной подписи. Спросите о времени встречи коротким готовым вопросом.',['hier','quand','aujourdhui','demain','rendez-vous','lundi','mardi','mercredi','jeudi','vendredi','dimanche','je-naime-pas','pain'])
choose(s,'reading',['PRE_A1.U10.APPLY'],['je-naime-pas','pain'],['je-naime-pas'],'Что сообщает знакомая реплика о предпочтении?',fr('Je n’aime pas le pain.','жё нэм па лё пэ́(н).'),['Просит хлеба','Хлеб нравится','Хлеб не нравится'],2,'Говорящий сообщает, что хлеб не нравится.','Отрицание показывает, что хлеб не нравится; это не просьба получить его.')
intro(s,2,'Hier — «вчера». Читаем подписи: Aujourd’hui : lundi. — «Сегодня — понедельник», Hier : dimanche. — «Вчера — воскресенье». Вчерашний день стоит перед сегодняшним; в недельном цикле перед понедельником — воскресенье. Здесь только подписи дней без глагола: рассказ о действиях в прошлом пока не составляем.',['hier'],[('Hier','йэ́р'),('Aujourd’hui : lundi.','ожурдюи лё(н)ди́'),('Hier : dimanche.','йэр дима́(н)ш')],('Aujourd’hui : lundi. Hier : dimanche.','ожурдюи лё(н)ди́. йэр дима́(н)ш.'))
phrase_pairs(s,[A],[('Hier : lundi.','йэр лё(н)ди́.','Вчера — понедельник.',['hier','lundi'],['hier']),('Aujourd’hui : mardi.','ожурдюи марди́.','Сегодня — вторник.',['aujourdhui','mardi'],['aujourdhui']),('Demain : mercredi.','дёмэ(н) мэркрёди́.','Завтра — среда.',['demain','mercredi'],['demain'])],'Вы различили вчера, сегодня и завтра.','Сохраните начальную временную метку: она показывает, до сегодняшнего дня, в сам этот день или после него указан день недели.')
listen(s,[A,U],['hier','aujourdhui','vendredi','jeudi'],['hier','vendredi'],'Что означает календарная подпись?','Hier : vendredi.','йэр ва(н)дрэди́.',['Сегодня — пятница','Вчера — пятница','Вчера — четверг'],1,'Подпись указывает, что вчера была пятница.','Метка означает вчера, а названный день — пятница. Сегодня и четверг не соответствуют подписи.')
intro(s,4,'Quand ? — «Когда?» Для короткого вопроса о дне встречи используем Rendez-vous quand ? — «Встреча когда?» Ответ может называть метку времени и день: Aujourd’hui, lundi. — «Сегодня, в понедельник» или Demain, mardi. — «Завтра, во вторник». Это готовый короткий обмен репликами; день определяется ответом.',['quand'],[('Quand ?','ка́(н)'),('Rendez-vous quand ?','ра(н)дэ-ву ка́(н)'),('Aujourd’hui, lundi.','ожурдюи, лё(н)ди́'),('Demain, mardi.','дёмэ(н), марди́')],('Rendez-vous quand ? Demain, mardi.','ра(н)дэ-ву ка́(н)? дёмэ(н), марди́.'))
choose(s,'vocabulary',[A],['rendez-vous','quand','demain','hier','lundi'],['quand'],'День встречи ещё не назван. Как спросить, когда встреча?',ru('Нужен вопрос, а не сообщение уже выбранного дня.'),[fr('Rendez-vous demain.','ра(н)дэ-ву дёмэ́(н).'),fr('Rendez-vous lundi.','ра(н)дэ-ву лё(н)ди́.'),fr('Rendez-vous quand ?','ра(н)дэ-ву ка́(н)?')],2,'Выбран вопрос о времени встречи.','Нужен вопрос «когда?»; другие реплики уже называют время встречи.')
choose(s,'reading',[A,U],['hier','dimanche','aujourdhui','lundi','rendez-vous','demain','mardi'],['hier','dimanche'],'Какой день указан как вчерашний?',fr('Hier : dimanche. Aujourd’hui : lundi. Rendez-vous demain, mardi.','йэр дима́(н)ш. ожурдюи лё(н)ди́. ра(н)дэ-ву дёмэ(н), марди́.'),['Понедельник','Воскресенье','Вторник'],1,'Вчерашним днём в записке названо воскресенье.','Найдите метку вчерашнего дня: рядом с ней воскресенье. Понедельник указан как сегодня, вторник — как день завтрашней встречи.')
fill(s,[A,U],['hier','dimanche','lundi','mardi'],['hier','dimanche'],'По условию сегодня понедельник. Выберите название вчерашнего дня в недельном цикле.','Hier : ','йэ́р','','',daylist(['lundi','dimanche','mardi']),1,'Hier : dimanche','йэр дима́(н)ш.','Перед понедельником в недельном цикле — воскресенье.','Нужен день перед понедельником: воскресенье. Вторник следует после понедельника, а не перед ним.')
order(s,[A],['rendez-vous','aujourdhui','lundi'],[],'Соберите «Встреча сегодня, в понедельник». Начните с названия встречи, затем поставьте метку «сегодня» и день.',[('Rendez-vous','ра(н)дэ-ву́'),('aujourd’hui,','ожурдюи́'),('lundi.','лё(н)ди́')],'Rendez-vous aujourd’hui, lundi.','ра(н)дэ-ву ожурдюи, лё(н)ди́.','Получилась краткая запись о сегодняшней встрече.','Поставьте название встречи первым, затем сегодня и понедельник.')
page(s,[('Метка вчерашнего дня',['В этой теме вчера обозначается календарной подписью без глагола. Форма рассказа о действии в прошлом не вводится.'],[W['hier']['example'],ex('Aujourd’hui : lundi.','Сегодня — понедельник.','ожурдюи лё(н)ди́')]),('Вопрос о времени',['Короткий разговорный вопрос о встрече можно составить из названия встречи и вопросительного слова. Ответ называет нужное время или день.'],[W['quand']['example'],ex('Demain, mardi.','Завтра, во вторник.','дёмэ(н), марди́')]),('Переход через границу недели',['В нашем цикле перед понедельником стоит воскресенье. Для решения достаточно условия и известного порядка дней; настоящий календарь не нужен.'],[ex('Hier : dimanche. Aujourd’hui : lundi.','Вчера — воскресенье. Сегодня — понедельник.','йэр дима́(н)ш. ожурдюи лё(н)ди́')])])

s=start(5,'Выберите время встречи по короткой записке или ответу. Повторите дни недели и метки вчера, сегодня и завтра. Нового материала нет.',['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche','aujourdhui','demain','hier','rendez-vous','quand'])
intro(s,1,'Здесь только изученные названия дней, метки времени и короткие записи о встрече. В каждом задании свои данные: используйте их, а не настоящую дату. Для ответа о встрече найдите именно запись или реплику о ней.',[])
listen(s,[A,U],['rendez-vous','demain','aujourdhui','mercredi','mardi'],['demain','mercredi'],'Когда назначена встреча?','Rendez-vous demain, mercredi.','ра(н)дэ-ву дёмэ(н), мэркрёди́.',['Сегодня, в среду','Завтра, во вторник','Завтра, в среду'],2,'Встреча завтра, в среду.','Сохраните и метку завтра, и среду; изменение одного элемента меняет время встречи.')
choose(s,'reading',[A,U],['aujourdhui','dimanche','rendez-vous','demain','lundi'],['demain','lundi'],'Когда встреча по записке?',fr('Aujourd’hui : dimanche. Rendez-vous demain, lundi.','ожурдюи дима́(н)ш. ра(н)дэ-ву дёмэ(н), лё(н)ди́.'),['Сегодня, в воскресенье','Завтра, в понедельник','Завтра, в воскресенье'],1,'Встреча назначена на завтра, понедельник.','Воскресенье отмечено как сегодня. Запись о встрече указывает завтра, понедельник.')
pairs(s,[A],[('hier','Вчера'),('aujourdhui','Сегодня'),('demain','Завтра')],'Вы различили три знакомые метки времени.','Вчера — день перед сегодняшним, завтра — следующий после сегодняшнего.')
listen(s,[U],['samedi','vendredi','dimanche'],['samedi'],'Какой день назван в кратком ответе?','Samedi.','самди́.',['Суббота','Воскресенье','Пятница'],0,'Названа суббота.','В аудио — суббота; пятница и воскресенье называются иначе.')
choose(s,'vocabulary',[A],['rendez-vous','quand','demain','lundi'],['quand'],'Вы знаете, что встреча состоится, но её день не указан. Как кратко спросить о времени?',ru('Выберите вопрос.'),[fr('Rendez-vous lundi.','ра(н)дэ-ву лё(н)ди́.'),fr('Rendez-vous quand ?','ра(н)дэ-ву ка́(н)?'),fr('Rendez-vous demain.','ра(н)дэ-ву дёмэ́(н).')],1,'Выбран изученный вопрос о времени встречи.','Нужна реплика с вопросом «когда?»; сообщения о понедельнике и завтра уже называют время.')
fill(s,[A],['rendez-vous','aujourdhui','demain','hier','vendredi'],['aujourdhui'],'По условию встреча сегодня, в пятницу. Выберите метку сегодняшнего дня.','Rendez-vous ','ра(н)дэ-ву́',', vendredi.','ва(н)дрэди́',daylist(['demain','hier','aujourdhui']),2,'Rendez-vous aujourd’hui, vendredi.','ра(н)дэ-ву ожурдюи, ва(н)дрэди́.','Выбрана метка сегодня.','Нужна метка сегодняшнего дня; вчера и завтра меняют время встречи.')
order(s,[A],['rendez-vous','demain','jeudi'],[],'Соберите «Встреча завтра, в четверг». Начните с названия встречи, затем поставьте метку «завтра» и день.',[('Rendez-vous','ра(н)дэ-ву́'),('demain,','дёмэ́(н)'),('jeudi.','жёди́')],'Rendez-vous demain, jeudi.','ра(н)дэ-ву дёмэ(н), жёди́.','Получилась нужная запись о завтрашней встрече.','После названия встречи поставьте завтра, затем четверг.')
choose(s,'reading',[A,U],['hier','lundi','aujourdhui','mardi','demain','mercredi'],['hier','lundi'],'Какая подпись относится к вчерашнему дню по записке?',fr('Hier : lundi. Aujourd’hui : mardi. Demain : mercredi.','йэр лё(н)ди́. ожурдюи марди́. дёмэ(н) мэркрёди́.'),['Сегодня — вторник','Завтра — среда','Вчера — понедельник'],2,'Вчерашним днём назван понедельник.','Нужна подпись с меткой вчера; сегодня и завтра относятся к другим дням в этой записке.')
page(s,[('Как узнать день встречи',['Найдите вопрос о времени и ответ на него либо готовую запись. Не подменяйте день встречи сегодняшним днём из соседней подписи.'],[ex('Rendez-vous quand ? Demain, mercredi.','Встреча когда? Завтра, в среду.','ра(н)дэ-ву ка́(н)? дёмэ(н), мэркрёди́')]),('Три метки времени',['Вчера, сегодня и завтра различаются относительно условия конкретного задания. Здесь повторяются только знакомые календарные подписи.'],[ex('Hier : lundi. Aujourd’hui : mardi. Demain : mercredi.','Вчера — понедельник. Сегодня — вторник. Завтра — среда.','йэр лё(н)ди́. ожурдюи марди́. дёмэ(н) мэркрёди́')]),('Краткая запись без новых времён',['Встречу обозначаем готовой короткой записью с меткой и названием дня. Рассказ о прошлых действиях не требуется.'],[ex('Rendez-vous aujourd’hui, vendredi.','Встреча сегодня, в пятницу.','ра(н)дэ-ву ожурдюи, ва(н)дрэди́'),ex('Rendez-vous demain, jeudi.','Встреча завтра, в четверг.','ра(н)дэ-ву дёмэ(н), жёди́')])])

for s in S:
 required=list(dict.fromkeys(w for t in s['tasks'] for w in t['vocabulary_ids']))
 required+=list(dict.fromkeys(w for i in s['introductions'] for w in i['vocabulary_ids']))
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
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-zÀ-ÿŒœ])'+re.escape(f)+r'(?![A-Za-zÀ-ÿŒœ])',text) and not any(n['fr']==f for n in ns):ns.append(dict(fr=f,transcription_ru=p))
  if ns:v['pronunciation_notes']=ns
 for x in list(v.values()):annotate(x)
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Семь дней недели, календарные метки вчера, сегодня и завтра и короткие записи о встрече.',learning_outcomes=['Узнавать названия семи дней недели на слух и в записи.','Различать вчера, сегодня и завтра по данным условия.','Выбирать и собирать короткую запись о дне встречи.','Спрашивать, когда встреча, и понимать короткий ответ без новых глагольных времён.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u13-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'PRE_A1-U13.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('U13 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('aujourdhui','Соединять знакомую метку сегодняшнего дня с новой рамкой времени суток только после её объяснения.'),('demain','Вернуть метку завтрашнего дня без введения будущего времени глагола.'),('hier','Повторять как календарную подпись без рассказа о действиях в прошлом.'),('rendez-vous','Сохранить готовую краткую запись о встрече, не вводя новые формы глагола встречи.'),('quand','Повторить короткий вопрос о времени в контексте встречи.'),('mercredi','Отложенно различить название среды и пятницы.'),('vendredi','Отложенно различить название пятницы и среды.'),('dimanche','Повторить переход от воскресенья к понедельнику в недельном цикле.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['До импорта целевая база должна содержать PRE_A1-U12-L05; наличие на другом сервере пакетом не обеспечивается.','Человеку нужно проверить французские краткие записи, переводы и условные русские транскрипции; прослушать браузерную озвучку, особенно названия среды, пятницы, понедельника и метки времени.','Выполнить предпросмотр, пробное прохождение и замер длительности. Ориентир 6 минут на занятие не измерен.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U14',previous_unit='PRE_A1-U12',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку текущей темы. Отдельное подтверждение принятия или импорта предыдущих пакетов не получено.','Дни даны как самостоятельные названия и краткие ответы о конкретном дне. Артикль для регулярного повторения по дням не вводится.','Сегодня, завтра и вчера отнесены к данным конкретного задания, а не к настоящей дате на устройстве.','Вчера дано только в календарных подписях без глагола. Прошедшее время и конструкции рассказа о прошлом не вводятся.','Назначенная встреча вводится с артиклем в словарной карточке и явно показана без артикля в готовой краткой записи о времени. Это существительное, а не новая форма глагола встречаться.','Короткий разговорный вопрос о времени вводится до вопросов о нём. В интеграционном занятии новых рамок нет.','В первых четырёх занятиях соответственно 3, 4, 3 и 2 новых значения. Две карточки четвёртого занятия нужны для метки вчера и вопроса о времени, без расширения словаря ради количества.','Повторены действия предыдущей темы и предпочтения еды из более ранней темы. Основной реестр и предыдущие пакеты не изменены.','Слово с апострофом и название встречи с дефисом сохраняются целыми в сборке. Подписанная сборка не объявляется независимой проверкой каждого слова.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Первые три названия дней и краткий ответ о конкретном дне; строчная буква в списке.'),dict(session_id=S[1]['id'],material='Остальные четыре названия и полный недельный цикл, включая переход к понедельнику.'),dict(session_id=S[2]['id'],material='Краткие подписи с метками сегодня и завтра; готовая запись о встрече с относительной меткой и уточнением дня.'),dict(session_id=S[3]['id'],material='Вчера как подпись без глагола; короткий разговорный вопрос о времени встречи и ответ с меткой и днём.')]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
(OUT/'PRE_A1-U13.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))
