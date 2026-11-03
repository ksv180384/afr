export const emptyAnswer = (task) => {
  if (task.type === 'single_choice' || (task.type === 'fill_blank' && task.input_mode === 'choice')) return { choice_id: '' };
  if (task.type === 'matching_pairs') return { pairs: [] };
  if (task.type === 'word_order') return { token_ids: [] };
  return { value: '' };
};

export const answerComplete = (task, answer) => {
  if (!task || !answer) return false;
  if ('choice_id' in answer) return (task.choices ?? []).some(({ id }) => id === answer.choice_id);
  if (task.type === 'matching_pairs') return answer.pairs?.length === task.left_items.length;
  if (task.type === 'word_order') return answer.token_ids?.length === task.tokens.length;
  return typeof answer.value === 'string' && answer.value.trim().length > 0;
};

export const introductionPages = (introductions, vocabulary) => introductions.flatMap((intro) => {
  const words = (intro.vocabulary_ids ?? []).map((id) => vocabulary.find((word) => word.id === id)).filter(Boolean);
  const chunks = words.length ? Array.from({ length: Math.ceil(words.length / 4) }, (_, index) => words.slice(index * 4, index * 4 + 4)) : [[]];
  return chunks.map((items, index) => ({ ...intro, words: items, showText: index === 0 }));
});

export const insertedValue = (value, character, start, end = start) => ({
  value: value.slice(0, start) + character + value.slice(end), caret: start + character.length,
});
