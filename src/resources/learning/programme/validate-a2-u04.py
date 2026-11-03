import json, re, copy
from pathlib import Path
import jsonschema
R=Path('/home/ksv180384/projects/afr')
P=R/'src/resources/learning/programme/packages/A2/A2-U04.v1.json'
D=P.with_name('A2-U04.authoring-delta.json')
p=json.loads(P.read_text()); d=json.loads(D.read_text())
schema=json.loads((R/'plans/daily-practice-authoring/reference/session-package-v1.schema.json').read_text())
errors=sorted(jsonschema.Draft202012Validator(schema).iter_errors(p),key=lambda e:list(map(str,e.path)))
for e in errors: print('SCHEMA',list(e.path),e.message[:1000])
assert not errors
plan=json.loads((R/'plans/daily-practice-authoring/levels/A2.plan.json').read_text())['level']['units'][3]
previous=json.loads((R/'plans/daily-practice-authoring/examples/PRE_A1-U01.v2.json').read_text())
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U02.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U03.v1.json').read_text())['sessions'])
for unit in ['PRE_A1-U04','PRE_A1-U05']:
 previous['sessions'].extend(json.loads((R/f'src/resources/learning/programme/packages/PRE_A1/{unit}.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U06.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U07.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U08.v1.json').read_text())['sessions'])
for unit in ['PRE_A1-U09','PRE_A1-U10']:
 previous['sessions'].extend(json.loads((R/f'src/resources/learning/programme/packages/PRE_A1/{unit}.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U11.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U12.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U13.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U14.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U15.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U16.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U17.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U18.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U19.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/PRE_A1/PRE_A1-U20.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/A1/A1-U01.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/A1/A1-U02.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/A1/A1-U03.v1.json').read_text())['sessions'])
for n in [4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]:
 previous['sessions'].extend(json.loads((R/f'src/resources/learning/programme/packages/A1/A1-U{n:02}.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/A2/A2-U01.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/A2/A2-U02.v1.json').read_text())['sessions'])
previous['sessions'].extend(json.loads((R/'src/resources/learning/programme/packages/A2/A2-U03.v1.json').read_text())['sessions'])
old={s['id'] for s in previous['sessions']}
keys={w['lexeme_key']:w for s in previous['sessions'] for w in s['vocabulary']}
seen={s['id'] for s in previous['sessions'] if s['level']=='A2'}; introduced=set(keys); task_count=0
for s,planned in zip(p['sessions'],plan['sessions']):
 for k in ['id','title','kind','order','prerequisite_ids']: assert s[k]==planned[k],(s['id'],k)
 assert s['version']==1 and s['unit_id']==plan['id'] and s['level']=='A2'
 assert set(s['prerequisite_ids'])<=seen
 seen.add(s['id'])
 assert 6<=len(s['tasks'])<=8
 assert set(s['skills'])=={t['skill'] for t in s['tasks']}
 vs={w['id']:w for w in s['vocabulary']}; os={o['id'] for o in s['objectives']}; ts={t['id'] for t in s['tasks']}
 assert len(vs)==len(s['vocabulary']) and len(ts)==len(s['tasks'])
 if s['order']==5: assert {w['lexeme_key'] for w in s['vocabulary']}<=introduced
 for w in s['vocabulary']:
  assert len(w['review_items'])==2 and all(t['type']=='single_choice' for t in w['review_items'])
  assert 'example' in w
  if w['lexeme_key'] in keys:
   assert all(w[k]==keys[w['lexeme_key']][k] for k in ['id','fr','ru','transcription_ru'])
  for t in w['review_items']:
   assert t['correct_choice_id'] in {c['id'] for c in t['choices']}
   assert len({c['text'] for c in t['choices']})==len(t['choices'])
 for i in s['introductions']:
  assert i['before_task_id'] in ts and set(i['vocabulary_ids'])<=set(vs) and len(i['vocabulary_ids'])<=3
 locally_introduced=set(introduced)
 for t in s['tasks']:
  for i in s['introductions']:
   if i['before_task_id']==t['id']: locally_introduced.update(vs[x]['lexeme_key'] for x in i['vocabulary_ids'])
  assert t['type'] in ['single_choice','matching_pairs','fill_blank','word_order']
  assert set(t['objective_ids'])<=os and set(t['vocabulary_ids'])<=set(vs)
  assert set(t['assessed_vocabulary_ids'])<=set(t['vocabulary_ids'])
  assert {vs[x]['lexeme_key'] for x in t['vocabulary_ids']}<=locally_introduced,(s['id'],t['id'],{vs[x]['lexeme_key'] for x in t['vocabulary_ids']}-locally_introduced)
  if 'choices' in t:
   assert t['correct_choice_id'] in {c['id'] for c in t['choices']}
   assert len({c['text'] for c in t['choices']})==len(t['choices'])
  if t['type']=='fill_blank':
   assert t['input_mode']=='choice'
   c=next(c for c in t['choices'] if c['id']==t['correct_choice_id'])
   assert t['text_before']+c['text']+t['text_after'] in [v['fr'] for v in t['completed_phrases']]
  if t['type']=='matching_pairs':
   assert {v['id'] for v in t['left_items']}=={v['left_id'] for v in t['correct_pairs']}
   assert {v['id'] for v in t['right_items']}=={v['right_id'] for v in t['correct_pairs']}
   assert len(t['correct_pairs'])==len(t['left_items'])==len(t['right_items'])
   assert set(t['assessed_vocabulary_ids'])=={x for pair in t['correct_pairs'] for x in pair['assessed_vocabulary_ids']}
  if t['type']=='word_order':
   tt={v['id']:v['text'] for v in t['tokens']}
   for ids in t['accepted_orders']:
    assert len(ids)==len(tt) and set(ids)==set(tt)
    assert ' '.join(tt[i] for i in ids) in [v['fr'] for v in t['completed_phrases']]
  task_count+=1
 introduced.update(w['lexeme_key'] for w in s['vocabulary'])
 assert len(s['public_page']['explanation_blocks'])>=2
