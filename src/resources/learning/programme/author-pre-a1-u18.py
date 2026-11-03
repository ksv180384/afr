"""Author PRE_A1-U18 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][17]
W={}
for source in [OUT/f'PRE_A1-U{n:02}.v1.json' for n in range(17,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='PRE_A1.U18.UNDERSTAND';A='PRE_A1.U18.APPLY'
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
addword('ami','fr:ami:friend','un ami','друг (мужской род в показанном образце)','э(н) нами́',ex('C’est mon ami.','Это мой друг.','сэ мо(н) нами́'),1,[rev('ami-meaning',fr('Un ami','э(н) нами́'),['Сосед','Друг','Учитель'],1,'Назван друг.','Правильное значение — друг; сосед и учитель обозначают другие отношения или роли.'),rev('ami-use',ru('Нужно представить человека словами «Это мой друг». Какую реплику выбрать?'),[fr('C’est mon voisin.','сэ мо(н) вуазэ́(н)'),fr('C’est mon ami.','сэ мо(н) нами́'),fr('C’est mon père.','сэ мо(н) пэ́р')],1,'Выбрано представление друга.','Нужен друг; сосед и отец не соответствуют условию.')])
addword('voisin','fr:voisin:neighbor','un voisin','сосед (мужской род в показанном образце)','э(н) вуазэ́(н)',ex('C’est mon voisin.','Это мой сосед.','сэ мо(н) вуазэ́(н)'),1,[rev('voisin-meaning',fr('Un voisin','э(н) вуазэ́(н)'),['Сосед','Отец','Друг'],0,'Назван сосед.','Правильное значение — сосед; отец и друг обозначают другие отношения.'),rev('voisin-use',ru('По условию нужно сказать «Это мой сосед». Какую реплику выбрать?'),[fr('C’est mon ami.','сэ мо(н) нами́'),fr('C’est mon père.','сэ мо(н) пэ́р'),fr('C’est mon voisin.','сэ мо(н) вуазэ́(н)')],2,'Выбрано представление соседа.','Нужен сосед; друг и отец не сохраняют условие.')])
addword('professeur','fr:professeur:teacher','un professeur','учитель, преподаватель (в показанном образце)','э(н) профэсё́р',ex('Il est professeur.','Он учитель.','иль э профэсё́р'),2,[rev('professeur-meaning',fr('Un professeur','э(н) профэсё́р'),['Ученик','Сосед','Учитель, преподаватель'],2,'Названа преподавательская роль.','Нужен учитель; ученик и сосед обозначают другие роли.'),rev('professeur-use',ru('Нужно передать «Он учитель» с изученным началом о нём. Какую реплику выбрать?'),[fr('Il est professeur.','иль э профэсё́р'),fr('Il est élève.','иль э тэлэ́в'),fr('C’est mon voisin.','сэ мо(н) вуазэ́(н)')],0,'Он назван учителем.','Нужна роль учителя; ученик и сосед не соответствуют условию.')])
addword('eleve','fr:eleve:pupil','un élève','ученик; ученица при показанном женском артикле','э(н) нэлэ́в',ex('C’est un élève.','Это ученик.','сэ тэ(н) нэлэ́в'),2,[rev('eleve-meaning',fr('Un élève','э(н) нэлэ́в'),['Учитель','Ученик','Сосед'],1,'Назван ученик.','Нужен ученик, а не учитель или сосед.'),rev('eleve-use',ru('Нужно передать «Она ученица» с изученным началом о ней. Какую реплику выбрать?'),[fr('Il est professeur.','иль э профэсё́р'),fr('Il est élève.','иль э тэлэ́в'),fr('Elle est élève.','эль э тэлэ́в')],2,'Она названа ученицей.','Нужно указание на неё и роль ученицы; сообщение о нём или об учителе меняет условие.')])
addword('medecin','fr:medecin:doctor','un médecin','врач','э(н) мэдсэ́(н)',ex('Elle est médecin.','Она врач.','эль э мэдсэ́(н)'),3,[rev('medecin-meaning',fr('Un médecin','э(н) мэдсэ́(н)'),['Врач','Продавец','Ученик'],0,'Назван врач.','Нужна роль врача; продавец и ученик обозначают другие занятия.'),rev('medecin-use',ru('Нужно сообщить «Она врач». Какую реплику выбрать?'),[fr('Il est médecin.','иль э мэдсэ́(н)'),fr('Elle est médecin.','эль э мэдсэ́(н)'),fr('Il est vendeur.','иль э ва(н)дё́р')],1,'Она названа врачом.','Сохраните указание на неё и роль врача; другой человек или продавец не соответствуют условию.')])
addword('vendeur','fr:vendeur:salesperson','un vendeur','продавец (мужской род в показанном образце)','э(н) ва(н)дё́р',ex('Il est vendeur.','Он продавец.','иль э ва(н)дё́р'),3,[rev('vendeur-meaning',fr('Un vendeur','э(н) ва(н)дё́р'),['Врач','Учитель','Продавец'],2,'Назван продавец.','Нужна роль продавца; врач и учитель обозначают другие занятия.'),rev('vendeur-use',ru('Нужно сообщить «Он продавец». Какую реплику выбрать?'),[fr('Il est vendeur.','иль э ва(н)дё́р'),fr('Il est médecin.','иль э мэдсэ́(н)'),fr('Il est professeur.','иль э профэсё́р')],0,'Он назван продавцом.','Нужна роль продавца; врач и учитель не сохраняют условие.')])

def cards(ids):return [wc(x) for x in ids]
s=start(1,'Различайте друга и соседа в коротком представлении человека. Вернитесь к знакомой подсказке о расположении вещи.',['ami','voisin','mon-possessive','cest-identify','pere','livre','sous','chaise'])
choose(s,'reading',['PRE_A1.U17.UNDERSTAND'],['livre','sous','chaise'],['sous'],'Где находится вещь в знакомой реплике?',fr('Le livre est sous la chaise.','лё ливр э су ла шэ́з.'),['На стуле','Под столом','Под стулом'],2,'Книга под стулом.','Нужны положение под и стул как опора; поверхность стула и стол меняют описание.')
intro(s,2,'Un ami — «друг», un voisin — «сосед». Здесь показаны формы мужского рода. Знакомые образцы представления и слово мой используем так: C’est mon ami. — «Это мой друг», C’est mon voisin. — «Это мой сосед». Эти слова называют отношение к человеку, а не его профессию. Нужное отношение дано в условии; по голосу его не угадывают.',['ami','voisin'],[('Un ami','э(н) нами́'),('un voisin','э(н) вуазэ́(н)'),('C’est mon ami.','сэ мо(н) нами́'),('C’est mon voisin.','сэ мо(н) вуазэ́(н)')],('C’est mon ami. C’est mon voisin.','сэ мо(н) нами́. сэ мо(н) вуазэ́(н).'))
pairs(s,[U],[('ami','Друг'),('voisin','Сосед'),('pere','Отец')],'Вы различили друга, соседа и знакомую семейную роль.','Эти слова называют разные отношения; выбирайте то, которое прямо задано в условии.')
listen(s,[U],['ami','voisin','mon-possessive','cest-identify'],['voisin'],'Кого представляет человек?','C’est mon voisin.','сэ мо(н) вуазэ́(н).',['Друга','Соседа','Отца'],1,'Говорящий представляет соседа.','Назван сосед, а не друг или отец; отношение определяется словами фразы.')
choose(s,'vocabulary',[U],['ami','voisin','pere','mon-possessive','cest-identify'],['ami'],'В условии нужно сказать «Это мой друг». Что выбрать?',ru('Сохраните отношение из условия.'),[fr('C’est mon ami.','сэ мо(н) нами́.'),fr('C’est mon voisin.','сэ мо(н) вуазэ́(н).'),fr('C’est mon père.','сэ мо(н) пэ́р.')],0,'Выбрано представление друга.','Нужен друг; сосед и отец не соответствуют заданному отношению.')
choose(s,'reading',[U],['voisin','mon-possessive','cest-identify'],['voisin'],'Какая информация прямо дана в реплике?',fr('C’est mon voisin.','сэ мо(н) вуазэ́(н).'),['Этот человек — друг говорящего','Названа профессия человека','Этот человек — сосед говорящего'],2,'Прямо названо соседство с говорящим.','Назван сосед; о дружбе и профессии в этой реплике ничего не сказано.')
fill(s,[U],['voisin','ami','pere','mon-possessive','cest-identify'],['voisin'],'Дополните «Это мой сосед».','C’est mon ','сэ мо́(н)','','',[fr('ami','ами́'),fr('voisin','вуазэ́(н)'),fr('père','пэ́р')],1,'C’est mon voisin','сэ мо(н) вуазэ́(н)','Выбрано нужное отношение — сосед.','Нужен сосед; друг и отец меняют смысл условия.')
order(s,[U],['ami','mon-possessive','cest-identify'],[],'Соберите «Это мой друг». Начните со знакомой формы представления.',[('C’est','сэ'),('mon','мо́(н)'),('ami.','ами́')],'C’est mon ami.','сэ мо(н) нами́.','Получилось представление друга с известной формой мой.','Поставьте форму представления первой, затем мой и название друга.')
page(s,[('Два отношения к человеку',['Друг и сосед — разные отношения. В этой теме показаны выбранные формы мужского рода; определять профессию или голос по этим словам не требуется.'],[W['ami']['example'],W['voisin']['example']]),('Знакомое представление',['Для представления используется уже встречавшийся семейный образец. Слово мой сохраняет прежний ключ и значение.'],[ex('C’est mon ami.','Это мой друг.','сэ мо(н) нами́')]),('Только прямо названные сведения',['Сообщение о соседстве само по себе не называет профессию и не утверждает дружбу. В вопросе выбирайте информацию, которая действительно дана.'],[W['voisin']['example']])])
s=start(2,'Различайте учителя и ученика. Сравните несколько показанных образцов представления и называния занятия.',['professeur','eleve','voisin','ami','mon-possessive','cest-identify','il','elle'])
choose(s,'reading',[U],['ami','voisin','mon-possessive','cest-identify'],['ami'],'Кого представляет говорящий знакомой репликой?',fr('C’est mon ami.','сэ мо(н) нами́.'),['Соседа','Друга','Учителя'],1,'Говорящий представляет друга.','Назван друг; сосед и учитель не соответствуют реплике.')
intro(s,2,'Un professeur — «учитель, преподаватель», un élève — «ученик». Образцы представления: C’est un professeur. — «Это учитель», C’est un élève. — «Это ученик». Учим полные фразы с показанными артиклями. Роль человека узнаём по словам, а не по возрасту или голосу.',['professeur','eleve'],[('Un professeur','э(н) профэсё́р'),('un élève','э(н) нэлэ́в'),('C’est un professeur.','сэ тэ(н) профэсё́р'),('C’est un élève.','сэ тэ(н) нэлэ́в')],('C’est un professeur. C’est un élève.','сэ тэ(н) профэсё́р. сэ тэ(н) нэлэ́в.'))
pairs(s,[U],[('professeur','Учитель, преподаватель'),('eleve','Ученик'),('voisin','Сосед')],'Вы различили учителя, ученика и знакомого соседа.','Учитель и ученик — разные роли; сосед обозначает отношение, а не учебную роль.')
listen(s,[U],['eleve','professeur','voisin','cest-identify'],['eleve'],'Кто назван в представлении?','C’est un élève.','сэ тэ(н) нэлэ́в.',['Это учитель','Это сосед','Это ученик'],2,'Это ученик.','Нужно значение ученик; учитель и сосед называются иначе.')
intro(s,4,'Теперь два коротких образца с уже знакомыми указаниями на человека: Il est professeur. — «Он учитель», Elle est élève. — «Она ученица». В этих показанных фразах название занятия или роли стоит без артикля. Для представления ученицы есть другой целый образец C’est une élève. — «Это ученица». Запоминаем эти примеры; общего правила для всех употреблений не требуется.',['professeur','eleve'],[('Il est professeur.','иль э профэсё́р'),('Elle est élève.','эль э тэлэ́в'),('C’est une élève.','сэ тюн элэ́в')],('Il est professeur. Elle est élève. C’est une élève.','иль э профэсё́р. эль э тэлэ́в. сэ тюн элэ́в.'))
choose(s,'vocabulary',[U],['il','elle','professeur','eleve'],['professeur'],'Нужно сказать «Он учитель» с изученным началом о нём. Что выбрать?',ru('Сохраните роль из условия.'),[fr('Il est élève.','иль э тэлэ́в.'),fr('Il est professeur.','иль э профэсё́р.'),fr('Elle est élève.','эль э тэлэ́в.')],1,'Он назван учителем.','Нужен учитель с началом о нём; ученик и сообщение о ней меняют условие.')
choose(s,'reading',[U],['il','elle','professeur','eleve'],['eleve','elle'],'Кто в этих репликах назван учащимся?',fr('Il est professeur. Elle est élève.','иль э профэсё́р. эль э тэлэ́в.'),['Он','Она','Оба'],1,'Она названа ученицей, а он — учителем.','Роль учащегося относится к ней; в сообщении о нём назван учитель.')
grammar(fill(s,[U],['il','professeur'],[],'Восстановите показанный короткий образец «Он учитель».','Il est ','иль э́','','',[fr('un professeur','э(н) профэсё́р'),fr('professeur','профэсё́р')],1,'Il est professeur','иль э профэсё́р','Восстановлен показанный образец с названием профессии без артикля.','В показанном нейтральном образце название профессии идёт без артикля; сравните его с ранее показанной формой представления.'))
order(s,[U],['elle','eleve'],[],'Соберите «Она ученица». Начните с указания на неё и используйте показанный образец.',[('Elle','э́ль'),('est','э'),('élève.','элэ́в')],'Elle est élève.','эль э тэлэ́в.','Получился изученный образец о ней и роли ученицы.','Поставьте указание на неё первым, затем связующую форму и название роли.')
page(s,[('Учитель и ученик',['Названия учатся вместе с показанными артиклями. Для представления даны короткие фразы с ними.'],[ex('C’est un professeur.','Это учитель.','сэ тэ(н) профэсё́р'),W['eleve']['example']]),('Называем роль знакомого человека',['В этих конкретных образцах после указания на человека и связующей формы название занятия или роли стоит без артикля. Полная система употребления здесь не выводится.'],[W['professeur']['example'],ex('Elle est élève.','Она ученица.','эль э тэлэ́в')]),('Указание на человека в словах',['Женский артикль в показанном представлении ученицы и начало о ней дают нужную языковую информацию. Пол синтетического голоса не используется.'],[ex('C’est une élève.','Это ученица.','сэ тюн элэ́в')])])

s=start(3,'Выбирайте фразу о враче или продавце. Сохраняйте указание на человека и его прямо названное занятие.',['medecin','vendeur','professeur','cest-identify','il','elle','il-pleut','soleil'])
choose(s,'reading',['PRE_A1.U15.UNDERSTAND'],['il-pleut','soleil'],['il-pleut'],'Что сообщает знакомая погодная фраза?',fr('Il pleut.','иль плё́.'),['Солнечно','Тепло','Идёт дождь'],2,'Идёт дождь.','Фраза сообщает дождь, а не солнце или температуру.')
intro(s,2,'Un médecin — «врач», un vendeur — «продавец» в показанной мужской форме. Используем знакомые короткие образцы: Il est médecin. — «Он врач», Il est vendeur. — «Он продавец». Для женщины-врача в нашем примере: Elle est médecin. — «Она врач». Название врача здесь сохраняет ту же форму. Роль задаётся словами, а не предполагаемым полом человека или синтетическим голосом.',['medecin','vendeur'],[('Un médecin','э(н) мэдсэ́(н)'),('un vendeur','э(н) ва(н)дё́р'),('Il est médecin.','иль э мэдсэ́(н)'),('Il est vendeur.','иль э ва(н)дё́р'),('Elle est médecin.','эль э мэдсэ́(н)')],('Il est médecin. Il est vendeur. Elle est médecin.','иль э мэдсэ́(н). иль э ва(н)дё́р. эль э мэдсэ́(н).'))
pairs(s,[A,U],[('medecin','Врач'),('vendeur','Продавец'),('professeur','Учитель, преподаватель')],'Вы различили врача, продавца и знакомого учителя.','Сохраните роль из условия; эти названия обозначают разные занятия.')
listen(s,[A],['elle','il','medecin','vendeur'],['medecin','elle'],'Кто назван и какое занятие указано?','Elle est médecin.','эль э мэдсэ́(н).',['Она врач','Он врач','Он продавец'],0,'Она названа врачом.','Нужны указание на неё и врач; сообщение о нём или продавец меняют условие.')
choose(s,'vocabulary',[A],['il','medecin','vendeur','professeur'],['vendeur'],'Нужно сказать «Он продавец» с изученным началом о нём. Что выбрать?',ru('Выберите занятие из условия.'),[fr('Il est médecin.','иль э мэдсэ́(н).'),fr('Il est vendeur.','иль э ва(н)дё́р.'),fr('Il est professeur.','иль э профэсё́р.')],1,'Он назван продавцом.','Нужен продавец; врач и учитель не соответствуют условию.')
choose(s,'reading',[A],['elle','il','medecin','vendeur'],['medecin','elle'],'Кто в двух репликах назван врачом?',fr('Elle est médecin. Il est vendeur.','эль э мэдсэ́(н). иль э ва(н)дё́р.'),['Он','Оба','Она'],2,'Врачом названа она; он назван продавцом.','Сопоставьте начало и занятие в одной реплике: врач — она, продавец — он.')
fill(s,[A],['il','vendeur','medecin','professeur'],['vendeur'],'Дополните «Он продавец».','Il est ','иль э́','','',[fr('vendeur','ва(н)дё́р'),fr('médecin','мэдсэ́(н)'),fr('professeur','профэсё́р')],0,'Il est vendeur','иль э ва(н)дё́р','Выбрана роль продавца.','Нужен продавец; врач и учитель меняют заданное занятие.')
order(s,[A],['elle','medecin'],[],'Соберите «Она врач». Начните с указания на неё и используйте показанный образец.',[('Elle','э́ль'),('est','э'),('médecin.','мэдсэ́(н)')],'Elle est médecin.','эль э мэдсэ́(н).','Получилась реплика о ней и профессии врача.','Поставьте указание на неё первым, затем связующую форму и название врача.')
page(s,[('Два занятия',['Новые названия даны с артиклем в словарной форме. В показанном коротком сообщении о профессии артикль перед названием не ставится.'],[ex('Il est médecin.','Он врач.','иль э мэдсэ́(н)'),W['vendeur']['example']]),('Человек определяется словами',['В показанном примере о женщине-враче название профессии сохраняет ту же форму. Кто назван, определяется началом фразы, а не голосом.'],[W['medecin']['example']]),('Читаем обе реплики',['У каждого названного человека своя прямо указанная роль. Не переносите профессию из одной реплики в другую.'],[ex('Elle est médecin. Il est vendeur.','Она врач. Он продавец.','эль э мэдсэ́(н). иль э ва(н)дё́р')])])
s=start(4,'Узнавайте знакомый вопрос кто это и соединяйте имя с прямо названной ролью. Представьте человека двумя короткими репликами.',['qui-est-ce','cest-identify','cest-ou','quel-temps','il','elle','medecin','vendeur','froid','il-pleut'])
choose(s,'reading',['PRE_A1.U15.UNDERSTAND'],['froid','il-pleut'],['froid'],'Что сообщает знакомый погодный образец?',fr('Il fait froid.','иль фэ фруа́.'),['Тепло','Холодно','Идёт дождь'],1,'Названа холодная погода.','Нужен смысл холодно; тепло и дождь выражаются другими образцами.')
intro(s,2,'Знакомый вопрос Qui est-ce ? означает «Кто это?» В ответ можно представить человека по имени: C’est Paul. — «Это Поль», C’est Lila. — «Это Лила». Имена служат учебными ярлыками; запоминать биографию или выводить профессию из имени не нужно. В каждом задании используйте прямо данный текст.',['qui-est-ce'],[('Qui est-ce ?','ки э́с'),('C’est Paul.','сэ по́ль'),('C’est Lila.','сэ лила́')],('Qui est-ce ? C’est Lila.','ки э́с? сэ лила́.'))
choose(s,'vocabulary',[A],['qui-est-ce','cest-ou','quel-temps'],['qui-est-ce'],'Вы хотите уточнить, кто представленный человек. Какой знакомый вопрос выбрать?',ru('Нужен вопрос о человеке.'),[fr('Qui est-ce ?','ки э́с?'),fr('C’est où ?','сэ у́?'),fr('Quel temps fait-il ?','кэль та(н) фэ ти́ль?')],0,'Выбран вопрос кто это.','Нужно спросить кто человек; расположение и погода — другие цели вопроса.')
listen(s,[A],['qui-est-ce','cest-ou','quel-temps'],['qui-est-ce'],'Что спрашивает человек?','Qui est-ce ?','ки э́с?',['Где это','Какая погода','Кто это'],2,'Это вопрос кто это.','Вопрос уточняет человека; расположение и погода им не спрашиваются.')
phrase_pairs(s,[A,'PRE_A1.U16.APPLY','PRE_A1.U15.APPLY'],[('Qui est-ce ?','ки э́с?','Кто это?',['qui-est-ce'],['qui-est-ce']),('C’est où ?','сэ у́?','Где это?',['cest-ou'],['cest-ou']),('Quel temps fait-il ?','кэль та(н) фэ ти́ль?','Какая погода?',['quel-temps'],['quel-temps'])],'Вы различили три знакомые цели вопроса.','Сопоставьте вопрос с его целью: человек, расположение или погода.')
intro(s,5,'Соединяем два уже показанных образца. C’est Lila. Elle est médecin. — «Это Лила. Она врач». C’est Paul. Il est vendeur. — «Это Поль. Он продавец». Имя и занятие относятся к одному человеку внутри такого короткого представления. Сведения берите из этих реплик; имя само по себе не доказывает профессию.',['il','elle'],[('C’est Lila. Elle est médecin.','сэ лила́. эль э мэдсэ́(н)'),('C’est Paul. Il est vendeur.','сэ по́ль. иль э ва(н)дё́р')],('C’est Lila. Elle est médecin. C’est Paul. Il est vendeur.','сэ лила́. эль э мэдсэ́(н). сэ по́ль. иль э ва(н)дё́р.'))
choose(s,'reading',[A],['cest-identify','elle','il','medecin','vendeur'],['medecin'],'Кто назван врачом в этом тексте?',fr('C’est Lila. Elle est médecin. C’est Paul. Il est vendeur.','сэ лила́. эль э мэдсэ́(н). сэ по́ль. иль э ва(н)дё́р.'),['Поль','Лила','Оба'],1,'В этом тексте врачом названа Лила.','У Лилы прямо назван врач; у Поля — продавец. Не переносите занятие из одной пары реплик в другую.')
grammar(fill(s,[A],['cest-identify','il','vendeur'],[],'Восстановите показанный короткий образец «Это Поль. Он продавец».','C’est Paul. Il est ','сэ по́ль. иль э́','','',[fr('un vendeur','э(н) ва(н)дё́р'),fr('vendeur','ва(н)дё́р')],1,'C’est Paul. Il est vendeur','сэ по́ль. иль э ва(н)дё́р','Восстановлено короткое представление с профессией в показанном образце.','В этой показанной фразе о занятии название стоит без артикля; не подменяйте её другой формой представления.'))
order(s,[A],['cest-identify','elle','medecin'],[],'Соберите «Это Лила. Она врач». Начните с представления по имени, затем назовите её занятие.',[('C’est Lila.','сэ лила́'),('Elle est','эль э́'),('médecin.','мэдсэ́(н)')],'C’est Lila. Elle est médecin.','сэ лила́. эль э мэдсэ́(н).','Получилось короткое представление с прямо названной профессией.','Поставьте представление по имени первым, затем начало о ней и название врача.')
page(s,[('Знакомый вопрос о человеке',['Вопрос кто это уже встречался при представлении родственника. Здесь его ключ и значение сохраняются.'],[W['qui-est-ce']['example']]),('Имя и роль',['Короткое представление может состоять из имени и отдельной реплики о занятии. Выводите роль из текста, а не из звучания имени.'],[ex('C’est Lila. Elle est médecin.','Это Лила. Она врач.','сэ лила́. эль э мэдсэ́(н)'),ex('C’est Paul. Il est vendeur.','Это Поль. Он продавец.','сэ по́ль. иль э ва(н)дё́р')]),('Самостоятельные учебные тексты',['Имена служат ярлыками внутри конкретного задания. В другом задании данные могут быть другими; постоянная биография персонажей не предполагается.'],[ex('C’est Paul.','Это Поль.','сэ по́ль')])])

s=start(5,'Представляйте другого человека по прямо данным отношениям, имени и роли. Повторите все шесть обозначений людей. Нового материала нет.',['ami','voisin','professeur','eleve','medecin','vendeur','mon-possessive','cest-identify','il','elle','qui-est-ce','cest-ou','quel-temps','pere'])
intro(s,1,'Здесь только знакомые слова и образцы. У каждого задания свой учебный текст: сведения о человеке берите из него. Не переносите профессию из другого задания и не выводите её из имени или голоса. При сборке следуйте указанному началу.',[])
listen(s,[U,A],['cest-identify','mon-possessive','ami','voisin','il','medecin','vendeur'],['ami','medecin'],'Какое отношение и занятие прямо названы?','C’est mon ami. Il est médecin.','сэ мо(н) нами́. иль э мэдсэ́(н).',['Мой сосед; врач','Мой друг; продавец','Мой друг; врач'],2,'Это друг говорящего, и он назван врачом.','Сохраните оба сведения: друг и врач. Сосед или продавец меняет прямо названную информацию.')
choose(s,'reading',[U],['cest-identify','il','elle','professeur','eleve'],['eleve'],'Кто назван учащимся в этом учебном тексте?',fr('C’est Paul. Il est professeur. C’est Lila. Elle est élève.','сэ по́ль. иль э профэсё́р. сэ лила́. эль э тэлэ́в.'),['Лила','Поль','Оба'],0,'В этом тексте учащаяся — Лила; Поль назван учителем.','Сведения этого задания самостоятельны: здесь Лила названа ученицей, а Поль учителем.')
pairs(s,[U,A],[('ami','Друг'),('voisin','Сосед'),('medecin','Врач'),('vendeur','Продавец')],'Вы различили два отношения и два занятия.','Друг и сосед обозначают отношения; врач и продавец — прямо названные занятия.')
listen(s,[U],['elle','il','eleve','professeur'],['elle','eleve'],'Кто назван и какая роль указана?','Elle est élève.','эль э тэлэ́в.',['Она учитель','Она ученица','Он ученик'],1,'Она названа ученицей.','Нужны указание на неё и роль ученицы; учитель или сообщение о нём меняет смысл.')
choose(s,'vocabulary',[A],['qui-est-ce','cest-ou','quel-temps'],['qui-est-ce'],'Вы хотите спросить, кто представленный человек. Что выбрать?',ru('Выберите цель вопроса по условию.'),[fr('C’est où ?','сэ у́?'),fr('Quel temps fait-il ?','кэль та(н) фэ ти́ль?'),fr('Qui est-ce ?','ки э́с?')],2,'Выбран вопрос о том, кто этот человек.','Нужно кто это; расположение и погода спрашиваются другими вопросами.')
fill(s,[U],['cest-identify','mon-possessive','voisin','ami','pere'],['voisin'],'Дополните «Это мой сосед».','C’est mon ','сэ мо́(н)','','',[fr('père','пэ́р'),fr('ami','ами́'),fr('voisin','вуазэ́(н)')],2,'C’est mon voisin','сэ мо(н) вуазэ́(н)','Выбрано отношение сосед.','Нужен сосед; отец и друг не соответствуют условию.')
order(s,[A],['cest-identify','il','vendeur'],[],'Соберите «Это Поль. Он продавец». Начните с представления по имени, затем назовите его занятие.',[('C’est Paul.','сэ по́ль'),('Il est','иль э́'),('vendeur.','ва(н)дё́р')],'C’est Paul. Il est vendeur.','сэ по́ль. иль э ва(н)дё́р.','Получилось короткое представление с прямо названным занятием.','Поставьте представление по имени первым, затем начало о нём и название продавца.')
choose(s,'reading',[U],['cest-identify','mon-possessive','voisin','ami','il','professeur','medecin'],['voisin','professeur'],'Какие два сведения прямо даны в репликах?',fr('C’est mon voisin. Il est professeur.','сэ мо(н) вуазэ́(н). иль э профэсё́р.'),['Мой друг; учитель','Мой сосед; врач','Мой сосед; учитель'],2,'Это сосед говорящего, и он назван учителем.','Сохраните отношение и занятие: сосед и учитель. Дружба и врач этой записью не названы.')
page(s,[('Отношение и занятие',['Представление может сообщить отношение к говорящему и отдельным образцом назвать занятие. Не подменяйте одно другим.'],[ex('C’est mon ami. Il est médecin.','Это мой друг. Он врач.','сэ мо(н) нами́. иль э мэдсэ́(н)'),ex('C’est mon voisin. Il est professeur.','Это мой сосед. Он учитель.','сэ мо(н) вуазэ́(н). иль э профэсё́р')]),('Представление по имени',['Все имена и формы уже показаны. Читайте весь короткий текст, чтобы связать имя и роль в этом задании.'],[ex('C’est Paul. Il est vendeur.','Это Поль. Он продавец.','сэ по́ль. иль э ва(н)дё́р')]),('Вопрос о человеке',['Знакомый вопрос о человеке отличается от вопросов о расположении и погоде. Для ответа достаточно прямо данного текста.'],[W['qui-est-ce']['example']])])

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
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-zÀ-ÿŒœ])'+re.escape(f)+r'(?![A-Za-zÀ-ÿŒœ])',text) and not any(n['fr']==f for n in ns):ns.append(dict(fr=f,transcription_ru=p))
  if ns:v['pronunciation_notes']=ns
 for x in list(v.values()):annotate(x)
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Друг, сосед и четыре учебные или профессиональные роли; короткое представление другого человека.',learning_outcomes=['Различать друга и соседа, учителя и ученика.','Выбирать и собирать показанные реплики о враче и продавце.','Узнавать знакомый вопрос кто это и понимать короткий ответ.','Связывать имя, отношение и прямо названную роль в самостоятельном учебном тексте.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u18-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'PRE_A1-U18.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('U18 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('ami','Вернуть знакомое отношение друг без вывода о профессии.'),('voisin','Повторить соседа в короткой бытовой ситуации, сохраняя выбранную форму.'),('professeur','Использовать только уже показанный образец о профессии до расширения грамматики.'),('eleve','Повторить изученные формы ученика и ученицы; указание на человека берётся из слов.'),('medecin','Сохранить одинаковую показанную форму названия врача после начала о нём или о ней.'),('vendeur','Сохранять показанную мужскую форму до отдельного объяснения других форм.'),('qui-est-ce','Вернуть прежний вопрос о человеке без нового ключа.'),('cest-identify','Сохранить ключ представления, уже использованный в семейной теме.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['До импорта целевая база должна содержать PRE_A1-U17-L05; пакет не создаёт отсутствующую внешнюю зависимость.','У существующей карточки c’est русская помета ограничена указанием на предмет, хотя ранее подготовленная семейная тема уже использует тот же ключ для людей. Ключ и полный объект сохранены. Редактору следует отдельно уточнить помету с соблюдением правил версий; текущий пакет её не переписывает.','Человеку нужно проверить выбранные формы обозначения людей, показанные грамматические образцы и условные русские транскрипции; прослушать браузерную озвучку, особенно связи в сочетаниях с другом и учеником.','Выполнить предпросмотр, пробное прохождение и замер длительности. Ориентир 6 минут на занятие не измерен.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U19',previous_unit='PRE_A1-U17',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку текущей темы. Отдельное подтверждение принятия или импорта предыдущих пакетов не получено.','Вопрос кто это и форма представления уже встречались в семейной теме. Существующие ключи и полные объекты сохранены, новые дубликаты этих выражений не создаются.','Друг и сосед обозначают отношения к человеку, а не его профессию. Профессия не выводится автоматически из дружбы, соседства или имени.','Показаны выбранные формы названий людей, включая образец о женщине-враче и ученице. Пол, роль и возраст не угадываются по синтетическому голосу. Все сведения определяются словами и условиями.','Артикли после формы представления и отсутствие артикля в нескольких коротких сообщениях о занятии вводятся на конкретных образцах. Универсальное правило для всех употреблений не формулируется.','Два учебных имени вводятся в четвёртом занятии с транскрипцией. Постоянная биография персонажей не предполагается; каждое задание содержит самостоятельные сведения.','В первых четырёх занятиях соответственно 2, 2, 2 и 0 новых лексических значений. Четвёртое занятие соединяет знакомые образцы в представление по имени. В пятом занятии нового обязательного материала нет.','Имена не оцениваются как новые словарные значения. Оцениваются смысл вопроса, прямо названные отношения и роли и показанные способы построения реплики.','Апострофы сохраняются целыми в сборке. Подписанная сборка и восстановление грамматического образца не объявляются независимой проверкой каждого слова: assessed_vocabulary_ids пуст.','Основной реестр и предыдущие пакеты не изменены. Новые ключи предложены отдельно до принятия.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Существующий семейный образец представления и слово мой с двумя новыми отношениями друг и сосед.'),dict(session_id=S[1]['id'],material='Конкретные образцы представления учителя и ученика с артиклем; короткие сообщения о роли после указания на человека без артикля; показанное представление ученицы.'),dict(session_id=S[2]['id'],material='Короткие сообщения о враче и продавце; показанный образец о женщине-враче с той же формой названия профессии.'),dict(session_id=S[3]['id'],material='Знакомый вопрос о человеке; представление по имени, соединённое с уже показанным сообщением о занятии.')]
D['introduced_named_labels']=[dict(first_session_id=S[3]['id'],before_task_id='Q02',fr='Paul',ru='Поль',transcription_ru='по́ль',status='fictional_person_label_not_a_new_lexeme'),dict(first_session_id=S[3]['id'],before_task_id='Q02',fr='Lila',ru='Лила',transcription_ru='лила́',status='person_label_not_a_new_lexeme')]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
(OUT/'PRE_A1-U18.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))
