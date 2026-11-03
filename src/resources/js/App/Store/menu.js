export default [
    {
        path: '/',
        name: 'index',
        title: 'Главная'
    },
    {path:'/practice',name:'practice',title:'Практика',activePaths:['/practice','/daily']},
    {
        path: '/grammar',
        name: 'grammar',
        title: 'Грамматика'
    },
    {
        path: '/lessons',
        name: 'lessons',
        title: 'Уроки'
    },
    {
        path: '/dictionary',
        name: 'dictionary',
        title: 'Словарь'
    },
    {
        path: '/games',
        name: 'games',
        title: 'Игры'
    },
];

export const isMenuItemActive = (url, item) => {
    const path = url.split(/[?#]/)[0];
    return (item.activePaths || [item.path]).some(section =>
        path === section || (section !== '/' && path.startsWith(section + '/'))
    );
};
