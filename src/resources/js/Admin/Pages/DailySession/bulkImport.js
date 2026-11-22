// Browser-side planning only. Every package is validated again on the PHP server.
export const packageFileName = file => file.webkitRelativePath || file.name;
const isCompanion = name => /(?:authoring-delta|validation)\.json$/i.test(name) || /(?:^|\/)IMPORT_MANIFEST\.json$/i.test(name);
export async function prepareImport(files, maxBytes = 10 * 1024 * 1024) {
  if (files.length > 2000) throw new Error('Выбрано более 2000 файлов. Выберите отдельную папку уровня.');
  const rows = [];
  for (const file of files) {
    const row = { file, name: packageFileName(file), status: 'pending', sessions: [], prerequisites: [], units: [], levels: [], needsUnits: [], needsLevels: [], dependencies: [], message: '', report: null, receipt: null };
    rows.push(row);
    if (!/\.json$/i.test(file.name) || isCompanion(row.name)) { row.status = 'skipped'; row.message = 'Сопроводительный файл'; continue; }
    if (file.size > maxBytes) { row.status = 'invalid'; row.message = 'Файл превышает допустимый размер пакета'; continue; }
    try {
      const p = JSON.parse((await file.text()).replace(/^\uFEFF/, ''));
      if (!p || typeof p !== 'object' || p.schema_version !== 1 || p.language_pair !== 'fr-ru' || !Array.isArray(p.sessions)) {
        row.status = 'skipped'; row.message = 'Это не пакет занятий'; continue;
      }
      row.sessions = p.sessions.map(s => s.id);
      row.trainingCount = p.sessions.reduce((total, session) => total
        + (session.training_tasks?.recall?.length ?? 0) + (session.training_tasks?.dialogue?.length ?? 0), 0);
      const match = /\.v(\d+)\.json$/i.exec(file.name);
      row.sourceVersion = match ? Number(match[1]) : Math.max(1, ...p.sessions.map(s => s.version ?? 1));
      if (!Number.isSafeInteger(row.sourceVersion) || row.sourceVersion < 1) throw new Error('Неверный номер версии файла');
      const canonical = value => Array.isArray(value) ? value.map(canonical) : value && typeof value === 'object' ? Object.fromEntries(Object.keys(value).sort().map(key => [key, canonical(value[key])])) : value;
      row.contentKey = JSON.stringify(canonical(p));
      row.identity = p.sessions.length ? row.sessions.map(id => 'session:' + id) : [...(p.units ?? []).map(u => 'unit:' + u.id), ...(p.levels ?? []).map(l => 'level:' + l.code), ...(p.curricula ?? []).map(c => 'curriculum:' + c.id)];
      row.identity = [...new Set(row.identity)].sort();
      if (row.sessions.some(id => typeof id !== 'string')) throw new Error('У занятия отсутствует ID');
      row.prerequisites = [...p.sessions.flatMap(s => [...(s.prerequisite_ids ?? []), ...(s.public_page?.related_session_ids ?? [])]), ...(p.curricula ?? []).flatMap(c => c.checkpoint_session_ids ?? [])];
      row.units = (p.units ?? []).map(u => u.id);
      row.levels = (p.levels ?? []).map(l => l.code);
      row.needsUnits = p.sessions.map(s => s.unit_id);
      row.needsLevels = [...p.sessions, ...(p.units ?? []), ...(p.curricula ?? [])].map(s => s.level);
    } catch (error) { row.status = 'invalid'; row.message = 'Ошибка JSON: ' + error.message; }
  }
  // Select the highest source revision for each exact set of stable IDs.
  const groups = new Map();
  for (const row of rows.filter(r => r.status === 'pending' && r.identity.length)) {
    const key = JSON.stringify(row.identity);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(row);
  }
  for (const group of groups.values()) {
    const max = Math.max(...group.map(r => r.sourceVersion));
    const latest = group.filter(r => r.sourceVersion === max).sort((a,b) => a.name.localeCompare(b.name));
    const winner = latest[0];
    const conflict = latest.some(r => r.contentKey !== winner.contentKey);
    for (const row of group) {
      if (row.sourceVersion < max) { row.status = 'skipped'; row.message = 'Есть более новая версия v' + max + ': ' + winner.name; }
      else if (conflict) { row.status = 'invalid'; row.message = 'Разное содержимое при одинаковой максимальной версии v' + max + '. Оставьте один актуальный файл.'; }
      else if (row !== winner) { row.status = 'skipped'; row.message = 'Идентичная копия ' + winner.name; }
    }
  }
  // A complete package supersedes old fragments only if its revision is at least as high.
  const candidates = rows.filter(r => ['pending','invalid'].includes(r.status) && r.identity?.length);
  for (const row of candidates.filter(r => r.status === 'pending')) {
    const complete = candidates.find(other => other !== row && other.identity.length > row.identity.length && other.sourceVersion >= row.sourceVersion && row.identity.every(id => other.identity.includes(id)));
    if (complete) { row.status = 'skipped'; row.message = 'Занятия входят в полный пакет ' + complete.name; }
  }
  const providers = new Map(), units = new Map(), levels = new Map();
  for (const row of rows.filter(r => r.status === 'pending')) {
    for (const id of row.sessions) {
      const previous = providers.get(id);
      if (previous) { previous.status = row.status = 'invalid'; previous.message = row.message = 'Повтор ID ' + id + ' в выбранных пакетах. Оставьте один файл для каждого занятия.'; }
      else providers.set(id, row);
    }
    for (const id of row.units) if (!units.has(id)) units.set(id, row);
    for (const code of row.levels) if (!levels.has(code)) levels.set(code, row);
  }
  for (const row of rows) row.dependencies = [...new Set([
    ...row.prerequisites.map(id => providers.get(id)), ...row.needsUnits.map(id => units.get(id)), ...row.needsLevels.map(id => levels.get(id)),
  ].filter(p => p && p !== row))];
  const ordered = [], remaining = rows.filter(r => r.status === 'pending');
  while (remaining.length) {
    const index = remaining.findIndex(r => r.dependencies.every(d => !remaining.includes(d)));
    if (index === -1) { for (const row of remaining) { row.status = 'invalid'; row.message = 'Цикл зависимостей между пакетами. Проверьте ссылки на занятия, темы и уровни.'; } break; }
    ordered.push(...remaining.splice(index, 1));
  }
  return [...ordered, ...rows.filter(r => !ordered.includes(r))];
}

