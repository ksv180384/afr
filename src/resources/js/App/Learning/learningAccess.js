// Published route order is shared with LearningAccessService. Completion uses stable session IDs.
export const learningAccess = (outline, completed, profileLevel = 'PRE_A1') => {
  const done = new Set(completed);
  const base = Math.max(0, outline.findIndex(level=>level.id===profileLevel));
  for (const level of outline.slice(0,base)) for (const unit of level.units) for (const session of unit.sessions) done.add(session.id);
  const states = {levels:{},units:{},sessions:{}};
  let prefix = true, reason = '';
  const status = (allowed, completed, reason, path, review_only = false) => ({locked:!allowed,completed,review_only,reason:allowed ? '' : (reason || 'Сначала завершите предыдущий материал.'),path});
  for (const [index,level] of outline.entries()) {
    const lower = index < base;
    if(index===base) { prefix=true; reason=''; }
    const levelGate = (lower || prefix) && level.published;
    const levelIds = [];
    if (!level.published || !level.units.length) {
      prefix = false;
      reason ||= 'Материалы предыдущего уровня ещё готовятся.';
    }
    for (const unit of level.units) {
      const unitGate = (lower || prefix) && level.published, unitReason = reason;
      const ids = unit.sessions.map(s => s.id);
      levelIds.push(...ids);
      if (!ids.length) { prefix = false; reason ||= 'Занятия предыдущей темы ещё готовятся.'; }
      for (const session of unit.sessions) {
        const finished = completed.includes(session.id), missing = session.prerequisite_ids.filter(id => !done.has(id));
        states.sessions[session.id] = status(finished || (lower && level.published) || (prefix && !missing.length),finished,
          reason || (missing.length ? 'Сначала завершите предыдущее занятие '+missing[0]+'.' : ''),session.path,lower);
        if (!finished && !lower) { prefix = false; reason ||= 'Сначала завершите занятие «'+session.title+'» ('+session.id+').'; }
      }
      states.units[unit.id] = status(unitGate || ids.some(id=>completed.includes(id)),!!ids.length && ids.every(id=>completed.includes(id)),unitReason,unit.path,lower);
    }
    const hasLessons = level.published && levelIds.length > 0;
    states.levels[level.id] = status(hasLessons && (levelGate || levelIds.some(id=>completed.includes(id))),!!levelIds.length && level.units.every(unit=>unit.sessions.length) && levelIds.every(id=>completed.includes(id)),hasLessons ? 'Сначала завершите предыдущий уровень.' : 'Занятия ещё не опубликованы.',level.path,lower);
  }
  return states;
};
export const completedGuestSessions = state => Object.values(state?.runs ?? {})
  .filter(run => run.status === 'completed' && ['daily','extra'].includes(run.kind))
  .map(run => run.snapshot.id);
export const decorateLearningCatalog = (catalog, states) => {
  const paths = new Map(Object.values(states).flatMap(group=>Object.values(group)).filter(s=>s.path).map(s=>[s.path,s]));
  const decorate = card => card ? {...card,...(paths.get(card.path) ?? {}),path:card.path} : card;
  return {...catalog,access:paths.get(catalog.path) ?? {locked:false,completed:false,reason:''},
    cards:catalog.cards.map(decorate),related:catalog.related.map(decorate),previous:decorate(catalog.previous),next:decorate(catalog.next)};
};
