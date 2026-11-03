"""Author U03 only; reuse unchanged word meanings from the prepared U01/U02 files."""
import json, copy, re, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][2]
W={}
for source in [OUT/'PRE_A1-U02.v1.json',KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']: W.setdefault(w['id'],copy.deepcopy(w))
OLD=set(W); FIRST={}; S=[]
U='PRE_A1.U03.UNDERSTAND'; A='PRE_A1.U03.APPLY'
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

def page(s,blocks):
 s['public_page']=dict(slug=s['id'].lower().replace('_','-'),summary=s['description'],seo_title=s['title']+' — французский с нуля',seo_description=s['description'],explanation_blocks=[dict(heading=h,paragraphs=ps,examples=es) for h,ps,es in blocks])

NUMBERS=[
 ('zero','zéro','ноль','зэро́'),('un','un','один','э́(н)'),('deux','deux','два','дё́'),
 ('trois','trois','три','труа́'),('quatre','quatre','четыре','ка́тр'),('cinq','cinq','пять','сэ́(н)к'),
 ('six','six','шесть','си́с'),('sept','sept','семь','сэ́т'),('huit','huit','восемь','уи́т'),
 ('neuf','neuf','девять','нё́ф'),('dix','dix','десять','ди́с'),('onze','onze','одиннадцать','о́(н)з'),
 ('douze','douze','двенадцать','ду́з'),('treize','treize','тринадцать','трэ́з'),('quatorze','quatorze','четырнадцать','като́рз'),
 ('quinze','quinze','пятнадцать','кэ́(н)з'),('seize','seize','шестнадцать','сэ́з'),
 ('dix-sept','dix-sept','семнадцать','дисэ́т'),('dix-huit','dix-huit','восемнадцать','дизюи́т'),
 ('dix-neuf','dix-neuf','девятнадцать','дизнё́ф'),('vingt','vingt','двадцать','вэ́(н)')]
N=[x[0] for x in NUMBERS]
GROUPS=[[0,1,2],[3,4,5],[6,7,8],[8,9,10],[11,12,13],[14,15,16],[17,18,19],[17,19,20]]
def number_group(n): return next(g for g in GROUPS if n in g and (n!=8 or g==[6,7,8]))
for n,(i,f,r,p) in enumerate(NUMBERS):
 group=number_group(n); wrong=[x for x in group if x!=n][:2]
 target=n%3
 meaning=[str(x) for x in wrong]; meaning.insert(target,str(n))
 application=[fr(NUMBERS[x][1],NUMBERS[x][3]) for x in wrong]; application.insert((target+1)%3,fr(f,p))
 situation=(f'На табло показан номер {n}. Как назвать именно это число?' if n%2 else f'В игре у вас {n} очков. Как назвать число очков?')
 example=ex(f'{f.capitalize()}, s’il vous plaît.',f'{r.capitalize()}, пожалуйста.',f'{p}, силь ву плэ́') if n else ex('Zéro.','Ноль.','зэро́')
 W[i]=dict(id=i,lexeme_key=f'fr:{i}:cardinal-number',fr=f,ru=[r],transcription_ru=p,example=example,review_items=[
  rev(i+'-meaning',fr(f,p),meaning,target,f'Это число {n}: «{r}».',f'Правильное значение — {n}, «{r}».'),
  rev(i+'-use',ru(situation),application,(target+1)%3,f'Вы выбрали число {n}, которое требуется в этой ситуации.',f'Нужно число {n}; другие варианты называют соседние изученные числа.')])
 FIRST[i]=1 if n<=5 else 2 if n<=10 else 3 if n<=16 else 4

def addword(i,key,f,r,p,e,first,reviews):
 W[i]=dict(id=i,lexeme_key=key,fr=f,ru=[r],transcription_ru=p,example=e,review_items=reviews); FIRST[i]=first
addword('numero','fr:numero:identifier','le numéro','номер (обозначение талона или места)','лё нюмэро́',ex('Le numéro huit.','Номер восемь.','лё нюмэро уи́т'),2,[
 rev('numero-meaning',fr('Le numéro','лё нюмэро́'),['Фамилия','Номер','Возраст'],1,'Это обозначение номера, например номера талона.','Здесь речь о номере, а не о фамилии или возрасте.'),
 rev('numero-use',ru('Вы хотите сообщить номер талона, а не фамилию. Какое начало подходит?'),[wc('nom-family-name'),wc('bonjour'),fr('Le numéro','лё нюмэро́')],2,'Номер талона называют с помощью слова «номер».','Нужно слово «номер»; приветствие и поле фамилии решают другие задачи.')])
addword('jai-age','fr:jai:age-frame','j’ai','мне (начало образца о возрасте)','жэ',ex('J’ai dix-huit ans.','Мне восемнадцать лет.','жэ дизюита́(н)'),4,[
 rev('jai-age-meaning',fr('J’ai dix-huit ans.','жэ дизюита́(н)'),['Мне восемнадцать лет.','Мой номер — восемнадцать.','Назовите восемнадцать по буквам.'],0,'Этим образцом сообщают свой возраст.','В полном образце говорится о возрасте: «Мне восемнадцать лет».'),
 rev('jai-age-use',ru('Вам двадцать лет. Выберите начало сообщения о своём возрасте.'),[fr('Le numéro','лё нюмэро́'),fr('J’ai','жэ'),wc('bonjour')],1,'Сообщение о возрасте начинается с изученного образца «мне».','Нужно начало образца о возрасте; слово «номер» и приветствие не подходят.')])
addword('ans-age','fr:ans:age-years','ans','лет (в образце о возрасте)','а́(н)',ex('J’ai vingt ans.','Мне двадцать лет.','жэ вэ(н)та́(н)'),4,[
 rev('ans-age-meaning',fr('ans','а́(н)'),['Номер','Лет (о возрасте)','Спасибо'],1,'В образце о возрасте это слово обозначает годы.','Здесь значение — «лет», а не номер или благодарность.'),
 rev('ans-age-use',ru('Вы сообщаете возраст восемнадцать лет. Какое слово завершает изученный образец?'),[wc('merci'),fr('ans','а́(н)'),wc('numero')],1,'Возраст завершает слово со значением «лет».','Выберите слово со значением «лет»; благодарность и номер не завершают образец о возрасте.')])

def ids(*nums): return [N[n] for n in nums]
def number_choices(*nums): return [wc(N[n]) for n in nums]
def digit_choices(*nums): return [str(n) for n in nums]
def number_intro(s,before,nums,text):
 pairs_data=[(NUMBERS[n][1],NUMBERS[n][3]) for n in nums]
 line='; '.join(f'{NUMBERS[n][1]} — {NUMBERS[n][2]}' for n in nums)+'.'
 intro(s,before,text+' '+line,ids(*nums),pairs_data,('. '.join(NUMBERS[n][1].capitalize() for n in nums)+'.','. '.join(NUMBERS[n][3] for n in nums)+'.'))
def number_pairs(s,goals,nums):
 pairs(s,goals,[(N[n],str(n)) for n in nums],'Каждое слово соединено с соответствующим числом.','Проверьте каждое значение: '+', '.join(f'{NUMBERS[n][1]} — {n}' for n in nums)+'.')
def numeral_page(s,nums,heading,paragraphs,additional):
 blocks=[(heading,paragraphs,[ex(NUMBERS[n][1],str(n),NUMBERS[n][3]) for n in nums])]+additional
 page(s,blocks)

# Lesson 1: two short groups of three numbers, plus a return to U02.
s=start(1,'Выбирайте числа от нуля до пяти по звучанию и записи. Используйте знакомые согласие, отказ и вежливую просьбу.',ids(0,1,2,3,4,5)+['oui','non','merci','sil-vous-plait','epelez'])
choose(s,'vocabulary',['PRE_A1.U02.APPLY'],['oui','non','merci','epelez'],['oui'],'Вам предлагают ещё раз назвать имя. Вы согласны. Как ответить?',ru('Вспомните короткие ответы из предыдущей темы.'),[fr('Non, merci.','но(н), мэрси́'),fr('Oui, merci.','уи, мэрси́'),wc('epelez')],1,'«Да, спасибо» выражает согласие.','Выберите «Да, спасибо»: отказ и просьба назвать по буквам имеют другой смысл.')
number_intro(s,2,[0,1,2],'Начнём с трёх чисел. У нуля значение «ни одного», а у единицы и двойки — соответствующее количество. Слушайте слово целиком:')
number_pairs(s,[U],[0,1,2])
listen(s,[U],ids(0,1,2),ids(2),'Какое число вы услышали?','Deux.','дё́.',digit_choices(0,2,1),1,'Прозвучало число два.','Правильный ответ — 2: прозвучало «два».')
number_intro(s,4,[3,4,5],'Теперь ещё три числа. Не нужно называть весь ряд подряд — узнавайте каждое отдельно:')
number_pairs(s,[U],[3,4,5])
listen(s,[U],ids(3,4,5),ids(4),'В игре назвали количество жетонов. Сколько их?','Quatre.','ка́тр.',digit_choices(5,4,3),1,'Названо четыре жетона.','Нужно выбрать 4; пять и три звучат иначе.')
choose(s,'reading',[U],ids(3,4,5),ids(5),'На карточке указано количество. Сколько предметов нужно взять?',wc('cinq'),digit_choices(3,5,4),1,'На карточке написано «пять».','Нужно пять предметов: правильное число 5.')
choose(s,'vocabulary',[U],ids(0,1,2),ids(0),'Коробка пуста. Какое число обозначает количество предметов?',ru('Выберите французское слово для нуля.'),number_choices(1,0,2),1,'Пустой коробке соответствует ноль предметов.','Выберите ноль: один и два означали бы, что предметы есть.')
intro(s,8,'К числу можно добавить знакомое «пожалуйста», если вы просите нужное количество. Это короткая реплика без нового грамматического правила.',['sil-vous-plait'])
fill(s,[U],ids(3,4,5)+['sil-vous-plait'],ids(3),'Вам нужны три места. Дополните короткую просьбу «Три, пожалуйста».','','',', s’il vous plaît.','силь ву плэ́',number_choices(4,5,3),2,'trois, s’il vous plaît.','труа, силь ву плэ́.','Вы попросили именно три места.','Нужно число три; четыре и пять изменят количество.')
numeral_page(s,range(6),'От пустой коробки к пяти предметам',['Ноль означает отсутствие предметов. Остальные числа этого занятия помогают назвать маленькое количество. Возвращайтесь к каждому слову отдельно, а не только считайте по порядку.'],[('Короткая вежливая просьба',['Назовите нужное количество и добавьте уже знакомую формулу вежливости. Нового спряжения для этого не требуется.'],[ex('Trois, s’il vous plaît.','Три, пожалуйста.','труа, силь ву плэ́')]),('Как слушать носовой гласный',['У некоторых чисел слышится носовой гласный. Запись (н) напоминает, что отдельный звук «н» после него не добавляют. Русская запись приблизительна и не заменяет озвучку.'],[W['un']['example'],W['cinq']['example']])])

# Lesson 2: five new numbers and the noun needed for real number contexts.
s=start(2,'Узнавайте числа от шести до десяти и выбирайте номер талона. Повторите маленькие количества и просьбу повторить.',ids(1,3,5,6,7,8,9,10)+['numero','nom-family-name','bonjour','repetez','epelez','plus-lentement'])
number_pairs(s,[U],[1,3,5])
number_intro(s,2,[6,7,8],'После повторения добавим три числа. В этих отдельных названиях слышны конечные согласные:')
number_pairs(s,[U],[6,7,8])
listen(s,[U],ids(6,7,8),ids(7),'Какое число назвали для записи?','Sept.','сэ́т.',digit_choices(6,8,7),2,'Прозвучало число семь.','Нужно 7: число заканчивается звучанием «эт», а не «ис» или «ит».')
intro(s,4,'Ещё два числа и одно полезное слово: neuf — девять, dix — десять, le numéro — номер (мужской род). Например, Le numéro huit. означает «Номер восемь». Здесь номер — обозначение талона, а не количество людей.',['neuf','dix','numero'],[('neuf','нё́ф'),('dix','ди́с'),('le numéro','лё нюмэро́'),('Le numéro huit.','лё нюмэро уи́т')],('Neuf. Dix. Le numéro huit.','нёф. дис. лё нюмэро уи́т.'))
pairs(s,[U],[('neuf','9'),('dix','10'),('numero','Номер талона')],'Девять и десять — числа; отдельное слово обозначает номер.','Различайте значения: neuf — 9, dix — 10, le numéro — номер.')
listen(s,[U],ids(6,7,8)+['numero'],ids(8),'Какой номер талона назвали?','Le numéro huit.','лё нюмэро уи́т.',digit_choices(8,6,7),0,'Назван номер восемь.','Выберите номер 8; в реплике звучит число восемь.')
choose(s,'reading',[U],ids(6,9,10)+['numero'],ids(9),'На талоне номер записан словом. Какое число ему соответствует?',fr('Numéro : neuf.','нюмэро : нё́ф.'),digit_choices(6,10,9),2,'На талоне указан номер девять.','Здесь число 9; не путайте его с шестью или десятью.')
fill(s,[U],ids(8,9,10)+['numero'],ids(10),'Дополните запись номера десять.','Numéro ','нюмэро','','',number_choices(9,10,8),1,'Numéro dix','нюмэро ди́с','Получился номер десять.','Выберите слово «десять»; девять и восемь дадут другие номера.')
choose(s,'vocabulary',['PRE_A1.CLARIFY.REQUEST'],['repetez','epelez','plus-lentement'],['repetez'],'Вы не расслышали номер. Нужно попросить произнести его ещё раз целиком. Что сказать?',ru('Повторите знакомую просьбу из первой темы.'),[wc('epelez'),wc('repetez'),wc('plus-lentement')],1,'Для повтора целиком подходит «Повторите, пожалуйста».','Выберите просьбу повторить целиком: перечисление букв и замедление речи решают другие задачи.')
numeral_page(s,range(6,11),'Пять следующих чисел',['Узнавайте числа не только по порядку, но и в случайной последовательности. Здесь приведены самостоятельные названия; звучание внутри целой фразы иногда меняется.'],[('Что именно обозначает номер',['Одно число может обозначать количество или номер талона. Ситуация подсказывает, какой смысл нужен. Существительное запоминаем вместе с артиклем.'],[W['numero']['example']]),('Если число не расслышано',['Можно попросить повторить весь номер. Просьба назвать по буквам подходит для написания имени и не заменяет повтор числительного.'],[W['repetez']['example']])])

# Lesson 3: six teens, with explicit contrasts to familiar short numbers.
s=start(3,'Различайте числа от одиннадцати до шестнадцати и похожие маленькие числа. Выбирайте нужный номер по записи и на слух.',ids(3,4,5,6,9,10,11,12,13,14,15,16)+['numero','bonjour','sil-vous-plait'])
listen(s,[U],ids(6,9,10)+['bonjour','numero'],ids(10),'Собеседник поздоровался и назвал номер. Какой?','Bonjour ! Le numéro dix.','бо(н)жу́р! лё нюмэро ди́с.',digit_choices(9,10,6),1,'После знакомого приветствия назван номер десять.','Нужен 10: приветствие не меняет значение следующего числа.')
number_intro(s,2,[11,12,13],'Начнём с трёх новых чисел. Запоминайте их целиком, не пытайтесь складывать названия букв:')
number_pairs(s,[A],[11,12,13])
listen(s,[A],ids(11,12,13)+['numero'],ids(12),'Какой номер талона прозвучал?','Le numéro douze.','лё нюмэро ду́з.',digit_choices(11,13,12),2,'Назван номер двенадцать.','Выберите 12; одиннадцать и тринадцать звучат иначе.')
number_intro(s,4,[14,15,16],'Теперь вторая небольшая группа. Особенно сравните короткое «пять» и новое «пятнадцать»:')
number_pairs(s,[A],[14,15,16])
choose(s,'reading',[A],ids(14,15,16)+['numero'],ids(16),'На карточке указан номер. Какой это номер?',fr('Numéro : seize.','нюмэро : сэ́з.'),digit_choices(14,16,15),1,'Указан номер шестнадцать.','Правильный номер — 16; здесь нет числа четырнадцать или пятнадцать.')
listen(s,[A],ids(3,12,13)+['numero'],ids(13),'Выберите услышанный номер: короткое число и число больше десяти звучат по-разному.','Le numéro treize.','лё нюмэро трэ́з.',digit_choices(3,13,12),1,'Это тринадцать, а не три.','Выберите 13: три звучит иначе и не заканчивается на «эз».')
choose(s,'vocabulary',[A],ids(4,14,15),ids(14),'Вам выдали талон 14. Как назвать его номер по-французски?',ru('Выберите именно четырнадцать, а не четыре.'),number_choices(4,15,14),2,'Для талона 14 нужно число четырнадцать.','Правильно — четырнадцать; четыре обозначает другой номер, пятнадцать тоже не подходит.')
fill(s,[A],ids(5,15,16)+['numero'],ids(15),'Дополните запись номера пятнадцать.','Numéro ','нюмэро','','',number_choices(15,5,16),0,'Numéro quinze','нюмэро кэ́(н)з','Номер пятнадцать отличается от номера пять.','Выберите пятнадцать; короткое слово «пять» не содержит нужного значения.')
numeral_page(s,range(11,17),'Числа учим двумя тройками',['Сначала вернитесь к одиннадцати, двенадцати и тринадцати, затем к четырнадцати, пятнадцати и шестнадцати. Не требуется разбирать историю их образования.'],[('Похожее начало не означает то же число',['Три и тринадцать, пять и пятнадцать называют разные числа. Слушайте слово до конца и выбирайте число по контексту.'],[ex('Trois. Treize.','Три. Тринадцать.','труа. трэз.'),ex('Cinq. Quinze.','Пять. Пятнадцать.','сэ(н)к. кэ(н)з.')]),('Номер в короткой записи',['Слово после подписи номера обозначает нужный талон. Можно назвать его целиком без длинного предложения.'],[ex('Numéro quinze.','Номер пятнадцать.','нюмэро кэ́(н)з')])])

# Lesson 4: four final numbers plus two transparent chunks for the age frame.
s=start(4,'Узнайте числа от семнадцати до двадцати и сообщайте возраст по короткому образцу. Повторите знакомые номера и благодарность.',ids(11,12,14,16,17,18,19,20)+['numero','jai-age','ans-age','merci'])
number_pairs(s,[A],[11,14,16])
number_intro(s,2,[17,18,19],'В этих трёх числах части соединены дефисом. Учите каждое название как одно число; на слух начало может звучать немного иначе:')
number_pairs(s,[A],[17,18,19])
listen(s,[A],ids(17,18,19)+['numero'],ids(18),'Какой номер назвали?','Le numéro dix-huit.','лё нюмэро дизюи́т.',digit_choices(19,18,17),1,'Прозвучало восемнадцать.','Нужен 18; семнадцать и девятнадцать отличаются второй частью.')
intro(s,4,'Последнее число — vingt, «двадцать». Возраст сообщают по образцу J’ai dix-huit ans. — «Мне восемнадцать лет». Начало j’ai в этом образце соответствует «мне», а ans — «лет». Между ними ставим число. Учим только этот образец, без таблицы форм глагола. В целой фразе слова могут звучать слитно: J’ai vingt ans. — «Мне двадцать лет».',['vingt','jai-age','ans-age'],[('vingt','вэ́(н)'),('J’ai dix-huit ans.','жэ дизюита́(н)'),('j’ai','жэ'),('ans','а́(н)'),('J’ai vingt ans.','жэ вэ(н)та́(н)')],('J’ai dix-huit ans. J’ai vingt ans.','жэ дизюита́(н). жэ вэ(н)та́(н).'))
listen(s,[A],ids(17,18,20)+['jai-age','ans-age'],ids(20),'Сколько лет человеку?','J’ai vingt ans.','жэ вэ(н)та́(н).',digit_choices(17,20,18),1,'Человек сообщает, что ему двадцать лет.','Правильный возраст — 20: в образце прозвучало число двадцать.')
choose(s,'vocabulary',[A],ids(18)+['jai-age','ans-age','numero'],['jai-age','ans-age'],'Что сообщает человек этим образцом?',fr('J’ai dix-huit ans.','жэ дизюита́(н).'),['Ему восемнадцать лет.','Его номер — восемнадцать.','Он просит повторить число.'],0,'Начало и завершение образца указывают на возраст.','Это сообщение о возрасте: «Мне восемнадцать лет», а не номер или просьба.')
fill(s,[A],ids(18,19,20)+['jai-age','ans-age'],ids(19),'Вам девятнадцать лет. Выберите число для сообщения о возрасте.','J’ai ','жэ',' ans.','а́(н)',number_choices(18,20,19),2,'J’ai dix-neuf ans.','жэ дизнёва́(н).','Число девятнадцать сообщает нужный возраст.','Нужно девятнадцать; восемнадцать и двадцать изменят ваш возраст.')
order(s,[A],ids(18)+['jai-age','ans-age'],[],'Соберите сообщение «Мне восемнадцать лет», начав с изученного начала образца.',[('J’ai','жэ'),('dix-huit','дизюи́т'),('ans.','а́(н)')],'J’ai dix-huit ans.','жэ дизюита́(н).','Сначала начало образца о себе, затем число, в конце слово «лет».','Поставьте число между началом образца о возрасте и словом «лет».')
listen(s,[A],ids(12,18,20)+['numero','merci'],ids(20),'Собеседник поблагодарил и назвал номер. Какой?','Merci ! Le numéro vingt.','мэрси́! лё нюмэро вэ́(н).',digit_choices(20,12,18),0,'После благодарности назван номер двадцать.','Выберите 20; слово благодарности не меняет названный номер.')
numeral_page(s,range(17,21),'Последние четыре числа темы',['В семнадцати, восемнадцати и девятнадцати две части написаны через дефис. Слушайте полное число; не разбивайте его на отдельные алфавитные названия. Двадцать запоминается отдельным словом.'],[('Как сообщить возраст',['Используйте короткий готовый образец: начало о себе, затем число, затем слово «лет». Другие формы глагола здесь не нужны. Внутри целого образца звуки могут связываться; повторяйте целую фразу по озвучке.'],[W['jai-age']['example'],W['ans-age']['example'],ex('J’ai dix-neuf ans.','Мне девятнадцать лет.','жэ дизнёва́(н)')]),('Возраст и номер решают разные задачи',['Одинаковое число может быть возрастом или номером талона. Чтобы понять смысл, обращайте внимание на слова вокруг него.'],[ex('Le numéro dix-huit.','Номер восемнадцать.','лё нюмэро дизюи́т'),ex('J’ai dix-huit ans.','Мне восемнадцать лет.','жэ дизюита́(н)')])])

# Lesson 5: only introduced words and constructions, used in fresh situations.
s=start(5,'Примените изученные числа: выберите нужный номер и сообщите возраст. Различайте короткие числа и похожие числа больше десяти; нового материала нет.',N+['numero','jai-age','ans-age','sil-vous-plait'])
intro(s,1,'Сегодня соединяем изученное без новых слов. Сначала определите, спрашивают возраст, номер или количество, затем выбирайте число. В сборке используйте все карточки ровно один раз.',[])
listen(s,[U],ids(6,10,16)+['numero'],ids(6),'На выдаче талонов назвали номер. Какой?','Le numéro six.','лё нюмэро си́с.',digit_choices(16,6,10),1,'Назван номер шесть, а не шестнадцать.','Правильный номер — 6: шестнадцать звучит иначе.')
choose(s,'reading',[A],ids(4,14,15)+['numero'],ids(14),'В списке указан номер места. Выберите его цифровую запись.',fr('Numéro : quatorze.','нюмэро : като́рз.'),digit_choices(4,14,15),1,'Это номер четырнадцать.','Выберите 14; четыре — только похожее маленькое число.')
number_pairs(s,[U,A],[0,2,8,11])
fill(s,[A],ids(12,19,20)+['jai-age','ans-age'],ids(20),'Вам двадцать лет. Дополните изученный образец.','J’ai ','жэ',' ans.','а́(н)',number_choices(19,12,20),2,'J’ai vingt ans.','жэ вэ(н)та́(н).','Вы сообщили возраст двадцать лет.','Нужно число двадцать; двенадцать и девятнадцать означают другой возраст.')
listen(s,[A],ids(9,19)+['jai-age','ans-age','numero'],ids(19)+['jai-age','ans-age'],'Что сообщил участник встречи?','J’ai dix-neuf ans.','жэ дизнёва́(н).',['Его номер — девятнадцать.','Ему девять лет.','Ему девятнадцать лет.'],2,'Образец говорит о возрасте, и в нём названо девятнадцать.','Нужен возраст 19 лет: это не номер и не короткое число девять.')
choose(s,'vocabulary',[A],ids(5,15,16)+['numero'],ids(15),'Вам нужен талон с номером 15. Какую короткую запись выбрать?',ru('Сравните пятнадцать с похожим маленьким числом.'),[fr('Numéro cinq.','нюмэро сэ́(н)к.'),fr('Numéro quinze.','нюмэро кэ́(н)з.'),fr('Numéro seize.','нюмэро сэ́з.')],1,'Запись содержит число пятнадцать.','Нужна запись с пятнадцатью; пять и шестнадцать обозначают другие талоны.')
order(s,[A],ids(17)+['numero','sil-vous-plait'],[],'Попросите талон «Номер семнадцать, пожалуйста», начав со слова «номер».',[('Le numéro','лё нюмэро'),('dix-sept,','дисэ́т'),('s’il vous plaît.','силь ву плэ́')],'Le numéro dix-sept, s’il vous plaît.','лё нюмэро дисэ́т, силь ву плэ́.','Сначала назван номер, затем добавлена целая формула вежливости.','Поставьте слово «номер» перед числом и закончите формулой «пожалуйста».')
listen(s,[U],ids(3,8,9),ids(3,9),'Названы два номера подряд. Выберите порядок, который прозвучал.','Trois. Neuf.','труа. нёф.',['9, затем 3','3, затем 8','3, затем 9'],2,'Сначала прозвучало три, затем девять.','Правильный порядок — 3, затем 9. Не переставляйте услышанные числа.')
page(s,[('Сначала определяем задачу',['Число само по себе не сообщает, о возрасте или о талоне идёт речь. Для этого нужны контекст и знакомые слова вокруг числа.'],[ex('Le numéro vingt.','Номер двадцать.','лё нюмэро вэ́(н)'),ex('J’ai vingt ans.','Мне двадцать лет.','жэ вэ(н)та́(н)')]),('Слушаем слово до конца',['В этой теме есть похожие короткие и длинные числа. Выбор делайте после прослушивания целого слова, а не по одному знакомому звуку.'],[ex('Six. Seize.','Шесть. Шестнадцать.','сис. сэз.'),ex('Cinq. Quinze.','Пять. Пятнадцать.','сэ(н)к. кэ(н)з.')]),('Вежливая просьба из знакомых частей',['Можно попросить конкретный номер и завершить просьбу уже знакомой формулой. Собственного ввода текста и записи голоса в этих заданиях нет.'],[ex('Le numéro dix-sept, s’il vous plaît.','Номер семнадцать, пожалуйста.','лё нюмэро дисэ́т, силь ву плэ́')])])

# Every French insertion in Russian explanation/feedback receives a note.
NOTES={w['fr']:w['transcription_ru'] for w in W.values()}
NOTES.update({'Le numéro':'лё нюмэро́','J’ai':'жэ'})
def annotate(v):
 if isinstance(v,list):
  for x in v: annotate(x)
 if not isinstance(v,dict): return
 supported=('correct' in v or 'before_task_id' in v or 'instruction' in v or 'description' in v or 'paragraphs' in v)
 if supported:
  text=(v.get('prompt',{}).get('text','') if v.get('prompt',{}).get('text_lang')=='ru' else '')+' '+' '.join(v.get(k,'') for k in ['instruction','hint','correct','incorrect','text','description','heading'])+' '+' '.join(v.get('paragraphs',[]))
  ns=v.get('pronunciation_notes',[])
  for f,p in NOTES.items():
   if re.search(r'(?<![A-Za-zÀ-ÿ])'+re.escape(f)+r'(?![A-Za-zÀ-ÿ])',text) and not any(n['fr']==f for n in ns): ns.append(dict(fr=f,transcription_ru=p))
  if ns: v['pronunciation_notes']=ns
 for x in list(v.values()): annotate(x)
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Числа от нуля до двадцати в маленьких количествах, номерах и готовом образце сообщения о возрасте.',learning_outcomes=['Соотносить числа от нуля до двадцати с их записью и звучанием.','Различать похожие маленькие числа и числа больше десяти.','Выбирать номер по короткой французской записи или реплике.','Сообщать возраст по изученному образцу из карточек.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u03-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True); target=OUT/'PRE_A1-U03.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv: raise SystemExit('Existing U03: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('un','Вернуться к количеству «один» при знакомстве с единичным предметом; артикль является другим значением и потребует отдельного объяснения.'),('deux','Сопоставить один и два предмета при введении множественного числа.'),('trois','Использовать три знакомых предмета как посильный перенос числительного в новую тему.'),('cinq','Повторить маленькое количество и отличие от пятнадцати.'),('repetez','Сохранить возможность попросить повторить название предмета.'),('numero','Вернуть номер как обозначение предмета или карточки, не смешивая с количеством.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['Перед импортом U03 в целевой базе должна существовать PRE_A1-U02-L05; наличие подготовленного файла не подтверждает импорт.','Человеку нужно прослушать числа и целые образцы возраста французским голосом браузера, проверить транскрипции и пробное прохождение.','Замерить длительность и нагрузку: оценка 6 минут не измерена; заданный план требует в нескольких занятиях по шесть новых значений.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U04',previous_unit='PRE_A1-U02',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку U03; отдельного подтверждения импорта или языкового принятия U02 нет.','Старые смысловые ключи сохранены. Основной реестр не изменён; новые значения переданы отдельным delta до принятия.','План требует шесть чисел в L01 и L03. L02 содержит пять чисел и слово «номер»; L04 — четыре числа и две части готового образца возраста. Введение разбито на карточки по 1–3 значения, задания ограничены восемью.','Начало и конец образца возраста имеют отдельные ключи: неизменные части допускают другие уже изученные числа. Это не обучение полной парадигме глагола.','В L05 нет новых обязательных слов, чисел или конструкций. Оно выборочно объединяет тему, а не подтверждает освоение каждого числа за одно прохождение.','Подписанная сборка фраз не размечена как оценка всех её слов: assessed_vocabulary_ids в двух заданиях сборки оставлены пустыми.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['number_evidence']=[dict(lexeme_key=W[i]['lexeme_key'],number=n,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if i in t['assessed_vocabulary_ids']]) for n,i in enumerate(N)]
(OUT/'PRE_A1-U03.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))
