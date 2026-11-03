import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { PRACTICE_CONTRACT, LEVELS, nextLevel, checkingPolicy } from '../../resources/js/App/Learning/types/index.js';
import { guestTimezone, localDate } from '../../resources/js/App/Learning/types/time.js';

test('client contract is immutable and C2 has no next level', () => {
  assert.equal(LEVELS[0].title, 'С нуля');
  assert.equal(nextLevel('PRE_A1'), 'A1');
  assert.equal(nextLevel('C2'), null);
  assert.throws(() => nextLevel('A0'), RangeError);
  assert.throws(() => { LEVELS[0].code = 'A0'; }, TypeError);
  assert.deepEqual(PRACTICE_CONTRACT.default_profile, { current_level: 'PRE_A1' });
});

test('pinned policies reject unknown versions and stay immutable', () => {
  assert.equal(checkingPolicy(1).completed_results_immutable, true);
  assert.throws(() => checkingPolicy(2), RangeError);
  assert.throws(() => checkingPolicy('1'), RangeError);
  assert.throws(() => checkingPolicy(), RangeError);
  assert.throws(() => { checkingPolicy(1).preserve_diacritics = false; }, TypeError);
});

test('guest detects timezone lazily and uses the Moscow fallback', () => {
  assert.equal(typeof window, 'undefined');
  assert.equal(guestTimezone(() => 'Europe/Moscow').usedFallback, false);
  for (const detect of [
    () => undefined,
    () => '',
    () => '+03:00',
    () => 'Mars/Olympus',
    () => { throw new Error('Intl unavailable'); },
  ]) {
    const result = guestTimezone(detect);
    assert.equal(result.timezone, 'Europe/Moscow');
    assert.equal(result.usedFallback, true);
    assert.match(result.message, /Europe\/Moscow/);
  }
});

test('PHP and JS use the same midnight, DST and year-boundary examples', () => {
  const vectors = JSON.parse(readFileSync(new URL('../Fixtures/daily-practice-time.json', import.meta.url), 'utf8'));
  for (const vector of vectors) {
    const instant = new Date(vector.instant);
    assert.equal(localDate(instant, vector.timezone), vector.date);
    assert.equal(instant.toISOString(), new Date(vector.instant).toISOString());
  }
  assert.throws(() => localDate(new Date('invalid'), 'UTC'), RangeError);
  assert.throws(() => localDate(new Date(), '+03:00'), RangeError);
  assert.throws(() => localDate(new Date(), 'Mars/Olympus'), RangeError);
});
