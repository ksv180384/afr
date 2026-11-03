import test from 'node:test';
import assert from 'node:assert/strict';
import { h, Fragment } from 'vue';
import { selectOptions, filterSelectOptions } from '../../resources/js/App/Components/Form/Select/selectOptions.js';
import { timezoneLabel } from '../../resources/js/App/Learning/practiceTime.js';

test('select options include fragments, retain numeric values and reflect disabled choices',()=>{
  const options=selectOptions([h('option',{value:0,label:'Ноль'}),h(Fragment,null,[h('option',{value:'a',label:'Первый'}),h('option',{value:'b',label:'Второй',disabled:true}),h('option',{value:'c',label:'Третий',disabled:null})])]);
  assert.deepEqual(options.map(option=>option.value),[0,'a','b','c']);
  assert.equal(options[2].disabled,true);
  assert.equal(options[3].disabled,false);
});
test('search matches city, timezone identifier and UTC offset without changing option values',()=>{
  const date=new Date('2026-01-01T12:00:00Z');
  const options=['Europe/Moscow','Europe/Paris','America/New_York','Asia/Kathmandu'].map(value=>({value,label:timezoneLabel(value,date)}));
  assert.equal(filterSelectOptions(options,'москва')[0].value,'Europe/Moscow');
  assert.equal(filterSelectOptions(options,'europe PARIS')[0].value,'Europe/Paris');
  assert.equal(filterSelectOptions(options,'UTC-5')[0].value,'America/New_York');
  assert.equal(filterSelectOptions(options,'UTC+5:45')[0].value,'Asia/Kathmandu');
  assert.equal(filterSelectOptions(options,'неизвестный город').length,0);
});
test('timezone offsets include negative, fractional and seasonal differences',()=>{
  const winter=new Date('2026-01-01T12:00:00Z');
  const summer=new Date('2026-07-01T12:00:00Z');
  assert.match(timezoneLabel('Europe/Moscow',winter),/UTC\+3/);
  assert.match(timezoneLabel('America/New_York',winter),/UTC-5/);
  assert.match(timezoneLabel('America/New_York',summer),/UTC-4/);
  assert.match(timezoneLabel('Asia/Kathmandu',winter),/UTC\+5:45/);
  assert.match(timezoneLabel('UTC',winter),/UTC\+0/);
});
