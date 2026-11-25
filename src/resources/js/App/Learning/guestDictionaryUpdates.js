// A missing network response never invalidates an offline lesson.
export async function refreshGuestDictionary(repository, transport, local) {
  if (!transport?.dictionaryUpdates || !local || local.bound_user_id != null) return local;
  const sessions = new Map();
  for (const word of Object.values(local.vocabulary)) {
    for (const source of word.sources ?? []) {
      const id = source.session_version_id;
      const session = source.session_id;
      if (Number.isInteger(id) && session && (!sessions.has(session) || !source.checksum)) {
        sessions.set(session, {id, session, checksum:source.checksum ?? null});
      }
    }
  }
  const requested = [...sessions.values()];
  const changes = new Map();
  for (let offset = 0, size = 25; offset < requested.length;) {
    const batch = requested.slice(offset, offset + size);
    try {
      const response = await transport.dictionaryUpdates(batch);
      offset += batch.length;
      for (const session of response.removed) changes.set(session, null);
      for (const lesson of response.updated) changes.set(lesson.session, {
        checksum: lesson.checksum, words: new Map(lesson.vocabulary.map(word => [word.lexeme_key, word])),
      });
    } catch (error) {
      if (error.response?.data?.code === 'response_too_large' && batch.length > 1) {
        size = Math.ceil(batch.length / 2);
        continue;
      }
      break;
    }
  }
  if (!changes.size) return local;
  return repository.mutate(state => {
    if (!state || state.bound_user_id != null || state.guest_id !== local.guest_id) return state;
    for (const [key, word] of Object.entries(state.vocabulary)) {
      const sources = word.sources ?? [];
      word.sources = sources.filter(source => {
        const request = sessions.get(source.session_id);
        if (!changes.has(source.session_id) || request.id !== source.session_version_id) return true;
        const update = changes.get(source.session_id);
        if (source.checksum && request.checksum && source.checksum !== request.checksum) return true;
        const current = update?.words.get(key);
        if (!current) return false;
        source.snapshot = current;
        source.checksum = update.checksum;
        return true;
      });
      if (sources.length && !word.sources.length) delete state.vocabulary[key];
      else if (word.sources.length) word.snapshot = word.sources[0].snapshot;
    }
    for (const run of Object.values(state.runs)) {
      if (!['review', 'vocabulary_test'].includes(run.kind) || !['paused', 'in_progress'].includes(run.status)) continue;
      const unavailable = run.snapshot.review_items.some((item, index) => {
        if (run.presentations[index].first_result) return false;
        const sources = state.vocabulary[item.review_lexeme_key]?.sources ?? [];
        return !sources.some(source => source.snapshot.review_items?.some(task => sameTask(task, item.task)));
      });
      if (unavailable) {
        run.status = 'cancelled';
        run.revision++;
        run.finished_at = new Date().toISOString();
      }
    }
    return state;
  });
}

// Object field order can differ between an imported package and the API.
function sameTask(a, b) {
  if (a === b) return true;
  if (!a || !b || typeof a !== 'object' || typeof b !== 'object' || Array.isArray(a) !== Array.isArray(b)) return false;
  const keys = Object.keys(a);
  return keys.length === Object.keys(b).length && keys.every(key => Object.hasOwn(b, key) && sameTask(a[key], b[key]));
}
