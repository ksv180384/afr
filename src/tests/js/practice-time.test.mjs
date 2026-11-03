import test from 'node:test';
import assert from 'node:assert/strict';
import { browserTimezone, practiceDate } from '../../resources/js/App/Learning/practiceTime.js';

test('calendar dates keep their original day and use the requested display format',()=>{
  assert.equal(practiceDate('2026-01-01',{timezone:'America/New_York'}).text,'01.01.2026');
  assert.equal(practiceDate('2026-01-01T06:22:48Z',{timezone:'Europe/Moscow',relative:false}).text,'01.01.2026 9:22:48');
});
test('recent timestamps show elapsed hours and minutes with a complete hover date',()=>{
  const now=Date.parse('2026-01-01T07:27:48Z');
  assert.deepEqual(practiceDate('2026-01-01T06:22:48Z',{timezone:'Europe/Moscow',now}),{text:'1 час 5 минут назад',title:'01.01.2026 9:22:48'});
  assert.equal(practiceDate('2026-01-01T07:26:48Z',{now}).text,'1 минуту назад');
  assert.equal(practiceDate('2026-01-01T07:27:47Z',{now}).text,'менее минуты назад');
  assert.equal(practiceDate('2025-12-31T07:27:48Z',{now,timezone:'Europe/Moscow',time:false}).text,'31.12.2025');
});
test('invalid timestamps are empty and unknown timezones use the Moscow fallback',()=>{
  assert.deepEqual(practiceDate('invalid'),{text:'',title:''});
  assert.equal(practiceDate('2026-01-01T06:22:48Z',{timezone:'invalid',relative:false}).text,'01.01.2026 9:22:48');
});
test('missing browser timezone uses Europe/Moscow',()=>{
  const original=Intl.DateTimeFormat;
  try {
    Intl.DateTimeFormat=()=>({resolvedOptions:()=>({})});
    assert.equal(browserTimezone(),'Europe/Moscow');
    Intl.DateTimeFormat=()=>{throw new Error('unavailable');};
    assert.equal(browserTimezone(),'Europe/Moscow');
  } finally { Intl.DateTimeFormat=original; }
});
