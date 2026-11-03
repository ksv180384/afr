import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root=path.dirname(fileURLToPath(import.meta.url));
const levels=['PRE_A1','A1','A2','B1','B2','C1','C2'];
const titles=['С нуля','A1','A2','B1','B2','C1','C2'];
const summaries=[
 'Готовые реплики и короткие ситуации без предварительного знания языка.',
 'Повседневные потребности: распорядок, дом, покупки, встречи и простые сообщения.',
 'Знакомые ситуации, прошлые события, планы, услуги и связные короткие сообщения.',
 'Связное общение, опыт, объяснение мнения и решение знакомых проблем.',
 'Аргументированная позиция, переговоры, критическое чтение и выбор регистра.',
 'Гибкая точная речь, синтез источников, сложный дискурс и прагматические оттенки.',
 'Смысловые нюансы, риторика, подтекст и адаптация сложного высказывания к аудитории.',
];
const lines=(name)=>readFileSync(path.join(root,name),'utf8').trim().split(/\r?\n/).filter(Boolean);
const topics=lines('topics.tsv').map(line=>line.split('|'));
const lexicon=lines('lexicon.tsv').map(line=>line.split('|'));
if(topics.length!==140 || lexicon.length!==140) throw new Error('Exactly 140 authored topic rows and lexical plans are required');
const result={plan_revision:2,created_on:'2026-10-28',updated_on:'2026-10-31',status:'authoring_plan',target_sessions:700,audio_policy:'browser_fr_speech_synthesis',
 sources:['plans/MASTER_PLAN_DAILY_PRACTICE.md#stage-17','https://www.coe.int/en/web/common-european-framework-reference-languages/cefr-descriptors'],
 notice:'План не является импортируемым пакетом или доказательством редакторской готовности. Французское ядро здесь — ориентир будущего словаря, без подмены полной транскрипции и контекста.',levels:[]};
for(const [li,code] of levels.entries()) {
 const rows=topics.filter(row=>row[0]===code);
 const lexical=lexicon.filter(row=>row[0]===code);
 if(rows.length!==20 || lexical.length!==20) throw new Error('Each level requires exactly 20 topics: '+code);
 const level={code,title:titles[li],summary:summaries[li],target_sessions:100,units:[],checkpoint_session_ids:[],required_objective_ids:[]};
 for(const [ui,row] of rows.entries()) {
  const id=code+'-U'+String(ui+1).padStart(2,'0');
  const focus=row[3].split(';');
  if(focus.length!==5) throw new Error('Each topic requires five distinct session focuses: '+id);
  const goals=[{id:code+'.U'+String(ui+1).padStart(2,'0')+'.UNDERSTAND',title:'Понимать: '+focus[0].toLowerCase()+'; '+focus[1].toLowerCase()},
   {id:code+'.U'+String(ui+1).padStart(2,'0')+'.APPLY',title:'Выбирать и строить реплику: '+focus[2].toLowerCase()+'; '+focus[3].toLowerCase()}];
  const unit={id,slug:row[1],title:row[2],order:ui+1,package_path:'packages/'+code+'/'+id+(id==='PRE_A1-U01'?'.v2.json':'.v1.json'),objectives:goals,
   vocabulary_plan:lexical[ui][1].split(';').map(item=>{const [fr,meaning_ru]=item.split('~');if(!fr||!meaning_ru)throw new Error('Lexical focus needs a meaning: '+id);return {fr,meaning_ru,status:'planned_context_required'};}),sessions:[]};
  for(let si=0;si<5;si++) {
   const sid=id+'-L'+String(si+1).padStart(2,'0');
   const checkpoint=si===4 && [5,10,15,20].includes(ui+1);
   const before=si>0?id+'-L'+String(si).padStart(2,'0'):ui>0?code+'-U'+String(ui).padStart(2,'0')+'-L05':null;
   unit.sessions.push({id:sid,title:focus[si],order:si+1,kind:checkpoint?'checkpoint':'practice',prerequisite_ids:before?[before]:[],
    objective_ids:goals.map(g=>g.id),planned_task_count:6,skills:['vocabulary','reading','grammar','sentence_building','listening'],status:'planned'});
   if(checkpoint) level.checkpoint_session_ids.push(sid);
  }
  const packageFile=path.join(root,unit.package_path);
  if(existsSync(packageFile)) {
   const authored=JSON.parse(readFileSync(packageFile,'utf8')).sessions.filter(s=>s.unit_id===id);
   const actualGoals=new Map(authored.flatMap(s=>s.objectives).map(g=>[g.id,g]));
   if(authored.length) unit.objectives=[...actualGoals.values()];
   for(const planned of unit.sessions) {
    const current=authored.find(s=>s.id===planned.id);
    if(current) { planned.title=current.title; planned.objective_ids=current.objectives.map(g=>g.id); }
   }
  }
  level.required_objective_ids.push(...unit.objectives.map(g=>g.id));
  level.units.push(unit);
 }
 result.levels.push(level);
}
writeFileSync(path.join(root,'map.v1.json'),JSON.stringify(result,null,2)+'\n');
console.log('Authoring map: 7 levels, 140 topics, 700 planned session slots, 28 checkpoints; no lessons were generated or published.');