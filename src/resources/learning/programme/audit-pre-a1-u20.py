import json,hashlib
from pathlib import Path
from collections import defaultdict,Counter
import jsonschema
R=Path('/home/ksv180384/projects/afr');OUT=R/'src/resources/learning/programme/packages/PRE_A1';KIT=R/'plans/daily-practice-authoring'
level=json.loads((KIT/'levels/PRE_A1.plan.json').read_text())['level'];schema=json.loads((KIT/'reference/session-package-v1.schema.json').read_text());validator=jsonschema.Draft202012Validator(schema)
objectives={o['id']:o for u in level['units'] for o in u['objectives']};evidence=defaultdict(list);seen=set();tasks={};packages=[];checkpoints=[];key_ids=defaultdict(set);id_keys=defaultdict(set);delta_checks=[]
for u in level['units']:
 n=u['order'];f=KIT/'examples/PRE_A1-U01.v2.json' if n==1 else OUT/f"{u['id']}.v1.json"
 p=json.loads(f.read_text());errors=list(validator.iter_errors(p));assert not errors,(f,[e.message for e in errors[:3]])
 assert len(p['units'])==1 and len(p['sessions'])==5
 for k in ['id','slug','title','order']:assert p['units'][0][k]==u[k],(u['id'],k)
 assert [s['id'] for s in p['sessions']]==[s['id'] for s in u['sessions']]
 for s,expected in zip(p['sessions'],u['sessions']):
  for k in ['id','title','kind','order','prerequisite_ids']:assert s[k]==expected[k],(s['id'],k)
  assert s['unit_id']==u['id'] and s['level']=='PRE_A1' and s['id'] not in seen
  assert set(s['prerequisite_ids'])<=seen,(s['id'],s['prerequisite_ids'])
  seen.add(s['id']);assert 6<=len(s['tasks'])<=8
  local={o['id']:o for o in s['objectives']};vs={w['id'] for w in s['vocabulary']}
  for o in s['objectives']:assert o==objectives[o['id']],(s['id'],o['id'])
  for w in s['vocabulary']:
   key_ids[w['lexeme_key']].add(w['id']);id_keys[w['id']].add(w['lexeme_key'])
   assert len(w['review_items'])==2 and all(t['type']=='single_choice' for t in w['review_items'])
  for t in s['tasks']:
   assert t['type'] in ['single_choice','matching_pairs','fill_blank','word_order']
   if t['type']=='fill_blank':assert t['input_mode']=='choice'
   assert set(t['objective_ids'])<=set(local) and set(t['vocabulary_ids'])<=vs
   assert set(t['assessed_vocabulary_ids'])<=set(t['vocabulary_ids'])
   k=(s['id'],t['id']);assert k not in tasks;tasks[k]=t
   for o in t['objective_ids']:evidence[o].append(dict(session_id=s['id'],task_id=t['id'],skill=t['skill']))
  if s['kind']=='checkpoint':
   c=Counter(t['skill'] for t in s['tasks']);assert len(s['tasks'])==8
   assert c['listening']==c['reading']==c['vocabulary']==2 and c['grammar']+c['sentence_building']==2,(s['id'],c)
   checkpoints.append(s['id'])
 if n>1:
  df=OUT/f"{u['id']}.authoring-delta.json";d=json.loads(df.read_text());assert d['unit_id']==u['id']
  count=0
  for entry in d['objective_coverage']:
   for e in entry['evidence']:
    t=tasks[(e['session_id'],e['task_id'])]
    assert entry['objective_id'] in t['objective_ids'] and e['skill']==t['skill'];count+=1
  assert {o['id'] for o in u['objectives']}<={x['objective_id'] for x in d['objective_coverage']}
  delta_checks.append(dict(unit_id=u['id'],file=str(df.relative_to(R)),evidence_entries_checked=count))
 packages.append(dict(unit_id=u['id'],file=str(f.relative_to(R)),sha256=hashlib.sha256(f.read_bytes()).hexdigest(),sessions=len(p['sessions']),tasks=sum(len(s['tasks']) for s in p['sessions']),source_status='supplied_prepared_baseline' if n==1 else 'prepared_authoring_package'))
assert len(packages)==20 and len(seen)==100
assert checkpoints==[f'PRE_A1-U{n:02}-L05' for n in [5,10,15,20]]
assert all(len(ids)==1 for ids in key_ids.values()),{k:sorted(v) for k,v in key_ids.items() if len(v)>1}
assert all(len(keys)==1 for keys in id_keys.values()),{k:sorted(v) for k,v in id_keys.items() if len(v)>1}
coverage=[]
for o in objectives:
 e=evidence[o];ss={x['session_id'] for x in e};sk={x['skill'] for x in e}
 assert len(e)>=2 and len(ss)>=2 and len(sk)>=2 and 'vocabulary' in sk,(o,len(e),len(ss),sk)
 coverage.append(dict(objective_id=o,title=objectives[o]['title'],task_count=len(e),session_count=len(ss),skills=sorted(sk),formal_policy_passed=True,evidence=e))
report=dict(level='PRE_A1',status='prepared_inventory_and_formal_coverage_passed',publication_status='not_checked',user_acceptance='not_confirmed',units=20,sessions=100,tasks=len(tasks),mandatory_objectives=len(objectives),checkpoint_session_ids=checkpoints,packages=packages,authoring_deltas_checked=delta_checks,baseline_delta_note='The supplied U01 v2 package has no authoring-delta in the kit; its coverage was checked directly from session tasks.',objective_coverage=coverage,lexeme_key_to_id_binding_stable=True,local_id_to_lexeme_key_binding_stable=True,unique_lexeme_keys=len(key_ids),checks=dict(all_packages_schema_valid=True,plan_session_inventory_checked=True,all_dependencies_resolve_in_prepared_inventory=True,task_counts_and_choice_input_checked=True,review_items_single_choice_checked=True,checkpoint_skill_balance_checked=True,formal_objective_coverage_checked=True,authoring_delta_evidence_crosschecked=True),not_performed=dict(semantic_editorial_review=True,browser_audio_listening=True,site_preview=True,duration_measurement=True,import_or_publication=True),limitations=['Formal coverage checks verify references, counts and skills; a human must assess the substantive quality of the evidence.','Prepared packages are not proof of publication or learner mastery.','Old dictionary qualifiers noted in authoring deltas remain for separate editorial review.'])
target=OUT/'PRE_A1-U20.level-audit.json';target.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(dict(units=20,sessions=100,tasks=len(tasks),mandatory_objectives=len(objectives),deltas_checked=len(delta_checks),checkpoints=checkpoints,formal_coverage_passed=True,lexeme_bindings_stable=True),ensure_ascii=False))
