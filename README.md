# ApprendreFr

Сайт для изучения французского: Laravel, Inertia, Vue и Vite. Начните с этого файла.

## Документация

| Файл | Что описывает |
| --- | --- |
| [docs/daily-practice/README.md](docs/daily-practice/README.md) | Вход в документацию практики |
| [docs/daily-practice/STATE.md](docs/daily-practice/STATE.md) | Что реализовано, результаты проверок и что ещё не проверено |
| [docs/daily-practice/OPERATIONS.md](docs/daily-practice/OPERATIONS.md) | Импорт, публикация, установка и откат |
| [docs/daily-practice/AUTHORING.md](docs/daily-practice/AUTHORING.md) | Как ИИ составляет и проверяет задания |
| [docs/daily-practice/FORMAT_V1.md](docs/daily-practice/FORMAT_V1.md) | Формат учебного JSON |
| [docs/AI_GUIDE.md](docs/AI_GUIDE.md) | Порядок работы для ИИ |
| [learning/README.md](learning/README.md) | Устройство локального банка |
| [plans/README.md](plans/README.md) | Оставшиеся отдельные планы SEO и игр |

## Папки

| Путь | Содержимое |
| --- | --- |
| src/ | Приложение, зависимости, тесты, миграции и собранные ресурсы; техническое описание в src/README.md |
| docs/ | Рабочая документация для человека и ИИ |
| learning/programme/packages/ | 140 активных JSON-пакетов: 7 уровней × 20 тем × 5 уроков |
| learning/programme/map.v1.json | Порядок тем, постоянные ID, цели и зависимости |
| learning/programme/editorial-profiles.json | Грамматическая прогрессия; актуальные правила нагрузки в AUTHORING |
| learning/programme/topics.tsv, lexicon.tsv | Вспомогательные таблицы тем и лексики; не файлы импорта |
| learning/programme/editorial/improvement/coverage.json | Редакторский реестр PRE_A1–B1 |
| learning/programme/editorial/improvement/baseline/ | Исходники для сравнения и восстановления содержания |
| learning/programme/editorial/improvement/pilot-results/ | Материалы необязательного наблюдения учеников, без подтверждённых результатов |
| release/lp-21/ | Итоговая приёмка и контрольные суммы пакетов; архива приложения нет |
| plans/ | Отдельные планы, завершение которых не подтверждено |
| _docker/ | Локальная инфраструктура. _docker/mysql содержит данные БД, удалять её при очистке нельзя |
| .ai/, .cursor/, .agents/, .idea/ | Настройки инструментов и среды разработки |

docker-compose.yml описывает локальный запуск. На production Docker и Node-процесс не обязательны.

## Как обновить практику

1. Прочитайте STATE и OPERATIONS.
2. При необходимости сначала установите совместимый код, сборку и миграции.
3. Импортируйте нужные JSON через /admin/daily-practice.
4. Проверьте результат: новые уроки создаются черновиками, существующие обновляются по ID с сохранением статуса.

Последние исправленные задания подготовлены локально; исполнитель не импортировал их в рабочую БД. Проверки проводились в изолированной тестовой БД.

learning/ и plans/ исключены из Git. Один checkout не восстановит учебный банк: храните его отдельную резервную копию. Не запускайте старые генераторы поверх актуальных пакетов.
