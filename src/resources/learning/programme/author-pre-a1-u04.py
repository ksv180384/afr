"""Author PRE_A1-U04 only; preserve prepared earlier packages and stable meanings."""
import json, copy, re, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][3]
W={}
for source in [OUT/'PRE_A1-U03.v1.json',OUT/'PRE_A1-U02.v1.json',KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']: W.setdefault(w['id'],copy.deepcopy(w))
OLD=set(W); FIRST={}; S=[]
U='PRE_A1.U04.UNDERSTAND'; A='PRE_A1.U04.APPLY'
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

def addword(i,key,f,r,p,e,n,reviews):
 W[i]=dict(id=i,lexeme_key=key,fr=f,ru=[r],transcription_ru=p,example=e,review_items=reviews); FIRST[i]=n
NOUNS=[('livre','livre:book','un livre','книга','э(н) ли́вр',1),('stylo','stylo:pen','un stylo','ручка для письма','э(н) стило́',1),('sac','sac:bag','un sac','сумка','э(н) са́к',1),('table','table:table','une table','стол','юн та́бль',2),('chaise','chaise:chair','une chaise','стул','юн шэ́з',2),('lampe','lampe:lamp','une lampe','лампа','юн ла́(н)п',2)]
for index,(i,key,f,r,p,n) in enumerate(NOUNS):
 group=NOUNS[:3] if n==1 else NOUNS[3:]; others=[v for v in group if v[0]!=i]
 meaning=[v[3] for v in others]; meaning.insert(index%3,r)
 application=[fr(v[2],v[4]) for v in others]; application.insert((index+1)%3,fr(f,p))
 situations={'livre':'Вы пришли читать и просите книгу. Какое название подходит?','stylo':'Вам нечем подписать карточку. Выберите название ручки.','sac':'Вы хотите убрать вещи в сумку. Выберите её название.','table':'Вам нужна поверхность, на которую можно положить книгу. Выберите стол.','chaise':'Вам нужно сесть. Выберите название стула.','lampe':'В комнате темно. Вам нужна лампа. Какое название выбрать?'}
 addword(i,'fr:'+key,f,r,p,ex(f.capitalize()+', s’il vous plaît.',r.capitalize()+', пожалуйста.',p+', силь ву плэ́'),n,[rev(i+'-meaning',fr(f,p),meaning,index%3,f'Это {r}. Слово учим вместе с артиклем.',f'Правильный перевод — «{r}»; другие варианты называют другие предметы.'),rev(i+'-use',ru(situations[i]),application,(index+1)%3,'Название соответствует нужному предмету.',f'Здесь нужен предмет «{r}»; другие карточки обозначают другие вещи.')])
addword('un-article','fr:un:indefinite-masculine','un','неопределённый артикль мужского рода перед одним предметом','э́(н)',ex('Un livre.','Книга.','э(н) ли́вр'),1,[rev('un-article-meaning',fr('Un livre.','э(н) ли́вр'),['Название сумки','Артикль мужского рода перед одним предметом','Благодарность'],1,'Перед названием книги стоит артикль мужского рода.','В этом сочетании маленькое слово — артикль, а не название вещи или благодарность.'),rev('un-article-use',ru('Вы впервые называете одну книгу. Какой артикль стоит перед изученным названием книги?'),[wc('cinq'),fr('un','э́(н)'),wc('bonjour')],1,'У изученного названия книги мужской род.','Выберите артикль мужского рода; число пять и приветствие не подходят.')])
addword('une-article','fr:une:indefinite-feminine','une','неопределённый артикль женского рода перед одним предметом','юн',ex('Une table.','Стол.','юн та́бль'),2,[rev('une-article-meaning',fr('Une table.','юн та́бль'),['Артикль женского рода перед одним предметом','Артикль мужского рода','Число два'],0,'Перед этим названием стола нужен артикль женского рода.','У французского названия стола женский род; не выбирайте род по русскому переводу.'),rev('une-article-use',ru('У французского названия лампы из занятия женский род. Какой артикль выбрать перед одним предметом?'),[wc('un-article'),fr('une','юн'),wc('trois')],1,'Лампу называют с артиклем женского рода.','Нужен артикль женского рода; мужской род и число три не подходят.')])
addword('cest-identify','fr:cest:identify-object','c’est','это (при указании на один предмет)','сэ',ex('C’est un livre.','Это книга.','сэт э(н) ли́вр'),3,[rev('cest-identify-meaning',fr('C’est un livre.','сэт э(н) ли́вр'),['Книгу, пожалуйста.','В сумке.','Это книга.'],2,'Образец указывает на один предмет и называет его.','Это указание «Это книга», а не просьба или место.'),rev('cest-identify-use',ru('Вы показываете одну книгу и хотите сказать «Это книга». Как начать?'),[fr('C’est','сэ'),wc('merci'),wc('bonjour')],0,'Это начало образца для указания на один предмет.','Начните с изученного «это»; благодарность и приветствие не называют предмет.')])
addword('sur-la-table','fr:sur-la-table:on-table','sur la table','на столе','сюр ла та́бль',ex('Sur la table : un livre.','На столе: книга.','сюр ла табль : э(н) ли́вр'),3,[rev('sur-la-table-meaning',fr('Sur la table','сюр ла та́бль'),['В сумке','На столе','Стул'],1,'Указано место на столе.','Правильное место — на столе, а не в сумке.'),rev('sur-la-table-use',ru('Книга лежит на столе. Какой подписью обозначить место?'),[fr('dans le sac','да(н) лё са́к'),wc('sac'),fr('sur la table','сюр ла та́бль')],2,'Подпись соответствует месту на столе.','Выберите «на столе»; сумка и место внутри неё не подходят.')])
addword('dans-le-sac','fr:dans-le-sac:in-bag','dans le sac','в сумке','да(н) лё са́к',ex('Dans le sac : un stylo.','В сумке: ручка.','да(н) лё сак : э(н) стило́'),3,[rev('dans-le-sac-meaning',fr('Dans le sac','да(н) лё са́к'),['На столе','Сумка','В сумке'],2,'Указано место внутри сумки.','Выражение означает «в сумке», а не только название сумки.'),rev('dans-le-sac-use',ru('Вы убрали ручку внутрь сумки. Какую подпись места выбрать?'),[wc('sur-la-table'),fr('dans le sac','да(н) лё са́к'),wc('sac')],1,'Подпись указывает на место внутри сумки.','Нужен смысл «в сумке»; стол и отдельное название сумки не обозначают это место.')])
addword('des-article','fr:des:indefinite-plural','des','неопределённый артикль перед несколькими предметами','дэ',ex('Des livres.','Книги, несколько книг.','дэ ли́вр'),4,[rev('des-article-meaning',fr('Des livres.','дэ ли́вр'),['Одна книга','Книги; точное число не названо','Ровно две книги'],1,'Книг несколько, без точного числа.','Точное число не сообщается: это несколько книг, а не обязательно две.'),rev('des-article-use',ru('Вы называете несколько сумок без точного количества. Какой артикль нужен перед формой множественного числа?'),[wc('un-article'),fr('des','дэ'),wc('une-article')],1,'Здесь нужен артикль множественного числа.','Нужен артикль для нескольких предметов; два других варианта относятся к одному.')])
addword('ce-sont-identify','fr:ce-sont:identify-objects','ce sont','это (при указании на несколько предметов)','сё со́(н)',ex('Ce sont des livres.','Это книги.','сё со(н) дэ ли́вр'),4,[rev('ce-sont-identify-meaning',fr('Ce sont des livres.','сё со(н) дэ ли́вр'),['Это книги, несколько предметов.','Это одна книга.','Книгу, пожалуйста.'],0,'Образец указывает на несколько книг.','Это несколько предметов: «Это книги», а не одна книга или просьба.'),rev('ce-sont-identify-use',ru('Перед вами несколько сумок. Как начать сообщение «Это сумки»?'),[wc('cest-identify'),fr('Ce sont','сё со́(н)'),wc('bonjour')],1,'Подходит изученное начало для нескольких предметов.','Для нескольких сумок нужно множественное число; единственное число и приветствие не подходят.')])
def as_grammar(s,t):
 t['skill']='grammar'
 return t

def phrase_pairs(s,gs,rows,g,b):
 vs=list(dict.fromkeys(w for f,p,r,ws,av in rows for w in ws)); avs=list(dict.fromkeys(w for f,p,r,ws,av in rows for w in av))
 t=base(s,'vocabulary',gs,vs,avs,'Соедините реплику или выражение с его смыслом.',ru('Используйте каждую карточку один раз.'),g,b)
 t.update(type='matching_pairs',left_items=[dict(id=f'l{i}',**fr(f,p)) for i,(f,p,r,ws,av) in enumerate(rows)],right_items=[dict(id=f'r{i}',**ru(r)) for i,(f,p,r,ws,av) in reversed(list(enumerate(rows)))],correct_pairs=[dict(left_id=f'l{i}',right_id=f'r{i}',assessed_vocabulary_ids=av) for i,(f,p,r,ws,av) in enumerate(rows)])
 return t

s=start(1,'Узнавайте книгу, ручку и сумку по французскому названию. Учите название вместе с артиклем и повторите маленькие числа.',['livre','stylo','sac','un-article','un','deux','cinq','sil-vous-plait'])
listen(s,['PRE_A1.U03.UNDERSTAND'],['un','deux','cinq'],['deux'],'Вспомните числа: сколько предметов назвали?','Deux.','дё́.',['1','2','5'],1,'Названо два предмета.','Прозвучало число два; один и пять означают другое количество.')
intro(s,2,'Три предмета, которые могут лежать на столе: un livre — книга, un stylo — ручка, un sac — сумка. Название учим вместе с маленьким словом перед ним. Все три французских существительных здесь мужского рода.',['livre','stylo','sac'],[('un livre','э(н) ли́вр'),('un stylo','э(н) стило́'),('un sac','э(н) са́к')],('Un livre. Un stylo. Un sac.','э(н) ли́вр. э(н) стило́. э(н) са́к.'))
pairs(s,[U],[('livre','Книга'),('stylo','Ручка для письма'),('sac','Сумка')],'Вы различили названия трёх предметов.','Книга, ручка и сумка имеют разные французские названия; сверяйте смысл каждого сочетания.')
listen(s,[U],['livre','stylo','sac'],['stylo'],'Какой предмет назвал собеседник?','Un stylo.','э(н) стило́.',['Сумку','Книгу','Ручку'],2,'Названа ручка для письма.','Собеседник назвал ручку, а не книгу или сумку.')
intro(s,4,'В счёте un означает «один». Перед названием нового, ещё не уточнённого предмета un служит артиклем мужского рода: un livre — книга. В этом значении его не обязательно переводить отдельным русским словом. Род запоминаем с французским названием.',['un-article'],[('un','э́(н)'),('un livre','э(н) ли́вр')])
choose(s,'grammar',[A],['un-article','livre'],['un-article'],'В записке впервые называют книгу. Какую роль играет маленькое слово перед её названием?',fr('Un livre.','э(н) ли́вр.'),['Это название сумки','Это артикль мужского рода перед одним предметом','Это знак множественного числа'],1,'Перед названием книги стоит артикль мужского рода.','Нужен вариант об артикле: название книги относится к одному предмету, а не к нескольким.')
choose(s,'vocabulary',[U],['livre','stylo','sac'],['livre'],'На столе лежит книга. Выберите её французское название.',ru('Нужен предмет для чтения.'),[wc('stylo'),wc('sac'),wc('livre')],2,'Вы выбрали название книги.','Нужно название книги; ручка и сумка обозначают другие предметы.')
fill(s,[U],['livre','stylo','sac','sil-vous-plait'],['livre'],'Попросите книгу. Дополните короткую просьбу «Книгу, пожалуйста».','','',', s’il vous plaît.','силь ву плэ́',[wc('sac'),wc('livre'),wc('stylo')],1,'un livre, s’il vous plaît.','э(н) ливр, силь ву плэ́.','Просьба относится именно к книге.','Выберите название книги; остальные варианты изменят предмет просьбы.')
choose(s,'reading',[U],['livre','stylo','sac'],['livre','stylo'],'В коротком списке отмечены два предмета. Какие?',fr('Un livre. Un stylo.','э(н) ли́вр. э(н) стило́.'),['Книга и сумка','Книга и ручка','Ручка и сумка'],1,'В списке названы книга и ручка.','Нужно выбрать книгу и ручку; сумка не названа.')
page(s,[('Названия учим вместе с артиклем',['Перед названием одного ещё не уточнённого предмета здесь стоит артикль мужского рода. Учите его вместе с новым словом.'],[W[w]['example'] for w in ['livre','stylo','sac']]),('Французский род не равен русскому',['Русское слово «книга» женского рода, но изученное французское название — мужского. Род запоминаем с французским сочетанием, а не угадываем по переводу.'],[ex('Un livre.','Книга.','э(н) ли́вр')]),('Как попросить предмет',['Добавьте знакомую вежливую формулу к названию. Это короткая просьба; длинное предложение пока не требуется.'],[ex('Un stylo, s’il vous plaît.','Ручку, пожалуйста.','э(н) стило, силь ву плэ́')])])

s=start(2,'Узнавайте стол, стул и лампу в комнате. Различайте артикли мужского и женского рода на знакомых названиях.',['livre','stylo','sac','table','chaise','lampe','un-article','une-article','sil-vous-plait'])
pairs(s,[U],[('stylo','Ручка для письма'),('sac','Сумка'),('livre','Книга')],'Вы вспомнили три предмета из прошлого занятия.','Не путайте ручку, сумку и книгу: в каждой паре нужен точный предмет.')
intro(s,2,'Три предмета комнаты: une table — стол, une chaise — стул, une lampe — лампа. Эти французские названия женского рода. Учите сочетания целиком, даже если род русского перевода другой.',['table','chaise','lampe'],[('une table','юн та́бль'),('une chaise','юн шэ́з'),('une lampe','юн ла́(н)п')],('Une table. Une chaise. Une lampe.','юн та́бль. юн шэ́з. юн ла́(н)п.'))
choose(s,'vocabulary',[U],['table','chaise','lampe'],['chaise'],'Вам нужно сесть. Выберите французское название стула.',ru('Нужен стул, а не стол и не лампа.'),[wc('table'),wc('chaise'),wc('lampe')],1,'Стулу соответствует выбранное название.','Нужно название стула; стол и лампа не подходят для этой задачи.')
listen(s,[U],['table','chaise','lampe'],['lampe'],'Какой предмет в комнате назвали?','Une lampe.','юн ла́(н)п.',['Стул','Лампу','Стол'],1,'Названа лампа.','Прозвучало название лампы, а не стула или стола.')
intro(s,4,'Перед одним новым предметом женского рода используется une: une table — стол. Сравните знакомое un livre — книга. Артикль выбираем по французскому роду слова, а не по русскому переводу.',['une-article'],[('une','юн'),('une table','юн та́бль'),('un livre','э(н) ли́вр')])
as_grammar(s,fill(s,[A],['table','un-article','une-article'],['une-article'],'Назовите один стол. Выберите артикль изученного французского слова.','','',' table.','та́бль',[wc('un-article'),wc('une-article')],1,'une table.','юн та́бль.','Название стола в этом занятии женского рода.','Нужен артикль женского рода: русский род слова «стол» не определяет французский артикль.'))
pairs(s,[U],[('lampe','Лампа'),('table','Стол'),('chaise','Стул')],'Каждое название соединено с нужным предметом.','Сверьте названия: лампа, стол и стул — разные предметы.')
choose(s,'reading',[U],['table','chaise','lampe'],['table','chaise'],'Какие два предмета названы в заметке о комнате?',fr('Une table. Une chaise.','юн та́бль. юн шэ́з.'),['Стол и лампа','Стул и лампа','Стол и стул'],2,'В заметке названы стол и стул.','Нужны стол и стул; лампа не упомянута.')
order(s,[A],['lampe','une-article','sil-vous-plait'],[],'Соберите просьбу «Лампу, пожалуйста», начав с артикля перед названием.',[('Une','юн'),('lampe,','ла́(н)п'),('s’il vous plaît.','силь ву плэ́')],'Une lampe, s’il vous plaît.','юн ла(н)п, силь ву плэ́.','Сначала артикль с названием предмета, затем целая формула вежливости.','Поставьте артикль перед названием лампы и завершите просьбу словом «пожалуйста».')
page(s,[('Три предмета комнаты',['Все три французских существительных этого занятия женского рода. Сразу произносите артикль и название вместе.'],[W[w]['example'] for w in ['table','chaise','lampe']]),('Артикль связан с французским словом',['У французского названия стола женский род, хотя русский перевод мужского рода. Не переносите русский род на французский. Сравнивайте изученные сочетания.'],[ex('Une table.','Стол.','юн та́бль'),ex('Un livre.','Книга.','э(н) ли́вр')]),('Разобранная ситуация',['Вам нужно сесть. Подойдёт название стула: стол служит другой цели, а лампа даёт свет. Ситуация помогает выбрать нужное слово.'],[ex('Une chaise, s’il vous plaît.','Стул, пожалуйста.','юн шэз, силь ву плэ́')])])

s=start(3,'Указывайте на один знакомый предмет и понимайте подписи «на столе» и «в сумке». Повторите просьбу повторить название.',['livre','stylo','sac','table','chaise','lampe','un-article','une-article','cest-identify','sur-la-table','dans-le-sac','repetez','epelez','plus-lentement'])
listen(s,[U],['table','chaise','lampe'],['chaise'],'Вспомните предметы комнаты. Что назвали?','Une chaise.','юн шэ́з.',['Лампу','Стул','Стол'],1,'Прозвучало название стула.','Нужно выбрать стул; лампа и стол называются иначе.')
intro(s,2,'Когда показываем один предмет и называем его, используем c’est — «это»: C’est un livre. — «Это книга». Сохраняем знакомое сочетание с артиклем. Слушайте полный образец: перед гласным в нём слышится связующий звук «т».',['cest-identify'],[('c’est','сэ'),('C’est un livre.','сэт э(н) ли́вр')],('C’est un livre. C’est une chaise.','сэт э(н) ли́вр. сэт юн шэ́з.'))
choose(s,'vocabulary',[A],['cest-identify','sac','livre','chaise'],['sac'],'Вы показываете одну сумку. Выберите реплику «Это сумка».',ru('Название должно соответствовать предмету.'),[fr('C’est un livre.','сэт э(н) ли́вр.'),fr('C’est une chaise.','сэт юн шэ́з.'),fr('C’est un sac.','сэт э(н) са́к.')],2,'Реплика называет именно сумку.','Выберите реплику о сумке; книга и стул — другие предметы.')
listen(s,[A],['cest-identify','livre'],['cest-identify'],'Что делает говорящий этой репликой?','C’est un livre.','сэт э(н) ли́вр.',['Называет показанный предмет: «Это книга»','Просит: «Книгу, пожалуйста»','Говорит, что книга в сумке'],0,'Реплика указывает на один предмет и называет его.','Это указание «Это книга», а не просьба и не сообщение о месте.')
intro(s,4,'Для подписи места выучим два выражения целиком: sur la table — «на столе», dans le sac — «в сумке». В коротком списке после подписи можно поставить двоеточие и название предмета. Отдельную таблицу форм артиклей здесь не изучаем.',['sur-la-table','dans-le-sac'],[('sur la table','сюр ла та́бль'),('dans le sac','да(н) лё са́к')],('Sur la table. Dans le sac.','сюр ла та́бль. да(н) лё са́к.'))
pairs(s,[U],[('sur-la-table','На столе'),('dans-le-sac','В сумке'),('sac','Сумка')],'Вы различили две подписи места и название предмета.','Выражение «в сумке» сообщает место; слово «сумка» называет предмет. Подпись «на столе» указывает другое место.')
choose(s,'reading',[U],['sur-la-table','dans-le-sac','livre','stylo','sac'],['dans-le-sac','stylo'],'По заметке определите, что находится в сумке.',fr('Sur la table : un livre. Dans le sac : un stylo.','сюр ла табль : э(н) ли́вр. да(н) лё сак : э(н) стило́.'),['Книга','Сумка','Ручка'],2,'В части о сумке названа ручка; книга относится к столу.','В сумке указана ручка. Книга стоит после подписи «на столе».')
as_grammar(s,fill(s,[A],['cest-identify','table','un-article','une-article'],['une-article'],'Вы показываете один стол. Дополните реплику «Это стол».','C’est ','сэ',' table.','та́бль',[wc('une-article'),wc('un-article')],0,'C’est une table.','сэт юн та́бль.','Сохранился знакомый артикль женского рода перед названием стола.','Нужен артикль женского рода: новый образец не меняет род названия стола.'))
choose(s,'vocabulary',['PRE_A1.CLARIFY.REQUEST'],['repetez','epelez','plus-lentement'],['repetez'],'Вы не расслышали название предмета и хотите услышать его ещё раз целиком. Что сказать?',ru('Вспомните знакомую вежливую просьбу.'),[wc('plus-lentement'),wc('repetez'),wc('epelez')],1,'Просьба повторить подходит для повтора названия целиком.','Выберите просьбу повторить: говорить медленнее и перечислять буквы — другие действия.')
page(s,[('Как назвать показанный предмет',['Образец указания ставится перед знакомым сочетанием артикля и названия. Род существительного остаётся тем же.'],[ex('C’est un livre.','Это книга.','сэт э(н) ли́вр'),ex('C’est une table.','Это стол.','сэт юн та́бль')]),('Где находится предмет',['Две подписи места учим целиком. В заметке двоеточие отделяет место от названия; такая запись не требует длинного предложения.'],[W['sur-la-table']['example'],W['dans-le-sac']['example']]),('Не расслышали название',['Попросите повторить слово целиком. Перечисление букв нужно для уточнения написания и не равно обычному повтору.'],[W['repetez']['example']])])

s=start(4,'Различайте один предмет, точное количество и несколько предметов без названного числа. Указывайте на несколько знакомых вещей.',['livre','stylo','sac','lampe','table','chaise','un-article','une-article','des-article','cest-identify','ce-sont-identify','un','deux','trois'])
choose(s,'reading',[U],['cest-identify','lampe','table','chaise'],['lampe'],'Какой знакомый предмет назван в реплике?',fr('C’est une lampe.','сэт юн ла́(н)п.'),['Стол','Стул','Лампа'],2,'Реплика называет лампу.','Это лампа; стол и стул имеют другие названия.')
intro(s,2,'Сравним количество: un livre — «одна книга», deux livres — «две книги», trois stylos — «три ручки». У знакомых названий нескольких предметов добавляется конечная буква s; в этих примерах она не произносится. После точного числа дополнительный артикль не ставим. Это наблюдение о данных примерах.',['livre','stylo','deux'],[('un livre','э(н) ли́вр'),('deux livres','дё ли́вр'),('trois stylos','труа стило́'),('s','эс')],('Un livre. Deux livres. Trois stylos.','э(н) ли́вр. дё ли́вр. труа стило́.'))
choose(s,'vocabulary',[A],['livre','un','deux','trois'],['deux'],'Вместо одной книги вам нужны две. Какую запись выбрать?',ru('Сравните точное количество.'),[fr('Un livre','э(н) ли́вр'),fr('Deux livres','дё ли́вр'),fr('Trois livres','труа ли́вр')],1,'В записи ровно две книги.','Нужна запись с числом два; один и три не соответствуют требуемому количеству.')
intro(s,3,'Если предметов несколько, но точное число не называем, перед знакомой формой множественного числа ставим des: des livres — «книги, несколько книг». Это не означает обязательно две книги. Сравните точное число и неопределённое количество.',['des-article'],[('des','дэ'),('des livres','дэ ли́вр')],('Des livres.','дэ ли́вр.'))
listen(s,[A],['des-article','livre','un','deux'],['des-article'],'Что известно о количестве книг из услышанного сочетания?','Des livres.','дэ ли́вр.',['Ровно две книги','Одна книга','Книг несколько, точное число не названо'],2,'Сообщено несколько книг, без точного числа.','Точное число не названо: книг несколько, но не обязательно две.')
as_grammar(s,fill(s,[A],['sac','un-article','une-article','des-article'],['des-article'],'Назовите несколько сумок без точного количества. Выберите артикль.','','',' sacs.','са́к',[wc('un-article'),wc('une-article'),wc('des-article')],2,'des sacs.','дэ са́к.','Артикль относится к нескольким сумкам.','Нужен артикль множественного числа; два других варианта относятся к одному предмету.'))
intro(s,5,'На один предмет указываем знакомым c’est. Для нескольких используем ce sont: Ce sont des livres. — «Это книги». Учим эту короткую пару образцов без таблицы спряжения.',['ce-sont-identify'],[('c’est','сэ'),('ce sont','сё со́(н)'),('Ce sont des livres.','сё со(н) дэ ли́вр')],('C’est un livre. Ce sont des livres.','сэт э(н) ли́вр. сё со(н) дэ ли́вр.'))
choose(s,'vocabulary',[A],['ce-sont-identify','des-article','sac'],['ce-sont-identify'],'Какой смысл передаёт реплика?',fr('Ce sont des sacs.','сё со(н) дэ са́к.'),['Это сумки, несколько предметов.','Это одна сумка.','Сумку, пожалуйста.'],0,'Образец указывает на несколько сумок.','Это указание на несколько предметов: «Это сумки», а не один предмет или просьба.')
order(s,[A],['ce-sont-identify','des-article','livre'],[],'Соберите реплику «Это книги», начав с образца для нескольких предметов.',[('Ce sont','сё со́(н)'),('des','дэ'),('livres.','ли́вр')],'Ce sont des livres.','сё со(н) дэ ли́вр.','Начало для нескольких предметов стоит перед артиклем и названием.','Начните с образца множественного числа, затем поставьте артикль и название книг.')
choose(s,'vocabulary',[A],['cest-identify','ce-sont-identify','un-article','des-article','livre','sac'],['livre','un-article'],'Перед вами одна книга. Выберите реплику, которая называет именно её.',ru('Учитывайте предмет и единственное число.'),[fr('Ce sont des livres.','сё со(н) дэ ли́вр.'),fr('C’est un sac.','сэт э(н) са́к.'),fr('C’est un livre.','сэт э(н) ли́вр.')],2,'Реплика называет одну книгу.','Нужно указать на одну книгу; другие ответы называют несколько книг или другую вещь.')
page(s,[('Один и точное количество',['Точное число ставится перед названием нескольких знакомых вещей. Конечная буква множественного числа в данных примерах не звучит. Это правило для изученных примеров, а не для любого будущего слова.'],[ex('Un livre.','Одна книга.','э(н) ли́вр'),ex('Deux livres.','Две книги.','дё ли́вр'),ex('Trois stylos.','Три ручки.','труа стило́')]),('Несколько без точного числа',['Артикль множественного числа не сообщает точное количество. Не заменяйте его автоматически числом два.'],[ex('Des livres.','Книги, несколько книг.','дэ ли́вр'),ex('Des sacs.','Сумки, несколько сумок.','дэ са́к')]),('Два образца указания',['Для одного и для нескольких предметов выбираем разные начала реплики. Форма множественного числа сохраняет смысловой ключ соответствующего существительного.'],[ex('C’est un livre.','Это книга.','сэт э(н) ли́вр'),ex('Ce sont des livres.','Это книги.','сё со(н) дэ ли́вр')])])

s=start(5,'Поймите короткую заметку о сумке и столе, назовите вещи и различите один предмет и несколько. Нового материала нет.',['livre','stylo','sac','table','chaise','lampe','un-article','une-article','des-article','cest-identify','ce-sont-identify','sur-la-table','dans-le-sac','un','deux','trois','non'])
intro(s,1,'В этом занятии только знакомые слова и образцы. Сначала определите предмет, затем количество и место. Если дан список, ответ берите из него, а не из собственных предположений.',[])
listen(s,[U],['cest-identify','stylo','livre','sac','non'],['stylo','non'],'Что ответил собеседник на предположение о предмете?','C’est un livre ? Non, c’est un stylo.','сэт э(н) ли́вр? но(н), сэт э(н) стило́.',['Подтвердил: это книга','Исправил: это ручка','Исправил: это сумка'],1,'Собеседник отверг предположение о книге и назвал ручку.','После отрицания собеседник исправил название: это ручка, а не книга или сумка.')
choose(s,'reading',[A],['dans-le-sac','sur-la-table','deux','livre','stylo','un'],['dans-le-sac','deux','livre'],'Что находится в сумке согласно заметке?',fr('Dans le sac : deux livres. Sur la table : un stylo.','да(н) лё сак : дё ли́вр. сюр ла табль : э(н) стило́.'),['Одна ручка','Одна книга','Две книги'],2,'В части о сумке названы две книги.','В сумке две книги; ручка относится к столу, а число книг — два.')
phrase_pairs(s,[A],[('C’est une chaise.','сэт юн шэ́з','Это стул.',['cest-identify','une-article','chaise'],['cest-identify','chaise']),('Ce sont des livres.','сё со(н) дэ ли́вр','Это книги, несколько предметов.',['ce-sont-identify','des-article','livre'],['ce-sont-identify','livre']),('Dans le sac.','да(н) лё са́к','В сумке.',['dans-le-sac'],['dans-le-sac'])],'Указания на один и несколько предметов отличаются от выражения места.','Различайте «Это стул», «Это книги» и «В сумке»: последняя карточка сообщает место, а не называет показанную вещь.')
as_grammar(s,fill(s,[A],['cest-identify','lampe','un-article','une-article','des-article'],['une-article'],'Вы показываете одну лампу. Дополните реплику.','C’est ','сэ',' lampe.','ла́(н)п',[wc('des-article'),wc('un-article'),wc('une-article')],2,'C’est une lampe.','сэт юн ла́(н)п.','У названия одной лампы женский род.','Нужен артикль женского рода перед одним предметом; множественное число здесь не подходит.'))
listen(s,[A],['ce-sont-identify','des-article','livre','sac','un-article'],['ce-sont-identify','livre'],'Какой смысл передаёт услышанная реплика?','Ce sont des livres.','сё со(н) дэ ли́вр.',['Это одна книга.','Это сумки.','Это книги.'],2,'Реплика указывает на несколько книг.','Нужно «Это книги»: предмет — книги, и их несколько.')
choose(s,'vocabulary',[A],['deux','trois','stylo','livre'],['trois','stylo'],'Для занятия нужны три ручки. Какую запись выбрать?',ru('Проверьте и число, и название предмета.'),[fr('Trois stylos','труа стило́'),fr('Deux stylos','дё стило́'),fr('Trois livres','труа ли́вр')],0,'В записи три ручки: совпадают количество и предмет.','Нужны три ручки; два предмета или три книги не подходят.')
order(s,[A],['cest-identify','un-article','sac'],[],'Соберите сообщение «Это сумка», начав с образца для одного предмета.',[('C’est','сэ'),('un','э́(н)'),('sac.','са́к')],'C’est un sac.','сэт э(н) са́к.','После начала указания стоят артикль и название сумки.','Начните с указания на один предмет, затем поставьте артикль перед названием сумки.')
choose(s,'reading',[U],['dans-le-sac','sur-la-table','livre','stylo','lampe'],['sur-la-table','lampe'],'Что находится на столе согласно списку?',fr('Dans le sac : un livre, un stylo. Sur la table : une lampe.','да(н) лё сак : э(н) ли́вр, э(н) стило́. сюр ла табль : юн ла́(н)п.'),['Ручка','Лампа','Книга'],1,'На столе названа лампа; два других предмета перечислены в сумке.','На столе находится лампа. Книга и ручка указаны в части о сумке.')
page(s,[('Читаем короткую заметку',['Подпись места относится к предметам после двоеточия. Найдите нужную часть списка, а затем прочитайте название и количество.'],[ex('Dans le sac : deux livres.','В сумке: две книги.','да(н) лё сак : дё ли́вр'),ex('Sur la table : une lampe.','На столе: лампа.','сюр ла табль : юн ла́(н)п')]),('Проверяем предмет и количество',['Один предмет, точное число и несколько без уточнения — разные сообщения. Не делайте вывода о точном количестве, если оно не названо.'],[ex('C’est un livre.','Это книга.','сэт э(н) ли́вр'),ex('Ce sont des livres.','Это книги.','сё со(н) дэ ли́вр'),ex('Trois stylos.','Три ручки.','труа стило́')]),('Собираем готовую реплику',['Начало указания выбирается для одного или нескольких предметов. Все карточки используются один раз; лишних карточек в заданиях сборки нет.'],[ex('C’est un sac.','Это сумка.','сэт э(н) са́к')])])

for s in S:s['skills']=list(dict.fromkeys(t['skill'] for t in s['tasks']))
NOTES={w['fr']:w['transcription_ru'] for w in W.values()}
NOTES.update({'s':'эс','C’est':'сэ','Ce sont':'сё со́(н)'})
def annotate(v):
 if isinstance(v,list):
  for x in v:annotate(x)
 if not isinstance(v,dict):return
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-zÀ-ÿ])'+re.escape(f)+r'(?![A-Za-zÀ-ÿ])',text) and not any(n['fr']==f for n in ns):ns.append(dict(fr=f,transcription_ru=p))
  if ns:v['pronunciation_notes']=ns
 for x in list(v.values()):annotate(x)
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Шесть предметов с артиклями: узнавание, указание на один и несколько предметов, короткие подписи места.',learning_outcomes=['Узнавать названия книги, ручки, сумки, стола, стула и лампы.','Выбирать изученный артикль по французскому роду и числу.','Указывать на один и несколько знакомых предметов по готовому образцу.','Находить предмет и количество в короткой заметке о столе и сумке.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u04-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True); target=OUT/'PRE_A1-U04.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('U04 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('livre','Повторить название и мужской род в выборочном повторении U01–U04.'),('table','Повторить французский женский род вопреки мужскому роду русского перевода.'),('sac','Вернуть знакомый предмет в короткую ситуацию о вещах ученика.'),('cest-identify','Повторить указание на один предмет в интеграции U05.'),('ce-sont-identify','Сопоставить один и несколько предметов без нового материала в контрольном занятии.'),('des-article','Проверить, что множественное число не интерпретируется как точное количество два.'),('un','Сохранять значение числа один отдельно от нового ключа артикля.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['Перед импортом U04 целевая база должна содержать PRE_A1-U03-L05. Подготовленный файл сам по себе не подтверждает импорт.','Человеку нужно проверить язык, озвучку носовых гласных и связующего звука в полных образцах указания, а также пробное прохождение.','Оценка времени 6 минут на занятие не измерена; проверить на телефоне объём объяснений об артиклях и числе.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U05',previous_unit='PRE_A1-U03',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку U04; отдельное подтверждение языкового принятия или импорта U03 не получено.','Число un сохраняет ключ fr:un:cardinal-number. Для другого значения, артикля, используется fr:un:indefinite-masculine.','Множественные формы существительных сохраняют ключи соответствующих значений единственного числа.','Два выражения места учатся целиком; отдельная таблица определённых артиклей не вводится.','В L01–L04 соответственно 4, 4, 3 и 2 новых значения. В L05 нового материала нет.','В заданиях word_order assessed_vocabulary_ids пуст: подписанная сборка не оценивает знание всех слов.','Основной LEXEME_REGISTRY.json и ранее созданные пакеты не изменены. Новые ключи переданы отдельным delta до принятия.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[i]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if i in t['assessed_vocabulary_ids']]) for i in FIRST]
(OUT/'PRE_A1-U04.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))

