import { completedGuestSessions } from './learningAccess.js';

export const trainingModes = [
  { id: 'recall', label: 'Выражения', title: 'Отработка выражений', icon: 'book', description: 'Тренируйтесь понимать слова и фразы из этой темы на слух и в тексте.' },
  { id: 'dialogue', label: 'Диалоги', title: 'Что сказать?', icon: 'message', description: 'Подбирайте реплики и собирайте ответы в новых ситуациях.' },
];

export const canTrainTopic = topic => ['available', 'completed'].includes(topic?.status);

export function trainingSession(mode, items) {
  const vocabulary = new Map();
  const tasks = [];
  const audioUrls = {};
  for (const {source, task: original, vocabulary: words} of items) {
    const qualify = id => source + '/' + id;
    const task = structuredClone(original);
    task.id = qualify(task.id);
    task.vocabulary_ids = task.vocabulary_ids.map(qualify);
    task.assessed_vocabulary_ids = task.assessed_vocabulary_ids.map(qualify);
    for (const pair of task.correct_pairs ?? []) pair.assessed_vocabulary_ids = pair.assessed_vocabulary_ids.map(qualify);
    for (const word of words) vocabulary.set(qualify(word.id), {...word, id: qualify(word.id)});
    const asset = task.prompt?.audio?.asset_id;
    if (asset) audioUrls[asset] = '/daily/api/content/' + encodeURIComponent(source) + '/media/' + encodeURIComponent(asset);
    tasks.push(task);
  }
  return {id: mode.id, title: mode.title, description: mode.description, level: items[0]?.level,
    tasks, vocabulary: [...vocabulary.values()], audioUrls};
}

export function guestTrainingProgress(state) {
  const completed = [...new Set(completedGuestSessions(state))];
  const recent = Object.values(state?.runs ?? {}).filter(run =>
    ['daily', 'extra'].includes(run.kind) && run.snapshot?.level === state.current_level)
    .sort((a, b) => (b.started_at ?? '').localeCompare(a.started_at ?? ''))[0];
  const seen = new Set();
  if (recent && !completed.includes(recent.snapshot.id)) {
    for (const [index, step] of (recent.presentations ?? []).entries()) {
      const task = recent.snapshot.tasks[index];
      if (step.first_result) for (const id of task?.vocabulary_ids ?? []) seen.add(id);
      if (recent.introductions_seen?.includes(step.presentation_id)) {
        for (const intro of recent.snapshot.introductions ?? []) {
          if (intro.before_task_id === task?.id) for (const id of intro.vocabulary_ids ?? []) seen.add(id);
        }
      }
    }
  }
  return {completed, current_level: state?.current_level, current: recent?.snapshot.id, seen: [...seen]};
}
