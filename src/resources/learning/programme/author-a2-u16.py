"""Author A2-U16 only; preserve previous sessions and lexical meanings."""
import json,copy,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[4]
KIT=ROOT/'plans/daily-practice-authoring'
OUT=Path(__file__).parent/'packages/A2'
A1=Path(__file__).parent/'packages/A1'
PRE=Path(__file__).parent/'packages/PRE_A1'
level=json.loads((KIT/'levels/A2.plan.json').read_text())['level']
plan=level['units'][15]
W={}
for source in [OUT/'A2-U15.v1.json',OUT/'A2-U14.v1.json',OUT/'A2-U13.v1.json',OUT/'A2-U12.v1.json',OUT/'A2-U11.v1.json',OUT/'A2-U10.v1.json',OUT/'A2-U09.v1.json',OUT/'A2-U08.v1.json',OUT/'A2-U07.v1.json',OUT/'A2-U06.v1.json',OUT/'A2-U05.v1.json',OUT/'A2-U04.v1.json',OUT/'A2-U03.v1.json',OUT/'A2-U02.v1.json',OUT/'A2-U01.v1.json']+[A1/f'A1-U{n:02}.v1.json' for n in range(20,0,-1)]+[PRE/f'PRE_A1-U{n:02}.v1.json' for n in range(20,1,-1)]+[KIT/'examples/PRE_A1-U01.v2.json']:
 for s in json.loads(source.read_text())['sessions']:
  for w in s['vocabulary']:W.setdefault(w['id'],copy.deepcopy(w))
for w in W.values():
 if 'example' not in w:w['example']=dict(fr=w['fr'],ru=w['ru'][0],transcription_ru=w['transcription_ru'])
OLD=set(W);FIRST={};S=[]
U='A2.U16.UNDERSTAND';A='A2.U16.APPLY'
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
word('foret','fr:foret:forest','une forêt','лес','юн форэ́',1,ex('La forêt est près du lac.','Лес находится рядом с озером.','ла форэ э прэ дю ла́к'),['Лес','Озеро','Тропа'],0,'Назовите лес, а не озеро.',[('Un lac.','э(н) лак'),('Une forêt.','юн форэ́'),('Un parc.','э(н) парк')],1)
word('lac','fr:lac:lake','un lac','озеро','э(н) лак',1,ex('Le lac est près du parc.','Озеро находится рядом с парком.','лё лак э прэ дю па́рк'),['Тропа','Озеро','Заголовок'],1,'Выберите озеро как место отдыха.',[('Une forêt.','юн форэ́'),('Un titre.','э(н) ти́тр'),('Un lac.','э(н) лак')],2)
word('sentier','fr:sentier:walking-path','un sentier','тропа для прогулки','э(н) са(н)тье́',1,ex('Le sentier est près du lac.','Тропа находится рядом с озером.','лё са(н)тье э прэ дю ла́к'),['Тропа','Лес','Программа обучения'],0,'Нужно назвать тропу, а не саму прогулку.',[('Un sentier.','э(н) са(н)тье́'),('Une randonnée.','юн ра(н)донэ́'),('Une formation.','юн формасьо́(н)')],0)
word('randonnee','fr:randonnee:recreational-hike','une randonnée','пешая прогулка по маршруту','юн ра(н)донэ́',1,ex('Je voudrais faire une randonnée.','Мне бы хотелось совершить пешую прогулку.','жё вудрэ фэр юн ра(н)донэ́'),['Тропа как место','Пешая прогулка','Доставка'],1,'Вы хотите совершить пешую прогулку.',[('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт'),('Je voudrais faire une randonnée.','жё вудрэ фэр юн ра(н)донэ́'),('Je voudrais m’inscrire.','жё вудрэ мэ(н)скри́р')],1)
word('suivre-chemin','fr:suivre:follow-path','suivre','следовать по тропе или маршруту','сюи́вр',2,ex('Suivez le sentier.','Следуйте по тропе.','сюивэ лё са(н)тье́'),['Объявлять','Возмещать деньги','Следовать по маршруту'],2,'Выберите указание следовать по тропе.',[('Suivez le sentier.','сюивэ лё са(н)тье́'),('Fermez le livre.','фэрмэ лё ли́вр'),('Comment payer ?','кома(н) пэйе́')],0)
word('proteger','fr:proteger:protect-place','protéger','защищать, беречь место природы','протэжэ́',3,ex('Il faut protéger la forêt.','Нужно беречь лес.','иль фо протэжэ ла форэ́'),['Беречь место природы','Объявлять о событии','Выбирать оплату'],0,'Нужно сказать, что лес следует беречь.',[('Il faut suivre le sentier.','иль фо сюивр лё са(н)тье́'),('Il faut protéger la forêt.','иль фо протэжэ ла форэ́'),('Je voudrais faire une randonnée.','жё вудрэ фэр юн ра(н)донэ́')],1)
for w,g,b in [('foret','Назван лес.','Озеро и парк — другие места.'),('lac','Названо озеро.','Лес и заголовок не являются озером.'),('sentier','Названа тропа как место маршрута.','Прогулка — действие, программа — другое понятие.'),('randonnee','Выражено желание пешей прогулки.','Оплата и запись сами по себе не означают прогулку.'),('suivre-chemin','Дано указание следовать по тропе.','Закрывание книги и вопрос об оплате имеют другие цели.'),('proteger','Названо требование беречь лес.','Движение по тропе и желание прогулки не называют действие беречь.')]:W[w]['review_items'][1]['feedback']=fb(g,b)

