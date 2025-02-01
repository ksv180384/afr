export default [
  {
    name: 'user.posts',
    params: null,
    title: 'Все мои посты'
  },
  {
    name: 'user.posts',
    params: { status: 'published' },
    title: 'Опубликованные'
  },
  {
    name: 'user.posts',
    params: { status: 'draft' },
    title: 'Черновики'
  },
  {
    name: 'user.posts',
    params: { status: 'hidden' },
    title: 'Скрытые'
  },
];
