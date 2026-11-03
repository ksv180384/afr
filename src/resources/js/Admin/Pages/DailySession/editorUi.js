export const sessionStatuses = [
  { value: 'draft', label: 'Черновик', type: 'info' },
  { value: 'reviewed', label: 'Готово', type: 'warning' },
  { value: 'published', label: 'Опубликовано', type: 'success' },
  { value: 'archived', label: 'Архив', type: 'info' },
];
export const statusLabel = (value) => sessionStatuses.find((item) => item.value === value)?.label ?? value;
export const practiceSections = [
  { path: '/admin/daily-practice', label: 'Сессии и импорт' },
  { path: '/admin/daily-practice/catalog', label: 'Уровни, темы и программы' },
  { path: '/admin/daily-practice/reports', label: 'Обращения' },
];
export const activePracticeSection = (url) => {
  const path = url.split(/[?#]/)[0].replace(/\/+$/, '');
  return practiceSections.slice(1).find((section) => path === section.path || path.startsWith(section.path + '/'))?.path ?? practiceSections[0].path;
};
