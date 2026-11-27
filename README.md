# ApprendreFr

Сайт для изучения французского: Laravel, Inertia, Vue и Vite. Начните с этого файла.

## Документация

| Файл | Что описывает |
| --- | --- |
| [docs/daily-practice/README.md](docs/daily-practice/README.md) | Вход в документацию практики |
| [docs/daily-practice/STATE.md](docs/daily-practice/STATE.md) | Что реализовано, результаты проверок и что ещё не проверено |
| [docs/daily-practice/OPERATIONS.md](docs/daily-practice/OPERATIONS.md) | Импорт, публикация, установка и откат |
| [Озвучка словаря](_docker/pronunciation/README.md) | Локальная генерация голосов Lea и Paul и перенос MP3 на хостинг |
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
| learning/programme/packages/ | 150 активных JSON-пакетов / 750 уроков: по 22 темы на PRE_A1–B2, по 20 на C1–C2 |
| learning/programme/map.v1.json | Порядок тем, постоянные ID, цели и зависимости |
| learning/programme/editorial-profiles.json | Грамматическая прогрессия; актуальные правила нагрузки в AUTHORING |
| learning/programme/topics.tsv, lexicon.tsv | Исторические вспомогательные таблицы; текущий состав задают карта и пакеты, не файлы импорта |
| learning/programme/editorial/improvement/coverage.json | Редакторский реестр PRE_A1–B1 и запись vocabulary_expansion о расширении PRE_A1–B2 |
| learning/programme/editorial/improvement/baseline/ | Исходники для сравнения и восстановления содержания |
| learning/programme/editorial/improvement/pilot-results/ | Материалы необязательного наблюдения учеников, без подтверждённых результатов |
| release/lp-21/ | Историческая приёмка LP-21 от 12.11.2026 и её контрольные суммы; новые темы U21–U22 в неё не входят |
| plans/ | Отдельные планы, завершение которых не подтверждено |
| _docker/ | Локальная инфраструктура. _docker/mysql содержит данные БД, удалять её при очистке нельзя |
| .ai/, .cursor/, .agents/, .idea/ | Настройки инструментов и среды разработки |

docker-compose.yml описывает локальный запуск. На production Docker и Node-процесс не обязательны.

## Как обновить практику

1. Прочитайте STATE и OPERATIONS.
2. При необходимости сначала установите совместимый код, сборку и миграции.
3. Импортируйте нужные JSON через /admin/daily-practice.
4. Проверьте результат: новые уроки создаются черновиками, существующие обновляются по ID с сохранением статуса.

Два блока расширения словаря — темы U21–U22 на PRE_A1, A1, A2, B1 и B2: суммарно 50 уроков и 160 новых учебных значений. Последний блок посвящён частям тела и одежде, готовке, аптеке и самочувствию, бюджету и управлению проектом. C1 и C2 не изменены. Пакеты подготовлены локально; исполнитель не импортировал их в рабочую БД. Проверки проводились в изолированной тестовой БД. Подробности и оставшийся объём расширения описаны в STATE.

learning/ и plans/ исключены из Git. Один checkout не восстановит учебный банк: храните его отдельную резервную копию. Не запускайте старые генераторы поверх актуальных пакетов.
