import test from 'node:test';
import assert from 'node:assert/strict';
import { activePracticeSection, sessionStatuses } from '../../resources/js/Admin/Pages/DailySession/editorUi.js';

test('admin navigation follows full section boundaries and ignores filters and query strings', () => {
  const base = '/admin/daily-practice';
  for (const path of [base, base + '?status=draft', base + '/versions/10', base + '/versions/10/preview?frame=1']) assert.equal(activePracticeSection(path), base);
  for (const section of ['catalog', 'reports']) {
    for (const suffix of ['', '/', '?page=2', '/15']) assert.equal(activePracticeSection(base + '/' + section + suffix), base + '/' + section);
  }
  assert.equal(activePracticeSection(base + '/media-other'), base);
  assert.deepEqual(sessionStatuses.map(status => status.value), ['draft', 'reviewed', 'published', 'archived']);
});