assert len(p['sessions'])==5
assert 'levels' not in p and 'curricula' not in p
assert [s['kind'] for s in p['sessions']]==['practice']*5
assert [len(s['new_lexeme_keys']) for s in d['session_material']]==[2,2,1,0,0]
assert all(item['evidence'] for item in d['lexeme_evidence'])
assert len(d['new_lexemes'])==5
assert len({v['lexeme_key'] for v in d['new_lexemes']})==5
for goal in [o['id'] for o in plan['objectives']]:
 evidence=next(x['evidence'] for x in d['objective_coverage'] if x['objective_id']==goal)
 assert len({x['session_id'] for x in evidence})>=2
 assert 'vocabulary' in {x['skill'] for x in evidence} and len({x['skill'] for x in evidence})>=2
for entry in d['objective_coverage']:
 for e in entry['evidence']:
  t=next(t for s in p['sessions'] if s['id']==e['session_id'] for t in s['tasks'] if t['id']==e['task_id'])
  assert entry['objective_id'] in t['objective_ids'] and t['skill']==e['skill']
def walk(v):
 if isinstance(v,list):
  for x in v: walk(x)
 elif isinstance(v,dict):
  if 'transcription_ru' in v: assert not re.search('[A-Za-z0-9]',v['transcription_ru'])
  if set(['text','lang','asset_id'])<=set(v): assert v['lang']=='fr-FR' and v['asset_id'] is None
  if 'pronunciation_notes' in v:
   text=json.dumps({k:x for k,x in v.items() if k!='pronunciation_notes'},ensure_ascii=False)
   assert len({x['fr'] for x in v['pronunciation_notes']})==len(v['pronunciation_notes'])
   for x in v['pronunciation_notes']: assert x['fr'] in text,(x,text[:80])
  for x in v.values(): walk(x)
walk(p)
d['checks'].update(json_parsed=True,schema_validated=True,plan_metadata_checked=True,local_references_checked=True,answer_structures_checked=True,lexeme_registry_checked=True,introduction_order_checked=True,objective_coverage_checked=True,no_new_material_in_l05=True,transcriptions_structurally_checked=True)
d['verification_summary']=dict(schema='Draft 2020-12; python jsonschema',sessions=5,tasks=task_count,tasks_per_session=[len(s['tasks']) for s in p['sessions']],new_lexemes=len(d['new_lexemes']),external_prerequisites=['A2-U03-L05'],external_reference_status='exists_in_prepared_A2_U03; target_database_status_unverified')
D.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(d['verification_summary'],ensure_ascii=False))

