import { createPreviewAdapter } from '@/App/Learning/playerAdapter';
import { calculateSessionResult } from '@/App/Learning/answerChecker';

export const createEditorPreviewAdapter = ({ session, scenario = 'session', onDraft = () => {}, idFactory }) => {
  const base = createPreviewAdapter({ loadSession: async () => session, idFactory });
  let failOnce = scenario === 'load_error';
  return Object.freeze({
    ...base,
    async load(input) {
      if (failOnce) { failOnce = false; throw new Error('Тестовый сбой загрузки. Нажмите «Повторить запрос».'); }
      const view = await base.load(input);
      if (['empty', 'exhausted'].includes(scenario)) return { ...view, total: 0, task: null, presentation: null, vocabulary: [], introductions: [],
        exhausted: scenario === 'exhausted', summary: calculateSessionResult([], 0, 1) };
      return view;
    },
    ...(scenario === 'save' ? { async saveDraft(draft) { onDraft(structuredClone(draft)); } } : {}),
  });
};