def read(s,gs,vs,av,q,f,p,cs,a,g,b):return choose(s,'reading',gs,vs,av,q,fr(f,p),cs,a,g,b)
def warm(s,u,n,q):
 old=json.loads((OUT/f'{u}.v1.json').read_text());t=copy.deepcopy(next(t for t in old['sessions'][n-1]['tasks'] if t['id']==q));t['id']='Q01';s['tasks'].append(t)
 for o in t['objective_ids']:
  if o not in {x['id'] for x in s['objectives']}:s['objectives'].append(copy.deepcopy(OBJECTIVES[o]))
DATA=[dict(n=1,desc='Различите места отдыха, тропу и пешую прогулку.',warm=('A2-U15',3,'Q05'),intro='Une forêt — лес; un lac — озеро. Un sentier — тропа, а une randonnée — пешая прогулка по маршруту. Je voudrais faire une randonnée — желание прогулки. Место и действие различаются: тропа не является самой прогулкой.',iv=['foret','lac'],iv2=['sentier','randonnee'],notes=[('Une forêt','юн форэ́'),('un lac','э(н) лак'),('Un sentier','э(н) са(н)тье́'),('une randonnée','юн ра(н)донэ́'),('Je voudrais faire une randonnée','жё вудрэ фэр юн ра(н)донэ́')],rows=[('foret','Лес'),('lac','Озеро'),('sentier','Тропа'),('randonnee','Пешая прогулка')],f='La forêt est près du parc. Le lac est près de la forêt. Le sentier est près du lac. Je voudrais faire une randonnée avec ma sœur. Le sentier est calme. Le parc est loin de la gare.',p='ла форэ э прэ дю па́рк. лё лак э прэ дё ла форэ́. лё са(н)тье э прэ дю ла́к. жё вудрэ фэр юн ра(н)донэ авэк ма сё́р. лё са(н)тье э ка́льм. лё парк э луэ(н) дё ла га́р.',vs=['foret','pres-de','parc','lac','sentier','je-voudrais','randonnee','soeur','calme','loin-de','gare'],av=['lac','foret','sentier'],aq='Где тропа и где озеро?',ac=['Тропа у озера; озеро у леса','Тропа у вокзала; озеро у музея','Лес далеко от озера'],aa=0,rq='Какая активность названа как желание?',rc=['Покупка книги','Пешая прогулка с сестрой','Доставка посылки'],ra=1,rav=['randonnee','je-voudrais','soeur'],vq='Назовите активность, а не место.',vc=[('Un lac.','э(н) лак'),('Un sentier.','э(н) са(н)тье́'),('Une randonnée.','юн ра(н)донэ́')],va=2,vid=['randonnee'],before='Je voudrais faire ',bp='жё вудрэ фэр',after=' avec ma sœur.',ap='авэк ма сё́р',fc=[('une randonnée','юн ра(н)донэ́'),('un lac','э(н) лак')],fa=0,full='Je voudrais faire une randonnée avec ma sœur.',fp='жё вудрэ фэр юн ра(н)донэ авэк ма сё́р.',fins='Восстановите желание совершить прогулку с сестрой.',tokens=[('Je voudrais','жё вудрэ́'),('faire','фэр'),('une randonnée','юн ра(н)донэ́'),('avec ma sœur.','авэк ма сё́р')],of='Je voudrais faire une randonnée avec ma sœur.',op='жё вудрэ фэр юн ра(н)донэ авэк ма сё́р.',translation='Мне бы хотелось совершить пешую прогулку с сестрой.',oi='Соберите: мне бы хотелось совершить пешую прогулку с сестрой. Сначала желание, затем действие, прогулка и участник.',goals=[U]),
dict(n=2,desc='Поймите направление маршрута от леса к озеру и указание следовать по тропе.',warm=None,intro='Suivre — следовать по маршруту; выбранное указание Suivez le sentier — «Следуйте по тропе». В модели Le sentier va de la forêt au lac начальная точка — лес, конечная — озеро. Предлоги de … au связывают начало и конец. В обратном описании Le sentier va du lac à la forêt начало — озеро, конец — лес. Это вымышленное описание маршрута, не инструкция для реальной местности.',iv=['suivre-chemin','foret','lac'],notes=[('Suivre','сюи́вр'),('Suivez le sentier','сюивэ лё са(н)тье́'),('Le sentier va de la forêt au lac','лё са(н)тье ва дё ла форэ о ла́к'),('de','дё'),('au','о'),('Le sentier va du lac à la forêt','лё са(н)тье ва дю лак а ла форэ́')],rows=[('suivre-chemin','Следовать по маршруту'),('foret','Лес'),('lac','Озеро')],f='Le sentier va de la forêt au lac. Suivez le sentier. Le lac est près du parc. Je voudrais faire une randonnée avec mon frère. Le sentier est calme. La forêt est loin de la gare.',p='лё са(н)тье ва дё ла форэ о ла́к. сюивэ лё са(н)тье́. лё лак э прэ дю па́рк. жё вудрэ фэр юн ра(н)донэ авэк мо(н) фрэ́р. лё са(н)тье э ка́льм. ла форэ э луэ(н) дё ла га́р.',vs=['sentier','aller','foret','lac','suivre-chemin','pres-de','parc','je-voudrais','randonnee','frere','calme','loin-de','gare'],av=['foret','lac','suivre-chemin'],aq='Каковы начало и конец указанного маршрута?',ac=['От озера к лесу','От леса к озеру','От вокзала к музею'],aa=1,rq='Что предлагают делать?',rc=['Следовать по тропе','Обязательно вернуться к вокзалу','Оплатить доставку'],ra=0,rav=['suivre-chemin','sentier'],vq='Выберите указание движения по тропе.',vc=[('Un lac.','э(н) лак'),('Une randonnée.','юн ра(н)донэ́'),('Suivez le sentier.','сюивэ лё са(н)тье́')],va=2,vid=['suivre-chemin'],before='Le sentier va de la forêt ',bp='лё са(н)тье ва дё ла форэ',after='.',ap='',fc=[('au lac','о ла́к'),('à la gare','а ла га́р')],fa=0,full='Le sentier va de la forêt au lac.',fp='лё са(н)тье ва дё ла форэ о ла́к.',fins='Сохраните конечную точку: озеро.',tokens=[('Le sentier','лё са(н)тье́'),('va','ва'),('de la forêt','дё ла форэ́'),('au lac.','о ла́к')],of='Le sentier va de la forêt au lac.',op='лё са(н)тье ва дё ла форэ о ла́к.',translation='Тропа ведёт от леса к озеру.',oi='Соберите: тропа ведёт от леса к озеру. Сначала тропа и движение, затем начало и конец.',goals=[U]),
dict(n=3,desc='Различите необходимость беречь лес и оставаться на тропе.',warm=('A2-U13',5,'Q05'),intro='Protéger — беречь, защищать место природы. Rester — оставаться в указанном месте. Il faut protéger la forêt — «Нужно беречь лес». Il faut rester sur le sentier — «Нужно оставаться на тропе». Это два разных действия. В условии данного вымышленного места дополнительно повторяется запрет шуметь; это не универсальное правило всех лесов.',iv=['proteger','rester','il-faut'],notes=[('Protéger','протэжэ́'),('Rester','рэстэ́'),('Il faut protéger la forêt','иль фо протэжэ ла форэ́'),('Il faut rester sur le sentier','иль фо рэстэ сюр лё са(н)тье́')],rows=[('proteger','Беречь место природы'),('rester','Оставаться в месте'),('sentier','Тропа')],f='Il faut protéger la forêt. Il faut rester sur le sentier. Dans ce parc, il est interdit de faire du bruit. Le sentier va de la forêt au lac. Je voudrais faire une randonnée avec ma sœur.',p='иль фо протэжэ ла форэ́. иль фо рэстэ сюр лё са(н)тье́. да(н) сё парк, иль э тэ(н)тэрди дё фэр дю брюи́. лё са(н)тье ва дё ла форэ о ла́к. жё вудрэ фэр юн ра(н)донэ авэк ма сё́р.',vs=['il-faut','proteger','foret','rester','sentier','parc','il-est-interdit-de','faire-du-bruit','aller','lac','je-voudrais','randonnee','soeur'],av=['proteger','rester','sentier'],aq='Какие два требования названы?',ac=['Шуметь и покинуть тропу','Беречь лес и оставаться на тропе','Оплатить прогулку и доставку'],aa=1,rq='Какой запрет назван именно в условии этого парка?',rc=['Запрещено шуметь','Запрещена любая прогулка','Запрещено беречь лес'],ra=0,rav=['il-est-interdit-de','faire-du-bruit'],vq='Выберите требование оставаться на тропе.',vc=[('Je voudrais faire une randonnée.','жё вудрэ фэр юн ра(н)донэ́'),('Il faut protéger la forêt.','иль фо протэжэ ла форэ́'),('Il faut rester sur le sentier.','иль фо рэстэ сюр лё са(н)тье́')],va=2,vid=['rester','sentier'],before='Il faut ',bp='иль фо',after=' sur le sentier.',ap='сюр лё са(н)тье́',fc=[('protéger','протэжэ́'),('rester','рэстэ́')],fa=1,full='Il faut rester sur le sentier.',fp='иль фо рэстэ сюр лё са(н)тье́.',fins='Восстановите оставаться в указанном месте.',tokens=[('Il faut','иль фо́'),('rester','рэстэ́'),('sur','сюр'),('le sentier.','лё са(н)тье́')],of='Il faut rester sur le sentier.',op='иль фо рэстэ сюр лё са(н)тье́.',translation='Нужно оставаться на тропе.',oi='Соберите: нужно оставаться на тропе. Сначала необходимость, затем действие и место.',goals=[A]),
dict(n=4,desc='Выберите прогулку по явно названным свойствам тропы.',warm=('A2-U13',3,'Q05'),intro='Повторяем qui и que: Le sentier qui est calme — «Тропа, которая является тихой». Le sentier que je choisis — «Тропа, которую я выбираю». Для рассказа о только что завершённом участке используем знакомую рамку с изученным глаголом: Je viens de suivre le sentier — «Я только что прошёл по тропе». Прохождение участка не означает автоматически завершения всех дальнейших планов.',iv=['qui-relatif','que-relatif','venir-de'],notes=[('qui','ки'),('que','кё'),('Le sentier qui est calme','лё са(н)тье ки э ка́льм'),('Le sentier que je choisis','лё са(н)тье кё жё шуази́'),('Je viens de suivre le sentier','жё вьэ(н) дё сюивр лё са(н)тье́')],rows=[('randonnee','Пешая прогулка'),('sentier','Тропа'),('suivre-chemin','Следовать по маршруту')],f='Je voudrais faire une randonnée. Je choisis le sentier qui est calme. Le sentier que je choisis va de la forêt au lac. Le lac est près du parc. L’autre sentier est plus bruyant. Je voudrais un sentier calme.',p='жё вудрэ фэр юн ра(н)донэ́. жё шуази лё са(н)тье ки э ка́льм. лё са(н)тье кё жё шуази ва дё ла форэ о ла́к. лё лак э прэ дю па́рк. лотр са(н)тье э плю брюийа́(н). жё вудрэ э(н) са(н)тье ка́льм.',vs=['je-voudrais','randonnee','choisir','sentier','qui-relatif','calme','que-relatif','aller','foret','lac','pres-de','parc','autre','plus-comparatif','bruyant'],av=['choisir','calme','foret','lac'],aq='Какую тропу выбирают?',ac=['Более шумную','Ту, которая ведёт к вокзалу','Тихую от леса к озеру'],aa=2,rq='Какое основание выбора прямо названо?',rc=['Самая низкая цена','Тишина тропы','Обязательное отсутствие всех других людей'],ra=1,rav=['calme','choisir'],vq='Выберите желание совершить пешую прогулку.',vc=[('Je voudrais faire une randonnée.','жё вудрэ фэр юн ра(н)донэ́'),('Je voudrais payer par carte.','жё вудрэ пэйе пар ка́рт'),('Je voudrais préciser le tarif.','жё вудрэ прэсизэ лё тари́ф')],va=0,vid=['randonnee'],before='Le sentier ',bp='лё са(н)тье',after=' je choisis est calme.',ap='жё шуази э ка́льм',fc=[('que','кё'),('qui','ки')],fa=0,full='Le sentier que je choisis est calme.',fp='лё са(н)тье кё жё шуази э ка́льм.',fins='Восстановите тропа, которую я выбираю.',tokens=[('Le sentier','лё са(н)тье́'),('que je choisis','кё жё шуази́'),('est','э'),('calme.','ка́льм')],of='Le sentier que je choisis est calme.',op='лё са(н)тье кё жё шуази э ка́льм.',translation='Тропа, которую я выбираю, тихая.',oi='Соберите: тропа, которую я выбираю, тихая. Сначала тропа, затем уточнение выбора и свойство.',goals=[A])]
for d in DATA:
 s=start(d['n'],d['desc'],[])
 if d['warm']:warm(s,*d['warm'])
 else:
  t=copy.deepcopy(S[0]['tasks'][4]);t['id']='Q01';s['tasks'].append(t)
 if d['n']==1:
  intro(s,2,'Une forêt — лес; un lac — озеро. Это места, не названия активности.',d['iv'],d['notes'][:2])
  intro(s,2,'Un sentier — тропа. Une randonnée — пешая прогулка. Je voudrais faire une randonnée — желание совершить прогулку. Тропа не является самой прогулкой.',d['iv2'],d['notes'][2:])
 else:intro(s,2,d['intro'],d['iv'],d['notes'])
 pairs(s,d['goals'],d['rows'],'Названия и действия различены.','Сохраните значение каждой карточки.')
 listen(s,d['goals'],d['vs'],d['av'],d['aq'],d['f'],d['p'],d['ac'],d['aa'],'Ответ соответствует названному описанию.','Правильный смысл: '+d['ac'][d['aa']]+'. Не переносите сведения другой ситуации.')
 choose(s,'vocabulary',d['goals'],list(dict.fromkeys(d['vs']+(['payer','par-carte','preciser','tarif'] if d['n']==4 else []))),d['vid'],d['vq'],ru('Выберите точное выражение.'),[fr(f,p) for f,p in d['vc']],d['va'],'Выбрана реплика с нужным значением.','Нужная реплика: '+d['vc'][d['va']][0])
 read(s,d['goals'],d['vs'],d['rav'],d['rq'],d['f'],d['p'],d['rc'],d['ra'],'Ответ следует из текста.','Правильный смысл: '+d['rc'][d['ra']]+'. Не добавляйте неуказанных условий.')
 fill(s,d['goals'],d['vs'],[],d['fins'],d['before'],d['bp'],d['after'],d['ap'],[fr(f,p) for f,p in d['fc']],d['fa'],d['full'],d['fp'],'Смысл изученной модели сохранён.','Правильная фраза: '+d['full'])
 order(s,d['goals'],d['vs'],[],d['oi'],d['tokens'],d['of'],d['op'],'Направление и роли слов сохранены.','Следуйте заданному порядку и не меняйте начало и конец маршрута.')
 page(s,[('Изученная модель',[d['intro']],[ex(d['of'],d['translation'],d['op'])]),('Читать условия',['Все описания и правила вымышлены для языка. Место, действие, направление и основание выбора читаются отдельно. Этот материал не задаёт маршрут для реальной местности.'],[ex(d['of'],d['translation'],d['op'])])])
 s['tasks'][3]['feedback']['pronunciation_notes']=[dict(fr=d['vc'][d['va']][0],transcription_ru=d['vc'][d['va']][1])]
 # fill helper removes a trailing full stop when the suffix contains punctuation only.
 ff=s['tasks'][5]['completed_phrases'][0]
 s['tasks'][5]['feedback']['pronunciation_notes']=[dict(fr=d['full'],transcription_ru=d['fp'])]
 s['public_page']['explanation_blocks'][0]['pronunciation_notes']=[dict(fr=f,transcription_ru=p) for f,p in d['notes']]
