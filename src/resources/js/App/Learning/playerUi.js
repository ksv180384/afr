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

export const reportAnswerText = (task, answer) => {
  if (!task || !answer) return '';
  const textFor = (items, id) => items?.find(item => item.id === id)?.text ?? '';
  if (typeof answer.value === 'string') return answer.value.trim() ? answer.value : '';
  if ('choice_id' in answer) return textFor(task.choices, answer.choice_id);
  if (Array.isArray(answer.token_ids)) {
    const words = answer.token_ids.map(id => textFor(task.tokens, id));
    return words.every(Boolean) ? words.join(' ') : '';
  }
  if (Array.isArray(answer.pairs)) {
    const pairs = answer.pairs.map(pair => {
      const left = textFor(task.left_items, pair?.left_id);
      const right = textFor(task.right_items, pair?.right_id);
      return left && right ? `${left} ↔ ${right}` : '';
    });
    return pairs.every(Boolean) ? pairs.join('\n') : '';
  }
  return '';
};

export const introductionPages = (introductions, vocabulary) => introductions.flatMap((intro) => {
  const words = (intro.vocabulary_ids ?? []).map((id) => vocabulary.find((word) => word.id === id)).filter(Boolean);
  const chunks = words.length ? Array.from({ length: Math.ceil(words.length / 4) }, (_, index) => words.slice(index * 4, index * 4 + 4)) : [[]];
  return chunks.map((items, index) => ({ ...intro, words: items, showText: index === 0 }));
});

export const insertedValue = (value, character, start, end = start) => ({
  value: value.slice(0, start) + character + value.slice(end), caret: start + character.length,
});

export function questionSupport(level, task, {graded = false, revealed = false, promptVisible = true, hasNotes = false} = {}) {
  const gradual = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].includes(level);
  const showAll = graded || revealed || !gradual;
  const prompt = showAll || level === 'A1';
  const choices = showAll;
  const hasPrompt = promptVisible && (hasNotes ||
    (task?.prompt?.text_lang === 'fr' && !!task.prompt.transcription_ru));
  const hasChoices = !!task?.text_before_transcription_ru || !!task?.text_after_transcription_ru ||
    ['choices', 'tokens', 'left_items', 'right_items'].some(key =>
      task?.[key]?.some(item => (item.text_lang ?? 'fr') === 'fr' && !!item.transcription_ru));
  return {prompt, choices, canReveal: (!prompt && hasPrompt) || (!choices && hasChoices)};
}
