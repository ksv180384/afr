import { learningAccess, completedGuestSessions } from './learningAccess.js';
import { guestDate } from './guestProgress.js';
import { guestShortReviewPlan } from './guestVocabulary.js';

function card(entry, access, published = true) {
  let status = 'unavailable';
  if (published && access) {
    status = access.completed ? 'completed' : access.locked ? 'locked' : 'available';
  }
  return {
    id: entry.id,
    title: entry.title ?? null,
    path: entry.path ?? null,
    status,
    reason: status === 'unavailable' ? 'Материалы ещё недоступны.' : access.reason,
  };
}

function routeView(outline, access, profileLevel, selection, candidate, startedLessons) {
  const inferredLevel = selection.unit && outline.find(level => level.units.some(unit => unit.id === selection.unit));
  const levelId = selection.level ?? inferredLevel?.id ?? profileLevel;
  const level = outline.find(entry => entry.id === levelId);
  const units = level?.published ? level.units : [];
  const findUnit = id => id && units.find(unit => unit.sessions.some(session => session.id === id));
  const unitId = selection.unit ?? (findUnit(candidate?.id) || units[0])?.id;
  const unit = units.find(entry => entry.id === unitId);
  const lessonCard = session => {
    const state = access.sessions[session.id];
    return {
      ...card(session, state),
      completed: state?.completed ?? false,
      review_only: state?.review_only ?? false,
      started: startedLessons.has(session.id),
      run_id: null,
    };
  };
  const lessons = (unit?.sessions ?? []).map(lessonCard);
  const topics = units.map(topic => ({
    ...card(topic, access.units[topic.id], topic.sessions.length > 0),
    background_url: topic.background_url ?? null,
    lessons: topic.sessions.map(lessonCard),
  }));
  const contents = outline.map(entry => {
    const publishedUnits = entry.published ? entry.units : [];
    return {
      ...card(entry, access.levels[entry.id], publishedUnits.some(unit => unit.sessions.length)),
      units: publishedUnits.map(unit => ({
        ...card(unit, access.units[unit.id], unit.sessions.length > 0),
        session_count: unit.sessions.length,
      })),
    };
  });
  return {
    level: card(level ?? { id: levelId }, access.levels[levelId], units.some(entry => entry.sessions.length)),
    unit: unitId == null ? null : card(unit ?? { id: unitId }, access.units[unitId], !!unit?.sessions.length),
    lessons,
    topics,
    current_lesson_id: candidate?.id ?? null,
    contents,
  };
}

export function searchGuestRoute(local, catalog, query, completedOnly) {
  const outline = catalog?.access_outline ?? [];
  const access = learningAccess(outline, completedGuestSessions(local), local.current_level);
  const needle = query.trim().toLocaleLowerCase('ru');
  const items = [];
  for (const level of outline) {
    if (!level.published) continue;
    for (const unit of level.units) {
      for (const lesson of unit.sessions) {
        const state = access.sessions[lesson.id];
        if ((completedOnly && !state.completed) || !lesson.title.toLocaleLowerCase('ru').includes(needle)) continue;
        items.push({...card(lesson, state), level: level.id, unit: unit.title});
        if (items.length === 41) return {items: items.slice(0, 40), more: true};
      }
    }
  }
  return {items, more: false};
}

export function activeGuestRun(runs, level, sessionId = null) {
  const ordered = Object.values(runs)
    .filter(run => {
      const lesson = ['daily', 'extra'].includes(run.kind);
      return sessionId === null ? !lesson || run.snapshot.level === level : lesson && run.snapshot.id === sessionId;
    })
    .sort((a, b) => b.started_at.localeCompare(a.started_at) || b.record_sequence - a.record_sequence);
  return ordered.find(run => run.status === 'in_progress') ?? ordered.find(run => run.status === 'paused');
}

export function guestPracticeHome(local, catalog, selection, now, contentError = '') {
  const outline = catalog?.access_outline ?? [];
  const access = learningAccess(outline, completedGuestSessions(local), local.current_level);
  const sessions = catalog?.sessions ?? [];
  const next = sessions.find(session => {
    const state = access.sessions[session.id];
    return state && !state.locked && !state.review_only && !state.completed;
  });
  const candidate = next ? {
    id: next.id, title: next.title, level: next.level, theme: next.theme,
    duration_minutes: next.duration_minutes, goal: next.goal ?? null,
  } : null;
  const runs = Object.values(local.runs);
  const startedLessons = new Set(runs
    .filter(run => ['daily', 'extra'].includes(run.kind) && ['in_progress', 'paused'].includes(run.status))
    .map(run => run.snapshot.id));
  const vocabularyRuns = runs.filter(run => ['review', 'vocabulary_test'].includes(run.kind));
  const current = activeGuestRun(vocabularyRuns, local.current_level);
  const sessionId = current && ['daily', 'extra'].includes(current.kind) ? current.snapshot.id : null;
  const active = current ? {
    id: current.id,
    title: current.snapshot.title,
    goal: current.snapshot.objectives?.[0]?.title ?? null,
    level: current.snapshot.level,
    status: current.status,
    kind: current.kind,
    session_id: sessionId,
    duration_minutes: current.snapshot.duration_minutes,
    theme: sessions.find(session => session.id === sessionId)?.theme ?? null,
  } : null;
  const date = guestDate(now, local.timezone);
  const review = guestShortReviewPlan(local, now, 'suggested');
  const { selection: ignored, ...suggestion } = review;
  return {
    profile: { current_level: local.current_level, timezone: local.timezone, revision: local.profile_revision },
    active_run: active,
    vocabulary_runs: {
      review: activeGuestRun(vocabularyRuns.filter(run => run.kind === 'review'), local.current_level)?.id ?? null,
      vocabulary_test: activeGuestRun(vocabularyRuns.filter(run => run.kind === 'vocabulary_test'), local.current_level)?.id ?? null,
    },
    run: active,
    candidate,
    route: routeView(outline, access, local.current_level, selection, candidate, startedLessons),
    review_suggestion: review.total ? suggestion : null,
    exhausted: !candidate,
    content_error: contentError,
    activity_today: runs.some(run => run.finished_at && guestDate(run.finished_at, run.timezone) === date),
    local_date: date,
    word_count: Object.keys(local.vocabulary).length,
  };
}
