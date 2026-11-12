import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { readFile, readdir } from 'node:fs/promises';
import { prepareImport, importPrepared } from '../../resources/js/Admin/Pages/DailySession/bulkImport.js';
const file = (name, data) => ({ name, size: 1, text: async () => typeof data === 'string' ? data : JSON.stringify(data) });
const pkg = (id, dependencies = []) => ({ schema_version: 1, language_pair: 'fr-ru', sessions: [{ id, prerequisite_ids: dependencies }] });
const receipt = { created_version_ids: [1], updated_version_ids: [] };
test('skips authoring, validation and other companion files without uploading them', async () => {
 const rows = await prepareImport([file('C1-U01.authoring-delta.json', '{}'), file('C1-U01.validation.json', '{}'), file('IMPORT_MANIFEST.json', {packages:['lesson.v1.json']}), file('README.md', ''), file('other.json', {}), file('lesson.v1.json', pkg('one'))]);
 assert.equal(rows.filter(r => r.status === 'skipped').length, 5);
 assert.equal(rows.filter(r => r.status === 'pending').length, 1);
});
test('orders prerequisites before dependent packages regardless of filename order', async () => {
 const rows = await prepareImport([file('second.json', pkg('two', ['one'])), file('first.json', pkg('one'))]);
 assert.deepEqual(rows.map(r => r.sessions[0]), ['one', 'two']);
});
test('rejects ambiguous duplicates, cycles and malformed JSON before sending', async () => {
 const rows = await prepareImport([file('first.json', pkg('one')), file('copy.json', {...pkg('one'), package_id:'different'}), file('a.json', pkg('a', ['b'])), file('b.json', pkg('b', ['a'])), file('bad.json', '{')]);
 assert.ok(rows.every(r => r.status === 'invalid'));
});
test('imports one file at a time and resumes after pause without resending successes', async () => {
 const rows = await prepareImport([file('one.json', pkg('one')), file('two.json', pkg('two'))]);
 let pause = false; const sent = [];
 const send = async f => { sent.push(f.name); pause = true; return receipt; };
 await importPrepared(rows, { send, paused: () => pause });
 assert.deepEqual(sent, ['one.json']);
 pause = false;
 await importPrepared(rows, { send, paused: () => pause });
 assert.deepEqual(sent, ['one.json', 'two.json']);
});
test('keeps failed dependencies blocked but imports unrelated packages and permits retry', async () => {
 const rows = await prepareImport([file('two.json', pkg('two', ['one'])), file('one.json', pkg('one')), file('other.json', pkg('other'))]);
 const send = async f => { if (f.name === 'one.json') throw { response: { status: 422, data: { message: 'Bad lesson' } } }; return receipt; };
 await importPrepared(rows, { send });
 assert.equal(rows.find(r => r.sessions[0] === 'one').status, 'error');
 assert.equal(rows.find(r => r.sessions[0] === 'two').status, 'error');
 assert.equal(rows.find(r => r.sessions[0] === 'other').status, 'done');
 for(const row of rows) if(row.status === 'error') row.status = 'pending';
 const sent=[]; await importPrepared(rows, { send: async f => {sent.push(f.name); return receipt;} });
 assert.deepEqual(sent, ['one.json','two.json']);
});
test('honours rate limiting before trying the same pending file again', async () => {
 const rows=await prepareImport([file('one.json',pkg('one'))]);const steps=[];let first=true;
 await importPrepared(rows,{ send:async()=>{steps.push('send');if(first){first=false;throw {response:{status:429}};}return receipt;},onRateLimit:async()=>steps.push('wait') });
 assert.deepEqual(steps,['send','wait','send']);assert.equal(rows[0].status,'done');
});
test('stops on server or network failure instead of firing requests for every file', async () => {
 const rows=await prepareImport([file('one.json',pkg('one')),file('two.json',pkg('two'))]);let calls=0,fatal=false;
 await importPrepared(rows,{send:async()=>{calls++;throw new Error('offline');},onFatal:()=>{fatal=true;}});
 assert.equal(calls,1);assert.ok(fatal);assert.equal(rows[1].status,'pending');
});
test('plans all actual programme packages and ignores all companion files', async t => {
 if (!existsSync(new URL('../../../learning/programme/packages/', import.meta.url))) return t.skip('Local learning bank is not installed');
 const root=new URL('../../../learning/programme/packages/',import.meta.url);const files=[];
 async function walk(dir){for(const item of await readdir(dir,{withFileTypes:true})){const url=new URL(item.name+(item.isDirectory()?'/':''),dir);if(item.isDirectory())await walk(url);else files.push({name:item.name,webkitRelativePath:decodeURIComponent(url.pathname.slice(root.pathname.length)),size:1,text:()=>readFile(url,'utf8')});}}
 await walk(root);const rows=await prepareImport(files);assert.equal(rows.filter(r=>r.status==='pending').length,140);assert.equal(rows.filter(r=>['error','invalid'].includes(r.status)).length,0);assert.equal(rows.filter(r=>r.status==='pending').reduce((n,r)=>n+r.sessions.length,0),700);
 const seen=new Set();for(const row of rows.filter(r=>r.status==='pending')){for(const dependency of row.dependencies)assert.ok(seen.has(dependency));seen.add(row);}
});


test('chooses numeric maximum version independent of filenames ordering or manifest', async () => {
 const rows=await prepareImport([file('IMPORT_MANIFEST.json','not valid json'),file('unit.v2.json',pkg('one')),file('unit.v10.json',pkg('one')),file('unit.v1.json',pkg('one'))]);
 assert.deepEqual(rows.filter(r=>r.status==='pending').map(r=>r.name),['unit.v10.json']);
 assert.equal(rows.filter(r=>r.status==='skipped').length,3);
});
test('deduplicates identical highest-version copies but rejects conflicting highest versions', async () => {
 const identical=await prepareImport([file('a.v2.json',pkg('one')),file('b.v2.json',pkg('one'))]);
 assert.equal(identical.filter(r=>r.status==='pending').length,1);
 assert.equal(identical.filter(r=>r.status==='skipped').length,1);
 const conflict=await prepareImport([file('a.v2.json',pkg('one')),file('b.v2.json',{...pkg('one'),package_id:'different'}),file('old.v1.json',pkg('one'))]);
 assert.equal(conflict.filter(r=>r.status==='pending').length,0);
 assert.equal(conflict.filter(r=>r.status==='invalid').length,2);
});
test('complete package supersedes matching old fragments without loading them twice', async () => {
 const complete={...pkg('one'),sessions:[...pkg('one').sessions,...pkg('two').sessions]};
 const rows=await prepareImport([file('fragment.v1.json',pkg('one')),file('complete.v1.json',complete)]);
 assert.deepEqual(rows.filter(r=>r.status==='pending').map(r=>r.name),['complete.v1.json']);
 const ambiguous=await prepareImport([file('fragment.v2.json',pkg('one')),file('complete.v1.json',complete)]);
 assert.equal(ambiguous.filter(r=>r.status==='pending').length,0);
 assert.ok(ambiguous.every(r=>r.status==='invalid'));
});
test('uses payload version for unnumbered filenames and filename vN when provided', async () => {
 const p={...pkg('one'),sessions:[{...pkg('one').sessions[0],version:3}]};
 const rows=await prepareImport([file('lesson.json',p),file('old.v2.json',p)]);
 assert.deepEqual(rows.filter(r=>r.status==='pending').map(r=>r.name),['lesson.json']);
});