s=start(5,'Объедините маршрут, выбор прогулки и правила, рассказав о только что пройденном участке без нового материала.',[])
F='Je viens de suivre le sentier de la forêt au lac. Le sentier est calme. La forêt est près du parc. Il faut rester sur le sentier. Il faut protéger la forêt.'
T='жё вьэ(н) дё сюивр лё са(н)тье дё ла форэ о ла́к. лё са(н)тье э ка́льм. ла форэ э прэ дю па́рк. иль фо рэстэ сюр лё са(н)тье́. иль фо протэжэ ла форэ́.'
V=['venir-de','suivre-chemin','sentier','foret','lac','calme','pres-de','parc','il-faut','rester','proteger']
listen(s,[U],V,['foret','lac','suivre-chemin'],'Какой участок говорящий только что прошёл?',F,T,['От озера к лесу','От леса к озеру','От музея к вокзалу'],1,'Назван участок от леса к озеру.','Не меняйте начало и конец; событие описано как только что выполненное.')
R='Le sentier va du lac à la forêt. Suivez le sentier. La forêt est près du parc. Je voudrais faire une randonnée avec ma sœur. Le sentier est calme. Le lac est loin de la gare.'
P='лё са(н)тье ва дю лак а ла форэ́. сюивэ лё са(н)тье́. ла форэ э прэ дю па́рк. жё вудрэ фэр юн ра(н)донэ авэк ма сё́р. лё са(н)тье э ка́льм. лё лак э луэ(н) дё ла га́р.'
read(s,[U],['sentier','aller','lac','foret','suivre-chemin','pres-de','parc','je-voudrais','randonnee','soeur','calme','loin-de','gare'],['lac','foret'],'В этом отдельном описании каковы начало и конец маршрута?',R,P,['От озера к лесу','От леса к озеру','От парка к вокзалу'],0,'В новом описании начало — озеро, конец — лес.','Не переносите направление из другого сообщения.')
phrase_pairs(s,[A],[('Il faut protéger la forêt.','иль фо протэжэ ла форэ́','Нужно беречь лес.',['il-faut','proteger','foret'],['proteger']),('Il faut rester sur le sentier.','иль фо рэстэ сюр лё са(н)тье́','Нужно оставаться на тропе.',['il-faut','rester','sentier'],['rester']),('Je voudrais faire une randonnée.','жё вудрэ фэр юн ра(н)донэ́','Мне бы хотелось совершить пешую прогулку.',['je-voudrais','randonnee'],['randonnee'])],'Правила и желание активности различены.','Не заменяйте правило одной активности названием места.')
d=DATA[3]
listen(s,[A],d['vs'],['calme','choisir'],'Какое свойство тропы соответствует названному желанию?',d['f'],d['p'],['Самая низкая цена','Отсутствие всех людей','Тишина'],2,'Человек хочет тихую тропу и выбирает её.','Цена и отсутствие других людей не сообщаются.')
read(s,[U],V,['venir-de','suivre-chemin','foret','lac'],'Что прямо сообщено о пройденном маршруте?',F,T,['Он только запланирован','Участок от леса к озеру только что пройден','Все дальнейшие прогулки уже закончены'],1,'Говорящий только что прошёл названный участок.','Не превращайте прохождение участка в завершение всех возможных дальнейших планов.')
choose(s,'vocabulary',[A],['il-faut','proteger','foret','rester','sentier','suivre-chemin'],['proteger'],'Выберите требование беречь лес.',ru('Сохраните именно действие защиты.'),[fr('Il faut protéger la forêt.','иль фо протэжэ ла форэ́'),fr('Il faut rester sur le sentier.','иль фо рэстэ сюр лё са(н)тье́'),fr('Suivez le sentier.','сюивэ лё са(н)тье́')],0,'Названо действие беречь лес.','Следование по тропе и пребывание на ней — другие действия.')
grammar(fill(s,[A],['sentier','que-relatif','qui-relatif','choisir','calme'],['que-relatif'],'Восстановите тропа, которую я выбираю.','Le sentier ','лё са(н)тье',' je choisis est calme.','жё шуази э ка́льм',[fr('qui','ки'),fr('que','кё')],1,'Le sentier que je choisis est calme.','лё са(н)тье кё жё шуази э ка́льм.','Тропа — объект выбора; дальше назван выбирающий человек.','Модель которая является тихой устроена иначе, чем которую я выбираю.'))
order(s,[U],['venir-de','suivre-chemin','sentier','foret','lac'],[],'Соберите: я только что прошёл по тропе от леса к озеру. Сначала недавнее действие, затем тропа, начало и конец.',[('Je viens de','жё вьэ(н) дё'),('suivre','сюи́вр'),('le sentier','лё са(н)тье́'),('de la forêt','дё ла форэ́'),('au lac.','о ла́к')],'Je viens de suivre le sentier de la forêt au lac.','жё вьэ(н) дё сюивр лё са(н)тье дё ла форэ о ла́к.','Направление и недавнее выполнение сохранены.','После рамки недавнего действия поставьте тропу, затем лес как начало и озеро как конец.')
page(s,[('Рассказать об участке',['Знакомая рамка недавнего действия применяется к уже изученному глаголу следовать по маршруту. Начало и конец сохраняются отдельно.'],[ex('Je viens de suivre le sentier de la forêt au lac.','Я только что прошёл по тропе от леса к озеру.','жё вьэ(н) дё сюивр лё са(н)тье дё ла форэ о ла́к')]),('Повторить выбор и правила',['Тишина — названное свойство выбранного варианта, а требования относятся к условиям вымышленного места. Нового обязательного материала нет.'],[W['proteger']['example'],W['rester']['example']])])

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
unit=dict(id=plan['id'],level='A2',slug=plan['slug'],title=plan['title'],order=plan['order'],summary='Места отдыха, простой вымышленный маршрут, правила поведения, выбор активности и рассказ о пройденном участке.',learning_outcomes=['Различать лес, озеро, тропу и пешую прогулку.','Понимать начало и конец маршрута.','Выбирать указание следовать по тропе.','Различать требования беречь лес и оставаться на тропе.','Выбирать активность по названному свойству и рассказывать о пройденном участке.'])
P=dict(schema_version=1,package_id='programme-a2-u16-v1',language_pair='fr-ru',units=[unit],sessions=S)
annotate(P)
OUT.mkdir(parents=True,exist_ok=True);target=OUT/'A2-U16.v1.json'
if target.exists() and '--update-unimported-draft' not in sys.argv:raise SystemExit('A2-U16 exists: inspect import/version history before changing content.')
target.write_text(json.dumps(P,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
coverage=[dict(objective_id=o,evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if o in t['objective_ids']]) for o in dict.fromkeys(o['id'] for s in S for o in s['objectives'])]
D=dict(unit_id=plan['id'],package_file=target.name,package_id=P['package_id'],session_ids=[s['id'] for s in S],new_lexemes=[dict(**{k:W[w][k] for k in ['id','lexeme_key','fr','ru','transcription_ru']},first_session_id=plan['sessions'][n-1]['id']) for w,n in FIRST.items()],reused_lexeme_keys=sorted({w['lexeme_key'] for s in S for w in s['vocabulary'] if w['id'] in OLD}),objective_coverage=coverage,review_targets_for_next_unit=[dict(lexeme_key=W[w]['lexeme_key'],reason=r) for w,r in [('sentier','Сохранять путь отдельно от действия прогулки.'),('randonnee','Вернуть выбранную активность.'),('suivre-chemin','Различать начало и конец маршрута.'),('proteger','Повторить беречь место природы в конкретной ситуации.'),('rester','Сохранить прежний ключ оставаться в месте.'),('qui-relatif','Вернуть описание собственного свойства места.'),('que-relatif','Вернуть место как объект выбора.')]],checks=dict(json_parsed=False,schema_validated=False,site_preview_passed=False,browser_audio_checked=False,language_reviewed_by_human=False),open_issues=['Проверить внешнюю зависимость A2-U15-L05 в целевой базе.','Редактору проверить естественность описаний маршрутов, относительных конструкций и рассказа с venir de; прослушать французскую озвучку.','Выполнить предпросмотр и пробное прохождение, измерить время. Эти действия фактически не выполнялись.'],progress=dict(current_unit='A2-U16',prepared_session_ids=[s['id'] for s in S],user_accepted_session_ids=[],remaining_session_ids=[],next_unit_not_started='A2-U17',next_unit_title='Советы и рекомендации'),scope_notes=['Один полный пакет пяти занятий. Все ID, названия, зависимости и kind взяты из плана.','Новые значения распределены как четыре, одно, одно, ноль, ноль. В первом занятии четыре карточки разбиты на два введения по две; ни одно введение не содержит более трёх карточек. В L05 нового обязательного материала нет.','Rester сохраняет прежний fr:rester:stay-at-place и полный объект. Aller, местные предлоги, qui / que, сравнительные признаки и venir de также используют прежние ключи.','Forêt и lac — места, sentier — тропа, randonnée — прогулка как активность. Не создаются дубли одного слова ради смены учебной темы.','Suivez — выбранная форма suivre, объяснённая до проверки. Модели de la forêt au lac и du lac à la forêt поясняют противоположные направления.','В L03 повторены требования и расписание из A2-U13, в L04 — текущая длительность обучения оттуда же. В L01 возвращаются источник и место события из A2-U15.','Qui связывает тропу с собственным свойством, que — с действием выбирающего человека. Эти функции не подменяются вопросом кто или сравнительным чем.','В L04 до итоговой практики повторена Je viens de suivre le sentier; в L05 этот известный образец описывает только что пройденный участок. Это не подтверждает выполнение всех дальнейших планов.','Все места, направления, правила и прогулки вымышлены для языка. Пакет не предлагает реальный туристический маршрут, оценку безопасности или универсальное правило поведения во всех лесах.','Вымышленное требование не шуметь относится к конкретному парку сообщения. Беречь лес и оставаться на тропе — разные названные действия.','Неуказанные цена, сложность, отсутствие других людей и реальные расстояния не выводятся из тишины или наличия озера.','Ответы только выбор, пары, пропуск с вариантами и сборка. Нет свободного ввода, микрофона или реальных поездок.','Озвучка только браузером: fr-FR, asset_id=null. Нет аудиофайлов и внешних сервисов. Импорт и публикация не выполнялись, принятие пользователем не подтверждено.'])
D['session_material']=[dict(session_id=s['id'],new_lexeme_keys=[W[w]['lexeme_key'] for w,n in FIRST.items() if n==s['order']],reused_lexeme_keys=sorted({w['lexeme_key'] for w in s['vocabulary'] if w['id'] in OLD or FIRST.get(w['id'],99)<s['order']}),task_count=len(s['tasks'])) for s in S]
D['lexeme_evidence']=[dict(lexeme_key=W[w]['lexeme_key'],evidence=[dict(session_id=s['id'],task_id=t['id'],skill=t['skill']) for s in S for t in s['tasks'] if w in t['assessed_vocabulary_ids']]) for w in FIRST]
D['introduced_grammar']=[dict(session_id=S[0]['id'],material='Место и активность; Je voudrais faire une randonnée.'),dict(session_id=S[1]['id'],material='Suivez le sentier; de la forêt au lac / du lac à la forêt.'),dict(session_id=S[2]['id'],material='Il faut protéger / rester; повторение запрета конкретной ситуации.'),dict(session_id=S[3]['id'],material='Le sentier qui est calme / que je choisis; повторение Je viens de suivre.')]
(OUT/'A2-U16.authoring-delta.json').write_text(json.dumps(D,ensure_ascii=False,indent=2)+'\n')
print('Prepared',len(S),'sessions',sum(len(s['tasks']) for s in S),'tasks')