// Stops between requests. Already committed files are never resent on Continue.
export async function importPrepared(rows, { send, paused = () => false, onRateLimit = async () => {}, onFatal = () => {} }) {
  for (const row of rows) {
    if (paused()) break;
    if (row.status !== 'pending') continue;
    const blocked = row.dependencies.find(d => d.status !== 'done');
    if (blocked) { row.status = 'error'; row.message = 'Сначала исправьте пакет ' + blocked.name; continue; }
    row.status = 'uploading'; row.message = 'Проверка и импорт'; row.report = null;
    try {
      row.receipt = await send(row.file);
      row.status = 'done'; row.message = 'Добавлено: ' + row.receipt.created_version_ids.length + ', обновлено: ' + (row.receipt.updated_version_ids?.length ?? 0);
    } catch (error) {
      const status = error.response?.status;
      if (status === 429) {
        row.status = 'pending'; row.message = 'Ожидание ограничения частоты запросов';
        await onRateLimit(error);
        if (paused()) break;
        return importPrepared(rows, { send, paused, onRateLimit, onFatal });
      }
      row.status = 'error'; row.report = error.response?.data && typeof error.response.data === 'object' ? error.response.data : { message: status === 413 ? 'Размер файла превышает ограничение PHP-хостинга.' : error.message || 'Ошибка сети' }; row.message = row.report.message || 'Пакет не прошёл проверку';
      if (!status || status >= 500 || [401, 403, 419, 413].includes(status)) { onFatal(error); break; }
    }
  }
}
