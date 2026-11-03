"""Author PRE_A1-U09 only; retain stable earlier lexical meanings and IDs."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level']
plan=level['units'][8]
W={}
for source in [OUT/f'PRE_A1-U{n:02}.v1.json' for n in range(8,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='PRE_A1.U09.UNDERSTAND';A='PRE_A1.U09.APPLY'
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
addword('eau','fr:eau:water','de l’eau','вода (в просьбе: воды)','дё ло́',ex('De l’eau, s’il vous plaît.','Воды, пожалуйста.','дё ло, силь ву плэ́'),1,[rev('eau-meaning',fr('De l’eau','дё ло́'),['Сок','Вода','Сумка'],1,'Сочетание используется в просьбе о воде.','Правильный смысл — вода, а не сок или вещь.'),rev('eau-use',ru('В учебном заказе просят питьевую воду. Какое сочетание выбрать?'),[fr('Un jus','э(н) жю́'),fr('Un livre','э(н) ли́вр'),fr('De l’eau','дё ло́')],2,'Сочетание соответствует воде в заказе.','Нужна вода; сок — другой напиток, а книга не является напитком.')])
addword('jus','fr:jus:juice','un jus','сок (порция в заказе)','э(н) жю́',ex('Un jus, s’il vous plaît.','Сок, пожалуйста.','э(н) жю, силь ву плэ́'),1,[rev('jus-meaning',fr('Un jus','э(н) жю́'),['Сок','Вода','Книга'],0,'В заказе это порция сока.','Нужно значение «сок»; вода и книга означают другое.'),rev('jus-use',ru('По условию нужно попросить сок, а не воду. Какое сочетание подходит?'),[fr('De l’eau','дё ло́'),fr('Un jus','э(н) жю́'),fr('Un sac','э(н) са́к')],1,'Выбрано сочетание для сока.','Нужен сок; вода — другой напиток, сумка — другой предмет.')])
addword('je-voudrais','fr:je-voudrais:polite-request','je voudrais','Мне бы хотелось (вежливая просьба)','жё вудрэ́',ex('Je voudrais de l’eau.','Мне бы хотелось воды.','жё вудрэ дё ло́'),1,[rev('je-voudrais-meaning',fr('Je voudrais de l’eau.','жё вудрэ дё ло́'),['Спасибо за воду.','Мне бы хотелось воды.','Меня зовут Мила.'],1,'Говорящий вежливо просит воду.','Это просьба о воде, а не благодарность или представление.'),rev('je-voudrais-use',ru('Заказ ещё не сделан. Вы хотите вежливо попросить сок. Какую реплику выбрать?'),[fr('Merci.','мэрси́'),fr('De rien.','дё рье́(н)'),fr('Je voudrais un jus.','жё вудрэ э(н) жю́')],2,'Реплика формулирует вежливую просьбу о соке.','Нужно попросить напиток; благодарность и ответ на неё не делают заказ.')])
addword('the','fr:the:tea','un thé','чай (порция в заказе)','э(н) тэ́',ex('Un thé, s’il vous plaît.','Чай, пожалуйста.','э(н) тэ, силь ву плэ́'),2,[rev('the-meaning',fr('Un thé','э(н) тэ́'),['Кофе','Сок','Чай'],2,'В заказе это чай.','Нужно значение «чай»; кофе и сок — другие напитки.'),rev('the-use',ru('Гость выбрал чай. Какое сочетание соответствует его выбору?'),[fr('Un thé','э(н) тэ́'),fr('Un café','э(н) кафэ́'),fr('Un jus','э(н) жю́')],0,'Выбран именно чай.','Нужен чай; кофе и сок изменят заказ.')])
addword('cafe','fr:cafe:coffee','un café','кофе (порция в заказе)','э(н) кафэ́',ex('Un café, s’il vous plaît.','Кофе, пожалуйста.','э(н) кафэ, силь ву плэ́'),2,[rev('cafe-meaning',fr('Un café','э(н) кафэ́'),['Кофе','Чай','Вода'],0,'В этом контексте назван напиток кофе.','Здесь значение — кофе как напиток; не чай и не вода.'),rev('cafe-use',ru('В заказе указан кофе. Какое сочетание выбрать?'),[fr('Un jus','э(н) жю́'),fr('Un café','э(н) кафэ́'),fr('Un thé','э(н) тэ́')],1,'Сочетание соответствует кофе в заказе.','Нужен кофе; сок и чай — другие напитки.')])
addword('froid','fr:froid:cold-temperature','froid','холодный (о температуре)','фруа́',ex('Un jus froid.','Холодный сок.','э(н) жю фруа́'),3,[rev('froid-meaning',fr('Froid','фруа́'),['Горячий','Холодный','Красный'],1,'Слово сообщает холодную температуру.','Это холодная температура, а не горячая и не цвет.'),rev('froid-use',ru('Нужен сок с прямо указанной холодной температурой. Какую запись выбрать?'),[fr('Un jus chaud','э(н) жю шо́'),fr('Un jus','э(н) жю́'),fr('Un jus froid','э(н) жю фруа́')],2,'В записи прямо названа холодная температура.','Нужен признак холодной температуры; горячий не подходит, а общее название сока её не уточняет.')])
addword('chaud','fr:chaud:hot-temperature','chaud','горячий (о температуре)','шо́',ex('Un thé chaud.','Горячий чай.','э(н) тэ шо́'),3,[rev('chaud-meaning',fr('Chaud','шо́'),['Горячий','Холодный','Большой'],0,'Слово сообщает горячую температуру.','Это горячая температура, а не холодная или большой размер.'),rev('chaud-use',ru('Нужен чай с прямо указанной горячей температурой. Какую запись выбрать?'),[fr('Un thé froid','э(н) тэ фруа́'),fr('Un thé chaud','э(н) тэ шо́'),fr('Un thé','э(н) тэ́')],1,'В записи прямо указана горячая температура.','Нужен горячий чай; холодный не подходит, а общее название напитка температуру не сообщает.')])
addword('drink-question','fr:que-voulez-vous-boire:drink-question','que voulez-vous boire ?','Что вы хотите пить?','кё вулэ ву буа́р?',ex('Que voulez-vous boire ?','Что вы хотите пить?','кё вулэ ву буа́р?'),4,[rev('drink-question-meaning',fr('Que voulez-vous boire ?','кё вулэ ву буа́р?'),['Кто это?','Что вы хотите пить?','Сколько вам лет?'],1,'Спрашивают о выборе напитка.','Нужен смысл «Что вы хотите пить?», а не личность человека или возраст.'),rev('drink-question-use',ru('В учебном диалоге вы принимаете заказ и хотите узнать напиток. Какой вопрос выбрать?'),[wc('qui-est-ce'),fr('Que voulez-vous boire ?','кё вулэ ву буа́р?'),wc('age-question')],1,'Вопрос относится к выбору напитка.','Нужен вопрос о напитке; два других вопроса запрашивают сведения о человеке.')])

s=start(1,'Понимайте названия воды и сока и вежливо просите напиток по готовому образцу. Повторите знакомый признак размера.',['eau','jus','je-voudrais','petit','grand','sac','merci','sil-vous-plait'])
choose(s,'reading',['PRE_A1.U08.UNDERSTAND'],['petit','grand','sac'],['petit'],'Вспомните признаки вещей: какой размер указан у сумки?',fr('Un petit sac.','э(н) пёти са́к.'),['Большой','Маленький','Размер не указан'],1,'Сумка названа маленькой.','Указан маленький размер, а не большой.')
intro(s,2,'Два сочетания для напитков: de l’eau — «воды», un jus — «сок» в значении порции при заказе. Учим их целиком, не разбирая сейчас все формы артикля. К сочетанию можно добавить знакомое «пожалуйста».',['eau','jus'],[('de l’eau','дё ло́'),('un jus','э(н) жю́')],('De l’eau, s’il vous plaît. Un jus, s’il vous plaît.','дё ло, силь ву плэ́. э(н) жю, силь ву плэ́.'))
pairs(s,[U],[('eau','Вода'),('jus','Сок'),('merci','Спасибо')],'Вы узнали два напитка и повторили знакомую благодарность.','Вода и сок — разные напитки; третье слово выражает благодарность.')
listen(s,[U],['jus','eau','sil-vous-plait'],['jus'],'Какой напиток попросили?','Un jus, s’il vous plaît.','э(н) жю, силь ву плэ́.',['Воду','Напиток не назван','Сок'],2,'Попросили сок.','Нужен сок; вода в просьбе не названа.')
intro(s,4,'Готовое вежливое начало просьбы: je voudrais — «мне бы хотелось». Затем добавляем нужный напиток: Je voudrais de l’eau. — «Мне бы хотелось воды». Учим рамку целиком, без таблицы форм глагола.',['je-voudrais'],[('je voudrais','жё вудрэ́'),('Je voudrais de l’eau.','жё вудрэ дё ло́')],('Je voudrais de l’eau. Je voudrais un jus.','жё вудрэ дё ло́. жё вудрэ э(н) жю́.'))
choose(s,'vocabulary',[A],['je-voudrais','eau','jus','merci'],['eau'],'По условию нужно вежливо попросить воду. Какую реплику выбрать?',ru('Сохраните нужный напиток.'),[fr('Je voudrais un jus.','жё вудрэ э(н) жю́.'),fr('Je voudrais de l’eau.','жё вудрэ дё ло́.'),wc('merci')],1,'Реплика просит воду.','Нужна вода; сок изменит напиток, а благодарность не формулирует просьбу.')
listen(s,[A],['je-voudrais','eau'],['je-voudrais'],'Что делает говорящий этой репликой?','Je voudrais de l’eau.','жё вудрэ дё ло́.',['Просит воду','Благодарит за воду','Представляется по имени'],0,'Говорящий вежливо просит напиток.','Это просьба, а не благодарность или представление.')
fill(s,[A],['je-voudrais','jus','eau'],['jus'],'Попросите сок. Выберите целое сочетание после начала просьбы.','Je voudrais ','жё вудрэ́','','',[wc('eau'),wc('jus')],1,'Je voudrais un jus','жё вудрэ э(н) жю́','В просьбе выбран сок.','Нужно сочетание для сока; вода означает другой напиток.')
order(s,[A],['je-voudrais','eau','sil-vous-plait'],[],'Соберите просьбу о воде. Начните с вежливой рамки и закончите знакомым «пожалуйста».',[('Je voudrais','жё вудрэ́'),('de l’eau,','дё ло́'),('s’il vous plaît.','силь ву плэ́')],'Je voudrais de l’eau, s’il vous plaît.','жё вудрэ дё ло, силь ву плэ́.','После начала просьбы стоит целое сочетание для воды, затем формула вежливости.','Начните с готовой рамки, добавьте воду целой карточкой и завершите формулой вежливости.')
page(s,[('Два напитка в заказе',['Воду и порцию сока называем показанными сочетаниями. Формы перед названием учим вместе с ним; полная система артиклей здесь не требуется.'],[W['eau']['example'],W['jus']['example']]),('Вежливая рамка просьбы',['Начало сообщает, что говорящий хочет получить напиток. После него можно подставить одно из двух уже изученных сочетаний.'],[W['je-voudrais']['example'],ex('Je voudrais un jus.','Мне бы хотелось сока.','жё вудрэ э(н) жю́')]),('Просьба и благодарность',['До получения напитка просьба сообщает выбор. Благодарность — знакомое слово с другой функцией; она не заменяет название нужного напитка.'],[ex('Merci.','Спасибо.','мэрси́')])])

s=start(2,'Узнавайте чай и кофе и выбирайте нужный напиток в коротком заказе. Повторите воду, сок и вежливое начало просьбы.',['eau','jus','the','cafe','je-voudrais','sil-vous-plait'])
choose(s,'reading',[U],['je-voudrais','eau','jus'],['eau'],'Какой напиток просит говорящий в знакомой реплике?',fr('Je voudrais de l’eau.','жё вудрэ дё ло́.'),['Сок','Воду','Напиток не назван'],1,'В просьбе названа вода.','Нужно выбрать воду, а не сок.')
intro(s,2,'Ещё два сочетания для заказа: un thé — «чай», un café — «кофе». В этих образцах речь об одной порции. Здесь слово café обозначает напиток; другие значения не нужны для ответа. Сохраняйте сочетание с артиклем целиком.',['the','cafe'],[('un thé','э(н) тэ́'),('un café','э(н) кафэ́'),('café','кафэ́')],('Un thé. Un café.','э(н) тэ́. э(н) кафэ́.'))
pairs(s,[U],[('the','Чай'),('cafe','Кофе'),('jus','Сок')],'Вы различили чай, кофе и знакомый сок.','Каждому напитку соответствует своё сочетание; не заменяйте один напиток другим.')
listen(s,[U],['cafe','the','jus','sil-vous-plait'],['cafe'],'Какой напиток попросили?','Un café, s’il vous plaît.','э(н) кафэ, силь ву плэ́.',['Чай','Сок','Кофе'],2,'Попросили кофе.','Это кофе; чай и сок звучат иначе.')
choose(s,'vocabulary',[A],['je-voudrais','the','cafe','eau'],['the'],'В учебном заказе выбран чай. Какую просьбу составить?',ru('Выберите реплику с нужным напитком.'),[fr('Je voudrais un thé.','жё вудрэ э(н) тэ́.'),fr('Je voudrais de l’eau.','жё вудрэ дё ло́.'),fr('Je voudrais un café.','жё вудрэ э(н) кафэ́.')],0,'В просьбе выбран чай.','Нужен чай; вода и кофе изменят заказ.')
choose(s,'reading',[U],['the','cafe','jus','eau'],['the','cafe'],'Какие два напитка названы в коротком списке?',fr('Un thé. Un café.','э(н) тэ́. э(н) кафэ́.'),['Чай и сок','Кофе и вода','Чай и кофе'],2,'В списке названы чай и кофе.','Нужны оба названных напитка: чай и кофе; сок и вода здесь не перечислены.')
fill(s,[A],['je-voudrais','cafe','jus','the','sil-vous-plait'],['cafe'],'Попросите порцию кофе. Выберите целое сочетание после начала просьбы.','Je voudrais ','жё вудрэ́',', s’il vous plaît.','силь ву плэ́',[wc('jus'),wc('cafe'),wc('the')],1,'Je voudrais un café, s’il vous plaît.','жё вудрэ э(н) кафэ, силь ву плэ́.','Просьба относится к кофе.','Нужен кофе; два других варианта называют сок и чай.')
order(s,[A],['je-voudrais','the','sil-vous-plait'],[],'Соберите просьбу о чае. Начните с готовой вежливой рамки и закончите словом «пожалуйста».',[('Je voudrais','жё вудрэ́'),('un thé,','э(н) тэ́'),('s’il vous plaît.','силь ву плэ́')],'Je voudrais un thé, s’il vous plaît.','жё вудрэ э(н) тэ, силь ву плэ́.','После начала просьбы стоит целое сочетание для чая.','Поставьте вежливую рамку первой, затем чай целой карточкой, в конце — формулу «пожалуйста».')
page(s,[('Чай и кофе в коротком заказе',['В показанных сочетаниях называем одну порцию напитка. Название кофе в этой теме относится к напитку, а не к месту встречи.'],[W['the']['example'],W['cafe']['example']]),('Выбираем по условию',['У начала просьбы одна и та же форма; выбор напитка определяется сочетанием после него. Сначала проверьте, какой напиток нужен, затем выбирайте реплику.'],[ex('Je voudrais un thé.','Мне бы хотелось чая.','жё вудрэ э(н) тэ́'),ex('Je voudrais un café.','Мне бы хотелось кофе.','жё вудрэ э(н) кафэ́')]),('Повторяем четыре названия',['Вода, сок, чай и кофе уже знакомы. Не нужно угадывать напиток по привычкам: в каждом упражнении выбор указан в условии или в самой реплике.'],[W['eau']['example'],W['jus']['example']])])

s=start(3,'Выбирайте напиток по температуре и различайте горячее и холодное. Применяйте показанные формы с водой и повторите знакомую семейную роль.',['froid','chaud','eau','jus','the','cafe','je-voudrais','cest-identify','ma-possessive','soeur','fille','mere'])
choose(s,'reading',['PRE_A1.U06.UNDERSTAND'],['cest-identify','ma-possessive','soeur','fille','mere'],['soeur'],'Вспомните семью: кого представили в реплике?',fr('C’est ma sœur.','сэ ма сё́р.'),['Мать','Сестру','Дочь'],1,'Представлена сестра говорящего.','Нужно выбрать сестру; мать и дочь называются иначе.')
intro(s,2,'Два признака температуры: chaud — горячий, froid — холодный. Они относятся к температуре, а не к размеру или цвету. Используем показанные сочетания un thé chaud — «горячий чай», un jus froid — «холодный сок». Признак следует за названием напитка.',['chaud','froid'],[('chaud','шо́'),('froid','фруа́'),('un thé chaud','э(н) тэ шо́'),('un jus froid','э(н) жю фруа́')],('Un thé chaud. Un jus froid.','э(н) тэ шо́. э(н) жю фруа́.'))
choose(s,'vocabulary',[A],['jus','froid','chaud'],['froid'],'Нужен сок с прямо указанной холодной температурой. Какую запись выбрать?',ru('Если температура не названа, не добавляйте её сами.'),[fr('Un jus chaud','э(н) жю шо́'),fr('Un jus froid','э(н) жю фруа́'),fr('Un jus','э(н) жю́')],1,'В записи прямо указана холодная температура сока.','Нужен холодный сок; горячий не подходит, а общее название температуру не уточняет.')
listen(s,[A],['je-voudrais','the','cafe','chaud','froid'],['the','chaud'],'Какая запись точно передаёт просьбу?','Je voudrais un thé chaud.','жё вудрэ э(н) тэ шо́.',['Горячий кофе','Холодный чай','Горячий чай'],2,'Запрошены чай и горячая температура.','Нужно сохранить оба сведения: чай, горячий. Другой напиток или температура изменяют просьбу.')
intro(s,4,'У французского слова «вода» женский род. Сравните показанные сочетания de l’eau froide — «холодной воды», de l’eau chaude — «горячей воды». В формах froide и chaude слышен конечный «д». Две формы сохраняют уже знакомые значения температуры.',['froid','chaud'],[('de l’eau froide','дё ло фруа́д'),('de l’eau chaude','дё ло шо́д'),('froide','фруа́д'),('chaude','шо́д')],('De l’eau froide. De l’eau chaude.','дё ло фруа́д. дё ло шо́д.'))
choose(s,'reading',[A],['je-voudrais','eau','froid','chaud'],['froid'],'Какая температура воды указана в просьбе?',fr('Je voudrais de l’eau froide.','жё вудрэ дё ло фруа́д.'),['Горячая','Температура не указана','Холодная'],2,'Вода запрошена холодной.','Нужна холодная вода; температура в реплике прямо указана.')
grammar(fill(s,[A],['eau','froid','chaud'],['froid'],'Дополните сочетание для просьбы о холодной воде. Выберите показанную форму температуры.','De l’eau ','дё ло́','','',[fr('froid','фруа́'),fr('chaude','шо́д'),fr('froide','фруа́д')],2,'De l’eau froide','дё ло фруа́д','С водой используется показанная форма женского рода холодной температуры.','Нужна форма женского рода со значением «холодная»; другая форма или горячая температура не подходят.'))
choose(s,'vocabulary',[A],['je-voudrais','cafe','the','chaud','froid'],['cafe','chaud'],'По условию нужен горячий кофе. Какую просьбу выбрать?',ru('Проверьте напиток и температуру.'),[fr('Je voudrais un café froid.','жё вудрэ э(н) кафэ фруа́.'),fr('Je voudrais un thé chaud.','жё вудрэ э(н) тэ шо́.'),fr('Je voudrais un café chaud.','жё вудрэ э(н) кафэ шо́.')],2,'В просьбе совпали кофе и горячая температура.','Нужен горячий кофе; холодный кофе или горячий чай меняют одно из условий.')
order(s,[A],['je-voudrais','the','chaud'],[],'Попросите горячий чай. Начните с вежливой рамки, затем поставьте напиток и его температуру.',[('Je voudrais','жё вудрэ́'),('un thé','э(н) тэ́'),('chaud.','шо́')],'Je voudrais un thé chaud.','жё вудрэ э(н) тэ шо́.','Температура следует за целым сочетанием для напитка.','Поставьте начало просьбы перед напитком, затем добавьте требуемую температуру.')
page(s,[('Температура названа отдельно',['Горячий и холодный — признаки температуры. Из цвета, размера или общего названия напитка нужная температура не выводится.'],[W['chaud']['example'],W['froid']['example']]),('Показанные формы с водой',['С водой используйте две показанные формы женского рода. Учим целое сочетание в просьбе; таблица всех форм прилагательных не требуется.'],[ex('De l’eau froide.','Холодной воды.','дё ло фруа́д'),ex('De l’eau chaude.','Горячей воды.','дё ло шо́д')]),('Проверяем два условия',['Напиток и температура — отдельные части заказа. Совпадение только одной части недостаточно, если нужны обе.'],[ex('Je voudrais un café chaud.','Мне бы хотелось горячего кофе.','жё вудрэ э(н) кафэ шо́'),ex('Je voudrais un thé chaud.','Мне бы хотелось горячего чая.','жё вудрэ э(н) тэ шо́')])])

s=start(4,'Понимайте вопрос о выборе напитка и отвечайте готовой вежливой просьбой. Выбирайте напиток и температуру по условию.',['drink-question','eau','jus','the','cafe','je-voudrais','froid','chaud','qui-est-ce','merci','de-rien'])
choose(s,'reading',['PRE_A1.U06.APPLY'],['qui-est-ce'],['qui-est-ce'],'Повторение семьи: что хотят уточнить этим вопросом?',fr('Qui est-ce ?','ки э́с?'),['Что человек хочет пить','Сколько предметов','Кто этот человек'],2,'Вопрос уточняет, кто этот человек.','Это вопрос о личности представленного человека, а не о напитке или количестве.')
intro(s,2,'Вопрос о выборе напитка: Que voulez-vous boire ? — «Что вы хотите пить?». Отвечаем уже знакомой вежливой рамкой и нужным напитком. Например, Je voudrais un thé. — «Мне бы хотелось чая». Вопрос учим целиком.',['drink-question'],[('Que voulez-vous boire ?','кё вулэ ву буа́р?'),('Je voudrais un thé.','жё вудрэ э(н) тэ́')],('Que voulez-vous boire ? Je voudrais un thé.','кё вулэ ву буа́р? жё вудрэ э(н) тэ́.'))
choose(s,'vocabulary',[A],['drink-question'],['drink-question'],'Какой смысл передаёт вопрос?',fr('Que voulez-vous boire ?','кё вулэ ву буа́р?'),['Как вас зовут?','Что вы хотите пить?','Кто это?'],1,'Спрашивают, какой напиток нужен.','Нужен смысл вопроса о напитке; имя и личность человека здесь не запрашиваются.')
listen(s,[U],['drink-question','je-voudrais','jus','the','cafe'],['jus'],'Какой напиток выбрали в ответе?','Que voulez-vous boire ? Je voudrais un jus.','кё вулэ ву буа́р? жё вудрэ э(н) жю́.',['Чай','Кофе','Сок'],2,'В ответе выбран сок.','Нужно выбрать сок; два других напитка в ответе не названы.')
choose(s,'reading',[A],['the','cafe','eau','chaud','froid'],['cafe','froid'],'Какой напиток в списке указан холодным?',fr('Un thé chaud. Un café froid.','э(н) тэ шо́. э(н) кафэ фруа́.'),['Чай','Кофе','Вода'],1,'Холодная температура указана у кофе.','Нужно выбрать кофе. Чай назван горячим, вода в списке отсутствует.')
t=fill(s,[A],['drink-question','je-voudrais','the','cafe','eau'],['the'],'Ответьте по условию: нужен чай. Выберите целое сочетание для ответа.','Je voudrais ','жё вудрэ́','','',[wc('eau'),wc('the'),wc('cafe')],1,'Je voudrais un thé','жё вудрэ э(н) тэ́','Ответ сообщает нужный напиток — чай.','Нужен чай; вода и кофе изменят выбор.')
t['prompt']=fr('Que voulez-vous boire ?','кё вулэ ву буа́р?')
choose(s,'vocabulary',[A],['je-voudrais','eau','merci','de-rien'],['je-voudrais'],'Заказ ещё не сделан. Вы хотите попросить воду. Какая реплика формулирует просьбу?',ru('Отличите просьбу от благодарности и ответа на неё.'),[wc('merci'),fr('Je voudrais de l’eau.','жё вудрэ дё ло́.'),wc('de-rien')],1,'Выбран образец вежливой просьбы.','Нужно сформулировать просьбу; благодарность и ответ на неё не сообщают нужный заказ.')
order(s,[A],['je-voudrais','eau','froid'],[],'Попросите холодную воду. Начните с вежливой рамки, затем добавьте целое сочетание для воды и температуру.',[('Je voudrais','жё вудрэ́'),('de l’eau','дё ло́'),('froide.','фруа́д')],'Je voudrais de l’eau froide.','жё вудрэ дё ло фруа́д.','Вода сохранена целым сочетанием, после неё стоит показанная форма температуры.','Сначала рамка просьбы, затем вода целой карточкой и холодная температура.')
page(s,[('Вопрос о напитке',['В учебном диалоге вопрос запрашивает выбор напитка. Ответить можно знакомым началом просьбы и сочетанием нужного напитка.'],[W['drink-question']['example'],ex('Je voudrais un thé.','Мне бы хотелось чая.','жё вудрэ э(н) тэ́')]),('Выбор по условию',['Информация о напитке и температуре дана в условии или списке. Не заменяйте один параметр другим и не угадывайте выбор по привычкам.'],[ex('Un thé chaud.','Горячий чай.','э(н) тэ шо́'),ex('Un café froid.','Холодный кофе.','э(н) кафэ фруа́')]),('Просьба и благодарность',['Просьба сообщает, что хочется получить. Благодарность и знакомый ответ на неё остаются отдельными репликами с другой функцией.'],[ex('Je voudrais de l’eau.','Мне бы хотелось воды.','жё вудрэ дё ло́'),ex('Merci.','Спасибо.','мэрси́')])])

s=start(5,'Соберите заказ одного напитка по указанным условиям: напиток, температура и вежливая просьба. Все слова и формы уже изучены.',['eau','jus','the','cafe','je-voudrais','froid','chaud','drink-question','sil-vous-plait','numero','un','deux'])
intro(s,1,'В этом занятии только знакомый материал. Проверьте, какой напиток и какая температура нужны по условию. Если температура не названа, не добавляйте её сами. В сборке используйте каждую карточку один раз.',[])
listen(s,[A],['je-voudrais','cafe','the','chaud','froid'],['cafe','chaud'],'Какой заказ точно соответствует услышанному?','Je voudrais un café chaud.','жё вудрэ э(н) кафэ шо́.',['Холодный кофе','Горячий чай','Горячий кофе'],2,'Запрошены кофе и горячая температура.','Нужен горячий кофе; смена напитка или температуры меняет заказ.')
choose(s,'reading',[A],['numero','un','deux','the','jus','chaud','froid'],['jus','froid'],'По записям выберите номер холодного сока.',fr('Numéro un : un thé chaud. Numéro deux : un jus froid.','нюмэро э(н) : э(н) тэ шо́. нюмэро дё : э(н) жю фруа́.'),['1','2','Нужный напиток не указан'],1,'Холодный сок указан под номером два.','Выберите 2; номер 1 относится к горячему чаю.')
pairs(s,[U],[('eau','Вода'),('the','Чай'),('cafe','Кофе')],'Вы повторили три знакомых названия напитков.','Каждому напитку соответствует своё сочетание; не заменяйте один другим.')
listen(s,[A],['drink-question'],['drink-question'],'Что хочет узнать собеседник?','Que voulez-vous boire ?','кё вулэ ву буа́р?',['Кто этот человек','Возраст человека','Какой напиток нужен'],2,'Спрашивают о выборе напитка.','Нужен смысл вопроса о напитке; личность и возраст не запрашиваются.')
grammar(fill(s,[A],['eau','froid','chaud'],['froid'],'В заказе нужна холодная вода. Дополните сочетание показанной формой температуры.','De l’eau ','дё ло́','','',[fr('chaude','шо́д'),fr('froide','фруа́д'),fr('froid','фруа́')],1,'De l’eau froide','дё ло фруа́д','С водой выбрана знакомая форма холодной температуры.','Нужна показанная форма женского рода «холодная»; горячая температура и другая форма не подходят.'))
choose(s,'vocabulary',[A],['the','cafe','chaud','froid'],['the','chaud'],'По условию нужен горячий чай. Какую запись выбрать?',ru('Должны совпасть напиток и температура.'),[fr('Un café chaud','э(н) кафэ шо́'),fr('Un thé froid','э(н) тэ фруа́'),fr('Un thé chaud','э(н) тэ шо́')],2,'Выбраны чай и горячая температура.','Нужен горячий чай; горячий кофе или холодный чай изменяют одно из условий.')
order(s,[A],['je-voudrais','jus','sil-vous-plait'],[],'Попросите сок. Начните с вежливой рамки и закончите знакомым «пожалуйста».',[('Je voudrais','жё вудрэ́'),('un jus,','э(н) жю́'),('s’il vous plaît.','силь ву плэ́')],'Je voudrais un jus, s’il vous plaît.','жё вудрэ э(н) жю, силь ву плэ́.','После начала просьбы назван сок, затем добавлена целая формула вежливости.','Начните с готовой рамки, затем поставьте сочетание для сока и завершите просьбу.')
choose(s,'reading',[A],['je-voudrais','eau','cafe','chaud','froid'],['eau','chaud'],'Какой напиток и температура прямо указаны в просьбе?',fr('Je voudrais de l’eau chaude.','жё вудрэ дё ло шо́д.'),['Холодная вода','Горячий кофе','Горячая вода'],2,'В просьбе указана горячая вода.','Нужно сохранить воду и горячую температуру; другой напиток или холодная вода изменят смысл.')
page(s,[('Проверяем заказ по условию',['Напиток и температура даны отдельно. Сначала найдите нужное название, затем проверьте, что к нему относится правильная температура.'],[ex('Un café chaud.','Горячий кофе.','э(н) кафэ шо́'),ex('Un jus froid.','Холодный сок.','э(н) жю фруа́')]),('Просьба из знакомых частей',['Готовая вежливая рамка соединяется с уже изученным сочетанием напитка. При необходимости добавляется знакомое «пожалуйста».'],[ex('Je voudrais un jus, s’il vous plaît.','Мне бы хотелось сока, пожалуйста.','жё вудрэ э(н) жю, силь ву плэ́')]),('С водой сохраняем показанную форму',['Две формы температуры с водой уже встречались. Повторяйте целые сочетания и не выводите температуру из общего названия напитка.'],[ex('De l’eau froide.','Холодной воды.','дё ло фруа́д'),ex('De l’eau chaude.','Горячей воды.','дё ло шо́д')])])

for s in S:
 required=list(dict.fromkeys(w for t in s['tasks'] for w in t['vocabulary_ids']))
 required+=list(dict.fromkeys(w for i in s['introductions'] for w in i['vocabulary_ids']))
 present={w['id'] for w in s['vocabulary']}
 for w in required:
  if w not in present:s['vocabulary'].append(copy.deepcopy(W[w]));present.add(w)
 s['skills']=list(dict.fromkeys(t['skill'] for t in s['tasks']))
NOTES={w['fr']:w['transcription_ru'] for w in W.values()}
NOTES.update({'froide':'фруа́д','chaude':'шо́д','café':'кафэ́','Que voulez-vous boire ?':'кё вулэ ву буа́р?'})
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
unit=dict(id=plan['id'],level='PRE_A1',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Вода, сок, чай и кофе в простом заказе: выбор напитка, холодная и горячая температура, вопрос и вежливая просьба.',learning_outcomes=['Понимать названия четырёх изученных напитков в коротких репликах и списках.','Выбирать напиток и прямо указанную температуру по условию.','Понимать вопрос о том, что человек хочет пить.','Собирать вежливую просьбу о напитке из готовых карточек.'])
P=dict(schema_version=1,package_id='programme-pre-a1-u09-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'PRE_A1-U09.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('U09 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=S[n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,
 review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('je-voudrais','Вернуть готовую вежливую просьбу в знакомой ситуации с едой без новой таблицы форм глагола.'),('eau','Повторить знакомое сочетание с водой и не строить полную систему артиклей по одному примеру.'),('the','Вернуть чай в короткий контекст завтрака, когда новые слова еды уже объяснены.'),('cafe','Вернуть кофе как напиток в контекст завтрака, сохранив смысловой ключ.'),('chaud','При уместном возврате повторить температуру и показанные формы без новых исключений.'),('drink-question','В контрольном занятии U10 использовать знакомый вопрос о напитке как повторение U09, не как новый материал.')]],
 checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),
 open_issues=['До импорта U09 целевая база должна содержать PRE_A1-U08-L05. Пакет не создаёт отсутствующую внешнюю зависимость.','Человеку нужно проверить язык и браузерную озвучку названий напитков, вопроса и полных просьб; особенно формы температуры с водой.','Выполнить предпросмотр, пробное прохождение и замер длительности. Ориентир 6 минут на занятие не измерен.'],
 progress=dict(current_unit=plan['id'],prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='PRE_A1-U10',previous_unit='PRE_A1-U08',previous_unit_advance_authorized=True,previous_unit_import_confirmed=False),
 scope_notes=['Команда «Следующий этап» разрешает подготовку U09; отдельного подтверждения языкового принятия или импорта U08 нет.','Названия напитков учатся показанными целыми сочетаниями; системная таблица частичных и неопределённых артиклей не вводится.','Café дано в значении напитка. Значение заведения и отдельный ключ для него в этой теме не создаются.','Просьба je voudrais вводится как готовая рамка без полной таблицы условного наклонения.','Формы froide/chaude сохраняют ключи холодной и горячей температуры. Для формы рода новые смысловые значения не создаются.','Из общего названия напитка температура не выводится. В заданиях выбираются напиток и явно указанная температура.','В L01–L04 соответственно 3, 2, 2 и 1 новое словарное значение. Формы с водой объяснены в L03, вопрос — в L04. В L05 нового обязательного материала нет.','Существующие ключи просьбы «пожалуйста», благодарности, семейных слов и признака размера сохранены.','Подписанная сборка не оценивает знание всех слов: assessed_vocabulary_ids в word_order пуст.','Основной LEXEME_REGISTRY.json и предыдущие пакеты не изменены. Новые ключи предложены отдельно до принятия.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Вежливая рамка просьбы; целые сочетания de l’eau/un jus.'),dict(session_id=S[1]['id'],material='Целые сочетания un thé/un café в заказе одной порции.'),dict(session_id=S[2]['id'],material='Температура после напитка; показанные формы froide/chaude с водой.'),dict(session_id=S[3]['id'],material='Готовый вопрос Que voulez-vous boire ? и ответы изученной просьбой.')]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
(OUT/'PRE_A1-U09.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps(dict(sessions=len(S),tasks=sum(len(s['tasks']) for s in S),new_lexemes=len(FIRST)),ensure_ascii=False))
