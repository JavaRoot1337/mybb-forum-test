# ADR-002: отдельный public web-root

## Статус

Принято; заменяет решение ADR-001 о web-root в корне проекта

## Контекст

Корень репозитория содержал десятки legacy PHP entrypoint-файлов MyBB, runtime-каталоги и конфигурации инструментов. Это затрудняло навигацию и смешивало исходный код форума с инфраструктурой проекта.

## Решение

Весь web-контур MyBB перенесён в `public/`: PHP-страницы, `admin`, `inc`, `install`, публичные ресурсы, runtime-кэш, загрузки, SQLite и лог. Сервер запускается с `public/` как document root, поэтому публичные URL остаются прежними.

Конфигурации Composer и анализаторов хранятся в `config/` и запускаются через `--working-dir=config`. Тесты и CI используют новые пути к `public/inc/vendor` и `public/inc/src`.

В корне остаются `LICENSE`, `README.md`, `start.bat`, каталоги проекта и скрытые технические файлы Git/CI.

## Последствия

- URL MyBB, имена entrypoint-файлов, plugin/theme-контракты и namespace не меняются.
- Local PHP server, devcontainer, CI, Composer, PHPUnit, PHPCS и CLI используют новый web-root.
- Runtime-файлы не находятся в корне репозитория; `.gitignore` защищает их уже внутри `public/`.
- Production web-server должен использовать `public/` как document root.
