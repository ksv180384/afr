"""Author PRE_A1-U07 only; retain existing lexical meanings and IDs."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][6]
W={}
for source in [OUT/f'PRE_A1-U{n:02}.v1.json' for n in range(6,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='PRE_A1.U07.UNDERSTAND';A='PRE_A1.U07.APPLY'
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
COLORS=[('rouge','red','rouge','красный','ру́ж',1),('bleu','blue','bleu','синий','блё́',1),('vert','green','vert','зелёный','вэ́р',1),('jaune','yellow','jaune','жёлтый','жо́н',3),('noir','black','noir','чёрный','нуа́р',4)]
for index,(i,sense,f,r,p,n) in enumerate(COLORS):
 group=COLORS[:3] if n==1 else [COLORS[0],COLORS[1],COLORS[index]]
 others=[c for c in group if c[0]!=i][:2]
 meanings=[c[3] for c in others];meanings.insert(index%3,r)
 applications=[fr('Un stylo '+c[2]+'.','э(н) стило '+c[4]) for c in others];applications.insert((index+1)%3,fr('Un stylo '+f+'.','э(н) стило '+p))
 addword(i,'fr:'+f+':'+sense,f,r,p,ex('Un sac '+f+'.',{'rouge':'Красная сумка.','bleu':'Синяя сумка.','vert':'Зелёная сумка.','jaune':'Жёлтая сумка.','noir':'Чёрная сумка.'}[i],'э(н) сак '+p),n,[rev(i+'-meaning',fr(f,p),meanings,index%3,f'Это {r} цвет.',f'Правильное значение — «{r}»; остальные варианты называют другие цвета.'),rev(i+'-use',ru(f'Для занятия нужна ручка: цвет в условии — {r}. Какую запись выбрать?'),applications,(index+1)%3,'Название цвета соответствует условию.',f'Выберите запись с цветом «{r}»; в остальных записях другой цвет.')])
addword('bleu-clair','fr:bleu-clair:light-blue','bleu clair','светло-синий','блё клэ́р',ex('Un sac bleu clair.','Светло-синяя сумка.','э(н) сак блё клэ́р'),2,[rev('bleu-clair-meaning',fr('Bleu clair','блё клэ́р'),['Тёмно-синий','Зелёный','Светло-синий'],2,'Указан светлый оттенок синего.','Правильный смысл — светло-синий, а не тёмно-синий или зелёный.'),rev('bleu-clair-use',ru('Нужна именно светло-синяя ручка. Какая запись указывает этот оттенок?'),[fr('Un stylo bleu foncé.','э(н) стило блё фо(н)сэ́'),fr('Un stylo bleu clair.','э(н) стило блё клэ́р'),fr('Un stylo bleu.','э(н) стило блё́')],1,'В записи прямо указан светлый оттенок синего.','Нужен светлый оттенок; тёмный не подходит, а общее название синего оттенок не уточняет.')])
addword('bleu-fonce','fr:bleu-fonce:dark-blue','bleu foncé','тёмно-синий','блё фо(н)сэ́',ex('Un sac bleu foncé.','Тёмно-синяя сумка.','э(н) сак блё фо(н)сэ́'),2,[rev('bleu-fonce-meaning',fr('Bleu foncé','блё фо(н)сэ́'),['Тёмно-синий','Светло-синий','Зелёный'],0,'Указан тёмный оттенок синего.','Правильный смысл — тёмно-синий, а не светлый оттенок или зелёный.'),rev('bleu-fonce-use',ru('Нужна именно тёмно-синяя ручка. Какая запись указывает этот оттенок?'),[fr('Un stylo bleu clair.','э(н) стило блё клэ́р'),fr('Un stylo bleu.','э(н) стило блё́'),fr('Un stylo bleu foncé.','э(н) стило блё фо(н)сэ́')],2,'В записи прямо указан тёмный оттенок.','Нужен тёмно-синий; светлый оттенок не подходит, а общее название синего недостаточно точно.')])
addword('colour-question','fr:de-quelle-couleur:colour-question','de quelle couleur ?','Какого цвета?','дё кэль кулё́р?',ex('De quelle couleur ?','Какого цвета?','дё кэль кулё́р?'),4,[rev('colour-question-meaning',fr('De quelle couleur ?','дё кэль кулё́р?'),['Сколько предметов?','Какого цвета?','Кто это?'],1,'Вопрос уточняет цвет уже названной вещи.','Правильный смысл — «Какого цвета?», а не количество или личность человека.'),rev('colour-question-use',ru('Собеседник уже назвал предмет. Вы хотите уточнить его цвет. Какой вопрос выбрать?'),[wc('qui-est-ce'),fr('De quelle couleur ?','дё кэль кулё́р?'),wc('age-question')],1,'Вопрос относится к цвету предмета.','Нужен вопрос о цвете; вопросы о человеке и возрасте не подходят.')])

s=start(1,'Узнавайте красный, синий и зелёный цвета. Понимайте цвет знакомого предмета и выбирайте нужную вещь по короткой записи.',['rouge','bleu','vert','sac','stylo','livre','cest-identify','mon-possessive','ma-possessive','pere','mere','frere','sil-vous-plait'])
listen(s,['PRE_A1.U06.UNDERSTAND'],['cest-identify','mon-possessive','pere','mere','frere'],['pere'],'Вспомните семью. Кого представили?','C’est mon père.','сэ мо(н) пэ́р.',['Брата','Отца','Мать'],1,'Представлен отец говорящего.','Нужно выбрать отца; мать и брат называются иначе.')
intro(s,2,'Три цвета: rouge — красный, bleu — синий, vert — зелёный. В показанных сочетаниях цвет стоит после названия вещи: un sac rouge — «красная сумка». Названия вещей уже знакомы; сейчас узнаём их цвет.',['rouge','bleu','vert'],[('rouge','ру́ж'),('bleu','блё́'),('vert','вэ́р'),('un sac rouge','э(н) сак ру́ж')],('Rouge. Bleu. Vert. Un sac rouge.','руж. блё. вэр. э(н) сак ру́ж.'))
pairs(s,[U],[('rouge','Красный'),('bleu','Синий'),('vert','Зелёный')],'Вы соединили названия трёх цветов с их значениями.','Красный, синий и зелёный — разные цвета; у каждого своё французское название.')
listen(s,[U],['sac','rouge','bleu','vert'],['bleu'],'Какой цвет сумки назвали?','Un sac bleu.','э(н) сак блё́.',['Зелёный','Красный','Синий'],2,'Названа синяя сумка.','Цвет — синий; зелёный и красный звучат иначе.')
choose(s,'vocabulary',[A],['stylo','rouge','bleu','vert'],['vert'],'Для занятия нужна зелёная ручка. Какую запись выбрать?',ru('Название предмета одинаковое; сравните цвет.'),[fr('Un stylo rouge','э(н) стило ру́ж'),fr('Un stylo vert','э(н) стило вэ́р'),fr('Un stylo bleu','э(н) стило блё́')],1,'В записи названа ручка нужного зелёного цвета.','Нужен зелёный цвет; красная и синяя ручки не соответствуют условию.')
choose(s,'reading',[A],['livre','sac','rouge','vert'],['livre','rouge'],'Какого цвета книга в коротком списке?',fr('Un livre rouge. Un sac vert.','э(н) ливр ру́ж. э(н) сак вэ́р.'),['Зелёного','Красного','Цвет книги не указан'],1,'К книге относится красный цвет; зелёный относится к сумке.','Книга красная. Не переносите цвет сумки на другой предмет.')
fill(s,[A],['sac','rouge','bleu','vert','sil-vous-plait'],['bleu'],'Попросите синюю сумку. Выберите цвет для короткой просьбы.','Un sac ','э(н) са́к',', s’il vous plaît.','силь ву плэ́',[wc('vert'),wc('rouge'),wc('bleu')],2,'Un sac bleu, s’il vous plaît.','э(н) сак блё, силь ву плэ́.','Просьба относится к синей сумке.','Выберите синий цвет; два других варианта изменят нужную вещь.')
order(s,[A],['cest-identify','sac','rouge'],[],'Соберите «Это красная сумка», начав со знакомого указания.',[('C’est','сэ'),('un sac','э(н) са́к'),('rouge.','ру́ж')],'C’est un sac rouge.','сэт э(н) сак ру́ж.','Цвет поставлен после названия предмета.','Начните с указания, затем поставьте название сумки и её цвет.')
page(s,[('Три первых цвета',['Узнавайте каждое слово отдельно, затем в коротком сочетании с уже знакомым предметом. Здесь используются красный, синий и зелёный.'],[W[w]['example'] for w in ['rouge','bleu','vert']]),('Сначала предмет, затем цвет',['В изученных сочетаниях название цвета следует за названием сумки, книги или ручки. Не переносите русский порядок слов автоматически.'],[ex('Un sac rouge.','Красная сумка.','э(н) сак ру́ж'),ex('Un stylo vert.','Зелёная ручка.','э(н) стило вэ́р')]),('Как выбрать нужную вещь',['Проверяйте название и цвет вместе. Красная книга и зелёная сумка — две отдельные записи; цвет одной вещи не относится к другой.'],[ex('Un livre rouge. Un sac vert.','Красная книга. Зелёная сумка.','э(н) ливр ру́ж. э(н) сак вэ́р')])])

s=start(2,'Различайте светло-синий и тёмно-синий оттенки на знакомых вещах. Не выводите точный оттенок из общего названия синего.',['rouge','bleu','vert','bleu-clair','bleu-fonce','sac','stylo','numero','un','deux','sil-vous-plait'])
choose(s,'reading',[U],['stylo','rouge','bleu','vert'],['vert'],'Какой основной цвет назван у ручки?',fr('Un stylo vert.','э(н) стило вэ́р.'),['Синий','Зелёный','Красный'],1,'Назван зелёный цвет.','Ручка зелёная; два других цвета в этой записи не указаны.')
intro(s,2,'Для двух оттенков синего используем готовые сочетания: bleu clair — «светло-синий», bleu foncé — «тёмно-синий». В этом занятии применяем их к знакомой сумке и учим каждое сочетание целиком. Обычное bleu называет синий цвет без уточнения оттенка.',['bleu-clair','bleu-fonce'],[('bleu clair','блё клэ́р'),('bleu foncé','блё фо(н)сэ́'),('bleu','блё́')],('Un sac bleu clair. Un sac bleu foncé.','э(н) сак блё клэ́р. э(н) сак блё фо(н)сэ́.'))
pairs(s,[U],[('bleu-clair','Светло-синий'),('bleu-fonce','Тёмно-синий'),('rouge','Красный')],'Вы различили два оттенка синего и знакомый красный цвет.','Светло-синий и тёмно-синий — разные уточнения; красный относится к другому основному цвету.')
listen(s,[U],['sac','bleu-clair','bleu-fonce','vert'],['bleu-clair'],'Какой оттенок назвали у сумки?','Un sac bleu clair.','э(н) сак блё клэ́р.',['Тёмно-синий','Зелёный','Светло-синий'],2,'Назван светлый оттенок синего.','Сумка светло-синяя, а не тёмно-синяя или зелёная.')
choose(s,'vocabulary',[U],['sac','bleu-clair','bleu-fonce','rouge'],['bleu-fonce'],'В условии нужна именно тёмно-синяя сумка. Какую запись выбрать?',ru('Обратите внимание на уточнение оттенка.'),[fr('Un sac bleu foncé','э(н) сак блё фо(н)сэ́'),fr('Un sac rouge','э(н) сак ру́ж'),fr('Un sac bleu clair','э(н) сак блё клэ́р')],0,'В записи прямо назван тёмный оттенок синего.','Нужна тёмно-синяя сумка; светлый оттенок и красный цвет не подходят.')
choose(s,'reading',[A],['numero','un','deux','sac','bleu-clair','bleu-fonce'],['bleu-clair'],'Выберите номер светло-синей сумки по списку.',fr('Numéro un : un sac bleu clair. Numéro deux : un sac bleu foncé.','нюмэро э(н) : э(н) сак блё клэ́р. нюмэро дё : э(н) сак блё фо(н)сэ́.'),['2','1','Оттенки не указаны'],1,'Светло-синий оттенок указан у номера один.','Выберите номер 1; номер 2 относится к тёмно-синему оттенку.')
fill(s,[A],['sac','bleu','bleu-clair','bleu-fonce','sil-vous-plait'],['bleu-fonce'],'Попросите именно тёмно-синюю сумку. Выберите полное название оттенка.','Un sac ','э(н) са́к',', s’il vous plaît.','силь ву плэ́',[wc('bleu'),wc('bleu-clair'),wc('bleu-fonce')],2,'Un sac bleu foncé, s’il vous plaît.','э(н) сак блё фо(н)сэ, силь ву плэ́.','В просьбе назван требуемый тёмный оттенок.','Нужно полное обозначение тёмно-синего. Общее слово «синий» не уточняет нужный оттенок.')
choose(s,'reading',[U],['sac','bleu','bleu-clair','bleu-fonce'],['bleu'],'Что точно известно об оттенке из этой записи?',fr('Un sac bleu.','э(н) сак блё́.'),['Сумка обязательно светло-синяя','Сумка обязательно тёмно-синяя','Цвет синий, точный оттенок не уточнён'],2,'Назван только общий синий цвет.','Не добавляйте отсутствующее уточнение: светлый или тёмный оттенок здесь не указан.')
page(s,[('Два оттенка синего',['Готовые сочетания называют светлый и тёмный оттенки. В этих примерах используем знакомое название сумки и запоминаем полное сочетание.'],[W['bleu-clair']['example'],W['bleu-fonce']['example']]),('Общее название не равно точному оттенку',['Если назван только синий цвет, нельзя утверждать, что он обязательно светлый или обязательно тёмный. Для такого вывода нужно отдельное уточнение.'],[ex('Un sac bleu.','Синяя сумка, оттенок не уточнён.','э(н) сак блё́')]),('Выбор по записи',['Сначала найдите нужное обозначение оттенка, затем его номер или предмет. Опирайтесь на подпись, а не на воображаемый вид вещи.'],[ex('Un sac bleu foncé, s’il vous plaît.','Тёмно-синюю сумку, пожалуйста.','э(н) сак блё фо(н)сэ, силь ву плэ́')])])

s=start(3,'Понимайте цвет знакомого предмета и выбирайте показанные формы мужского и женского рода. Верните сочетания «мой» и «моя» с вещами.',['rouge','bleu','vert','jaune','bleu-clair','bleu-fonce','sac','lampe','livre','stylo','mon-possessive','ma-possessive','sur-la-table','cest-identify'])
listen(s,[U],['sac','bleu-clair','bleu-fonce','vert'],['bleu-fonce'],'Вспомните оттенки: какой назвали у сумки?','Un sac bleu foncé.','э(н) сак блё фо(н)сэ́.',['Светло-синий','Тёмно-синий','Зелёный'],1,'Назван тёмно-синий оттенок.','Сумка тёмно-синяя; светлый оттенок и зелёный цвет не соответствуют услышанному.')
intro(s,2,'Добавим jaune — «жёлтый». В показанных сочетаниях rouge и jaune сохраняют написание с названиями мужского и женского рода: une lampe rouge — «красная лампа», une lampe jaune — «жёлтая лампа».',['jaune','rouge'],[('jaune','жо́н'),('rouge','ру́ж'),('une lampe rouge','юн ла(н)п ру́ж'),('une lampe jaune','юн ла(н)п жо́н')],('Une lampe rouge. Une lampe jaune.','юн ла(н)п ру́ж. юн ла(н)п жо́н.'))
pairs(s,[U],[('rouge','Красный'),('jaune','Жёлтый'),('bleu','Синий')],'Вы узнали жёлтый цвет и повторили два знакомых.','Не путайте жёлтый с красным или синим: это три разных названия.')
intro(s,3,'У синего цвета сравните формы: un sac bleu — «синяя сумка», une lampe bleue — «синяя лампа». Название сумки во французском мужского рода, название лампы — женского. Две формы цвета звучат одинаково, но пишутся по-разному. Учим только показанную пару.',['bleu'],[('un sac bleu','э(н) сак блё́'),('une lampe bleue','юн ла(н)п блё́')],('Un sac bleu. Une lampe bleue.','э(н) сак блё́. юн ла(н)п блё́.'))
grammar(fill(s,[A],['lampe','bleu'],['bleu'],'Назовите одну синюю лампу. Выберите показанную форму цвета.','Une lampe ','юн ла́(н)п','','',[fr('bleu','блё́'),fr('bleue','блё́')],1,'Une lampe bleue','юн ла(н)п блё́','С названием лампы используется показанная форма женского рода.','Нужна форма для изученного слова женского рода; одинаковое звучание не означает одинаковое написание.'))
intro(s,4,'У зелёного цвета сравните un sac vert — «зелёная сумка» и une lampe verte — «зелёная лампа». В форме verte в конце слышится «т». Цвет в обоих сочетаниях остаётся зелёным. Не переносим один образец на все неизвестные прилагательные.',['vert'],[('un sac vert','э(н) сак вэ́р'),('une lampe verte','юн ла(н)п вэ́рт'),('verte','вэ́рт')],('Un sac vert. Une lampe verte.','э(н) сак вэ́р. юн ла(н)п вэ́рт.'))
listen(s,[A],['lampe','vert','bleu','jaune'],['vert'],'Какого цвета названная лампа?','Une lampe verte.','юн ла(н)п вэ́рт.',['Синего','Жёлтого','Зелёного'],2,'Лампа зелёная; услышана форма цвета для слова женского рода.','Нужен зелёный цвет. Синий и жёлтый в реплике не названы.')
choose(s,'reading',[A],['sur-la-table','lampe','jaune','rouge','vert'],['jaune'],'Какого цвета лампа на столе согласно заметке?',fr('Sur la table : une lampe jaune.','сюр ла табль : юн ла(н)п жо́н.'),['Жёлтого','Красного','Зелёного'],0,'После подписи места указана жёлтая лампа.','Лампа жёлтая; два других цвета в заметке не названы.')
intro(s,6,'Знакомые формы mon и ma можно поставить перед названиями изученных вещей: mon sac bleu — «моя синяя сумка», ma lampe bleue — «моя синяя лампа». Выбор по-прежнему связан с французским родом названия вещи. Цвет остаётся после существительного.',['mon-possessive','ma-possessive'],[('mon','мо́(н)'),('ma','ма'),('mon sac bleu','мо(н) сак блё́'),('ma lampe bleue','ма ла(н)п блё́')])
choose(s,'vocabulary',[A],['mon-possessive','ma-possessive','sac','lampe','bleu','vert'],['lampe','bleu'],'Говорящий называет свою синюю лампу. Какое сочетание подходит?',ru('Проверьте предмет и цвет.'),[fr('Mon sac bleu','мо(н) сак блё́'),fr('Ma lampe verte','ма ла(н)п вэ́рт'),fr('Ma lampe bleue','ма ла(н)п блё́')],2,'Выбрана синяя лампа говорящего.','Нужна синяя лампа; синяя сумка и зелёная лампа отличаются от условия.')
order(s,[A],['cest-identify','lampe','vert'],[],'Соберите «Это зелёная лампа», начав со знакомого указания.',[('C’est','сэ'),('une lampe','юн ла́(н)п'),('verte.','вэ́рт')],'C’est une lampe verte.','сэт юн ла(н)п вэ́рт.','После названия лампы стоит показанная форма зелёного цвета.','Начните с указания, затем поставьте название лампы и форму её цвета.')
page(s,[('Показанные пары форм',['Французский род названия вещи влияет на форму цвета. Для синего цвета две формы звучат одинаково; у зелёного форма с лампой имеет слышимый конечный согласный. Здесь изучаем только приведённые пары.'],[ex('Un sac bleu. Une lampe bleue.','Синяя сумка. Синяя лампа.','э(н) сак блё. юн ла(н)п блё.'),ex('Un sac vert. Une lampe verte.','Зелёная сумка. Зелёная лампа.','э(н) сак вэр. юн ла(н)п вэрт.')]),('Красный и жёлтый в знакомых сочетаниях',['В показанных сочетаниях эти два названия сохраняют форму. Цвет всегда относится к конкретному предмету, а не ко всему списку.'],[ex('Une lampe rouge.','Красная лампа.','юн ла(н)п ру́ж'),ex('Une lampe jaune.','Жёлтая лампа.','юн ла(н)п жо́н')]),('Моя вещь с цветом',['Уже знакомое слово перед названием вещи сообщает, чья она. Название цвета следует за существительным.'],[ex('Mon sac bleu.','Моя синяя сумка.','мо(н) сак блё́'),ex('Ma lampe bleue.','Моя синяя лампа.','ма ла(н)п блё́')])])

s=start(4,'Выбирайте нужную вещь по цвету и номеру. Уточняйте цвет названного предмета и используйте знакомую вежливую просьбу.',['noir','colour-question','rouge','bleu','vert','sac','lampe','stylo','livre','dans-le-sac','numero','un','deux','sil-vous-plait'])
choose(s,'reading',['PRE_A1.U04.UNDERSTAND'],['dans-le-sac','deux','livre','stylo','un'],['deux','livre'],'Повторение вещей: что находится в сумке по заметке?',fr('Dans le sac : deux livres.','да(н) лё сак : дё ли́вр.'),['Одна книга','Две ручки','Две книги'],2,'В сумке указаны две книги.','Нужно учесть и предмет, и число: две книги, а не одна книга или две ручки.')
intro(s,2,'Ещё один цвет: noir — «чёрный». Сравните un sac noir — «чёрная сумка» и une lampe noire — «чёрная лампа». Две показанные формы звучат одинаково; написание выбираем по французскому роду названия вещи.',['noir'],[('noir','нуа́р'),('un sac noir','э(н) сак нуа́р'),('une lampe noire','юн ла(н)п нуа́р')],('Un sac noir. Une lampe noire.','э(н) сак нуа́р. юн ла(н)п нуа́р.'))
choose(s,'vocabulary',[A],['sac','noir','vert','rouge'],['noir'],'Вам нужна чёрная сумка. Какую запись выбрать?',ru('Название вещи одинаковое; сравните цвет.'),[fr('Un sac vert','э(н) сак вэ́р'),fr('Un sac noir','э(н) сак нуа́р'),fr('Un sac rouge','э(н) сак ру́ж')],1,'В записи названа чёрная сумка.','Нужен чёрный цвет; зелёный и красный не соответствуют условию.')
intro(s,3,'Когда предмет уже назван, можно уточнить цвет коротким вопросом De quelle couleur ? — «Какого цвета?». Например, собеседник назвал сумку, а вы спрашиваете именно о её цвете.',['colour-question'],[('De quelle couleur ?','дё кэль кулё́р?')],('Un sac. De quelle couleur ?','э(н) са́к. дё кэль кулё́р?'))
listen(s,[A],['sac','colour-question'],['colour-question'],'Что уточняет собеседник после названия предмета?','Un sac. De quelle couleur ?','э(н) са́к. дё кэль кулё́р?',['Количество сумок','Цвет сумки','Кому принадлежит сумка'],1,'Уточняется цвет уже названной сумки.','Вопрос касается цвета, а не количества или владельца.')
choose(s,'reading',[A],['numero','un','deux','sac','rouge','noir'],['noir'],'Выберите номер чёрной сумки по списку.',fr('Numéro un : un sac rouge. Numéro deux : un sac noir.','нюмэро э(н) : э(н) сак ру́ж. нюмэро дё : э(н) сак нуа́р.'),['1','Номер чёрной сумки не указан','2'],2,'Чёрная сумка указана под номером два.','Выберите 2; номер 1 относится к красной сумке.')
grammar(fill(s,[A],['sac','vert','rouge','sil-vous-plait'],['vert'],'Попросите зелёную сумку. Выберите показанную форму цвета для названия сумки.','Un sac ','э(н) са́к',', s’il vous plaît.','силь ву плэ́',[fr('verte','вэ́рт'),fr('vert','вэ́р'),wc('rouge')],1,'Un sac vert, s’il vous plaît.','э(н) сак вэр, силь ву плэ́.','Название сумки мужского рода, поэтому подходит показанная форма без конечного звучащего «т».','Для этого названия нужна форма мужского рода зелёного цвета; форма с лампой и красный цвет не подходят.'))
choose(s,'vocabulary',[A],['colour-question','noir','deux','dans-le-sac','lampe'],['noir'],'Речь о лампе, которая по условию чёрная. Выберите ответ на уточняющий вопрос.',fr('De quelle couleur ?','дё кэль кулё́р?'),[fr('Deux.','дё́.'),fr('Dans le sac.','да(н) лё са́к.'),fr('Noire.','нуа́р.')],2,'Ответ сообщает заданный чёрный цвет лампы.','Нужен цвет лампы; число и место в сумке не отвечают на вопрос о цвете.')
order(s,[A],['sac','noir','sil-vous-plait'],[],'Соберите просьбу «Чёрную сумку, пожалуйста», начав с названия предмета.',[('Un sac','э(н) са́к'),('noir,','нуа́р'),('s’il vous plaît.','силь ву плэ́')],'Un sac noir, s’il vous plaît.','э(н) сак нуар, силь ву плэ́.','Цвет следует за предметом, затем стоит целая формула вежливости.','Начните с сумки, добавьте цвет и завершите просьбу знакомой вежливой формулой.')
page(s,[('Уточняем цвет названного предмета',['Если собеседник уже назвал вещь, короткий вопрос позволяет узнать её цвет. Не заменяйте ответ сведениями о количестве или месте.'],[W['colour-question']['example'],ex('Noire.','Чёрная — о лампе.','нуа́р')]),('Сверяем предмет, цвет и номер',['В списке каждый цвет относится к конкретной записи. Сначала найдите нужную вещь, затем назовите связанный с ней номер.'],[ex('Un sac noir.','Чёрная сумка.','э(н) сак нуа́р'),ex('Une lampe noire.','Чёрная лампа.','юн ла(н)п нуа́р')]),('Просьба из знакомых частей',['К названию вещи с цветом добавляется уже изученная вежливая формула. Новый длинный образец для этого не требуется.'],[ex('Un sac noir, s’il vous plaît.','Чёрную сумку, пожалуйста.','э(н) сак нуар, силь ву плэ́')])])

s=start(5,'Выберите вещи небольшой витрины по текстовым подписям: предмет, цвет, оттенок и номер. Примените знакомые формы цвета; нового материала нет.',['rouge','bleu','vert','jaune','noir','bleu-clair','bleu-fonce','colour-question','sac','lampe','stylo','livre','numero','un','deux','cest-identify'])
intro(s,1,'Здесь только знакомые слова и формы. Все сведения даны в подписях: проверяйте предмет, цвет, оттенок и номер. Если оттенок не уточнён, не добавляйте его сами. В сборке используйте все карточки один раз.',[])
listen(s,[U],['sac','bleu-clair','bleu-fonce','vert'],['bleu-clair'],'Какой оттенок указан у сумки?','Un sac bleu clair.','э(н) сак блё клэ́р.',['Тёмно-синий','Светло-синий','Зелёный'],1,'Назван светлый оттенок синего.','Нужен светло-синий, а не тёмно-синий или зелёный.')
choose(s,'reading',[A],['numero','un','deux','lampe','bleu','jaune'],['jaune'],'По подписям выберите номер жёлтой лампы.',fr('Numéro un : une lampe bleue. Numéro deux : une lampe jaune.','нюмэро э(н) : юн ла(н)п блё́. нюмэро дё : юн ла(н)п жо́н.'),['2','Жёлтая лампа не указана','1'],0,'Жёлтая лампа находится в записи с номером два.','Выберите 2; в первой записи лампа синяя.')
pairs(s,[U],[('rouge','Красный'),('bleu-fonce','Тёмно-синий'),('noir','Чёрный')],'Вы различили два основных цвета и знакомый оттенок синего.','Не смешивайте красный и чёрный с тёмным оттенком синего.')
listen(s,[A],['cest-identify','lampe','sac','vert','bleu'],['lampe','vert'],'Какой предмет и цвет названы?','C’est une lampe verte.','сэт юн ла(н)п вэ́рт.',['Синяя лампа','Зелёная сумка','Зелёная лампа'],2,'Названа зелёная лампа.','Проверьте обе части: предмет — лампа, цвет — зелёный.')
grammar(fill(s,[A],['lampe','bleu','vert'],['bleu'],'Назовите синюю лампу. Выберите показанную форму цвета для этого существительного.','Une lampe ','юн ла́(н)п','','',[fr('bleu','блё́'),fr('bleue','блё́'),fr('vert','вэ́р')],1,'Une lampe bleue','юн ла(н)п блё́','Для названия лампы нужна форма женского рода синего цвета.','Нужна показанная форма женского рода: мужская форма и зелёный цвет не подходят.'))
choose(s,'vocabulary',[A],['stylo','sac','rouge','noir'],['stylo','rouge'],'Для занятия нужна красная ручка. Какую запись выбрать?',ru('Учитывайте одновременно предмет и цвет.'),[fr('Un sac rouge','э(н) сак ру́ж'),fr('Un stylo noir','э(н) стило нуа́р'),fr('Un stylo rouge','э(н) стило ру́ж')],2,'В записи совпадают нужный предмет и красный цвет.','Нужна красная ручка; красная сумка и чёрная ручка отличаются от условия.')
order(s,[A],['cest-identify','sac','noir'],[],'Соберите «Это чёрная сумка», начав со знакомого указания.',[('C’est','сэ'),('un sac','э(н) са́к'),('noir.','нуа́р')],'C’est un sac noir.','сэт э(н) сак нуа́р.','Цвет стоит после названия сумки.','Начните с указания, затем поставьте название сумки и её цвет.')
choose(s,'reading',[U],['colour-question','bleu','bleu-clair','bleu-fonce'],['bleu-fonce'],'При выборе сумки уточнили цвет. Какой оттенок указан в ответе?',fr('De quelle couleur ? Bleu foncé.','дё кэль кулё́р? блё фо(н)сэ́.'),['Светло-синий','Оттенок не назван','Тёмно-синий'],2,'Ответ уточняет тёмный оттенок синего.','Указан тёмно-синий, а не светлый оттенок и не только общий синий цвет.')
page(s,[('Читаем подпись вещи',['Сначала определите название предмета, затем его цвет. Если у записи есть номер, он помогает указать нужную вещь. Всё необходимое для ответа дано в тексте.'],[ex('Une lampe jaune.','Жёлтая лампа.','юн ла(н)п жо́н'),ex('Un stylo rouge.','Красная ручка.','э(н) стило ру́ж')]),('Не угадываем оттенок',['Общий цвет и точный оттенок — разные по полноте сообщения. Если указан оттенок, сохраняйте его в ответе; если не указан, не добавляйте его по собственной догадке.'],[W['bleu-clair']['example'],W['bleu-fonce']['example']]),('Предмет с цветом в реплике',['В знакомых образцах название цвета следует за названием вещи. Форму выбирайте по показанным сочетаниям мужского и женского рода.'],[ex('C’est un sac noir.','Это чёрная сумка.','сэт э(н) сак нуа́р'),ex('C’est une lampe verte.','Это зелёная лампа.','сэт юн ла(н)п вэ́рт')])])

for s in S:
 required=list(dict.fromkeys(w for t in s['tasks'] for w in t['vocabulary_ids']))
 required+=list(dict.fromkeys(w for i in s['introductions'] for w in i['vocabulary_ids']))
 present={w['id'] for w in s['vocabulary']}
 for w in required:
  if w not in present:s['vocabulary'].append(copy.deepcopy(W[w]));present.add(w)
 s['skills']=list(dict.fromkeys(t['skill'] for t in s['tasks']))
NOTES={w['fr']:w['transcription_ru'] for w in W.values()}
NOTES.update({'bleue':'блё́','verte':'вэ́рт','noire':'нуа́р','mon':'мо́(н)','ma':'ма','De quelle couleur ?':'дё кэль кулё́р?'})
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
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Пять цветов и два оттенка синего в подписях знакомых вещей: узнавание, формы цвета и выбор нужного предмета.',learning_outcomes=['Понимать названия пяти изученных цветов и двух оттенков синего.','Различать общий цвет и уточнённый светлый или тёмный оттенок.','Ставить показанное название цвета после существительного и выбирать знакомую форму рода.','Выбирать вещь по предмету, цвету, оттенку и номеру; уточнять цвет коротким вопросом.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u07-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'PRE_A1-U07.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('U07 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('rouge','Сохранить известный цвет как контраст к новому признаку размера у знакомого предмета.'),('bleu','Вернуться к паре показанных форм рода и не переносить положение цвета на любой новый признак.'),('vert','Повторить известную форму женского рода на знакомом предмете при уместном возврате.'),('sac','Использовать уже знакомый предмет для введения большого и маленького без новых названий вещей.'),('lampe','Повторить предмет женского рода, если нужен пример формы нового признака.'),('cest-identify','Сохранить знакомый образец указания, добавляя только новый изучаемый признак.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['До импорта U07 целевая база должна содержать PRE_A1-U06-L05. Подготовленный файл не создаёт отсутствующую зависимость.','Человеку нужно проверить язык и браузерную озвучку цветов, вопроса и целых фраз; особенно различие звучания форм зелёного цвета.','Выполнить предпросмотр, пробное прохождение и замер длительности. Ориентир 6 минут на занятие не измерен.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U08',previous_unit='PRE_A1-U06',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку U07; отдельное подтверждение языкового принятия или импорта U06 не получено.','Показанные формы bleue, verte, noire сохраняют ключи соответствующих цветов; новые значения для рода не создаются.','Два оттенка синего выучиваются как готовые сочетания только с названиями мужского рода. Их согласование с существительными женского рода и правила составных цветов не проверяются.','Из общего названия синего не выводится точный светлый или тёмный оттенок.','Короткий вопрос о цвете используется только после уже названного предмета.','Названия вещей, числа, формы mon/ma и образец указания сохраняют прежние ключи.','В L01–L04 соответственно 3, 2, 1 и 2 новых словарных значения; в L03 и L04 дополнительно объясняются формы рода. В L05 нового материала нет.','Все сведения для ответов даны текстом; задания не зависят от картинки, передачи цвета экраном или личных предпочтений.','Сборка подписанной фразы не помечена оценкой всех слов: assessed_vocabulary_ids в word_order пуст.','Основной LEXEME_REGISTRY.json и предыдущие пакеты не изменены. Новые ключи предложены отдельно до принятия.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Цвет после названия предмета в показанных сочетаниях.'),dict(session_id=S[2]['id'],material='Формы bleu/bleue и vert/verte, неизменная форма rouge и jaune в приведённых сочетаниях, перенос известных mon/ma на знакомые вещи.'),dict(session_id=S[3]['id'],material='Пара noir/noire в показанных сочетаниях с сумкой и лампой.')]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
(OUT/'PRE_A1-U07.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))
