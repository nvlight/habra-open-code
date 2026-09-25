# Habra Open Code

Полнофункциональный клон базового опыта [habr.com](https://habr.com) — публикации, хабы, компании и социальная активность — на **Laravel 13** (JSON API) и **Quasar / Vue 3** (SPA).

## Документация

| Документ | Содержимое |
|---|---|
| [`docs/domain_ru.md`](docs/domain_ru.md) | Доменная модель: сущности, ER-диаграмма, енамы, бизнес-правила |
| [`docs/api_ru.md`](docs/api_ru.md) | Полный справочник всех эндпоинтов с примерами |
| [`docs/habr-archive_ru.md`](docs/habr-archive_ru.md) | Пайплайн архива habr.com: команды, очередь, scheduler, стоп/возобновление |
| [`docs/deployment_ru.md`](docs/deployment_ru.md) | Продакшен: топология, жизненный цикл TLS, мониторинг, разбор проблем |
| [`AGENTS_RU.md`](AGENTS_RU.md) | Конвенции разработки, запуск, тестирование (русская версия AGENTS.md) |
| [`frontend/README_RU.md`](frontend/README_RU.md) | Фронтенд: dev-контейнер, скрипты, структура, темы, сборка |
| [`docs/api.md`](docs/api.md) · [`docs/habr-archive.md`](docs/habr-archive.md) · [`docs/deployment.md`](docs/deployment.md) | Английские версии той же документации |

## Стек

**Бэкенд**
- PHP 8.3+, Laravel 13.17
- PostgreSQL (dev через Sail; prod v16)
- Laravel Sanctum — Bearer-токены для API
- Pest 5 — тесты; Larastan 3 (уровень 5) — статический анализ; Pint — стиль кода

**Фронтенд** (`frontend/`)
- Quasar 2 SPA + TypeScript, Vue Router (history mode), Pinia 4
- axios-клиент с Bearer-interceptor
- Vitest + @vue/test-utils (юнит), Playwright (e2e против живого стека)

## Сущности

`User` · `Company` · `Industry` · `Hub` · `Publication` (article/post/news в одной таблице) · `Tag` · `Comment` (вложенное дерево) · `Vote` (morph: публикации/комментарии/карма) · `Bookmark` · `Subscription` (morph: пользователи/хабы/компании) · `Badge`

Отношения и правила голосования описаны в [docs/domain_ru.md](docs/domain_ru.md).

## Быстрый старт

Нужен Docker (Laravel Sail для бэкенда, обычный Docker для dev-сервера фронтенда).

```bash
composer install                 # зависимости + vendor/bin/sail
sail up -d                       # контейнеры бэкенда (app, pgsql, redis…)
sail artisan key:generate        # app key (если ещё не задан)
sail artisan migrate:fresh --seed
```

API отдаётся по адресу `http://localhost/api`. Смоук-проверка:

```bash
curl http://localhost/api/publications?per_page=2
```

Запуск dev-сервера фронтенда (hot reload, проксирует `/api` на Sail-бэкенд):

```bash
docker compose -f docker/dev/frontend.compose.yml up -d
# → http://localhost:9000
```

Демо-админ после сидирования:

```
login:  admin
email:  admin@habr.test
password: password
```

У всех засеянных пользователей пароль `password`.

## Команды

Бэкенд (через Sail):

```bash
sail artisan migrate:fresh --seed   # пересобрать БД с демо-данными
sail bin pest                       # тесты (64 feature-теста)
sail bin pint --dirty               # стиль кода
sail bin phpstan analyse            # статический анализ (уровень 5)
```

Команды архива habr.com (см. [docs/habr-archive_ru.md](docs/habr-archive_ru.md)):

```bash
sail artisan habr:discover --since=YYYY-MM-DD   # собрать список URL из sitemap
sail artisan habr:fetch --limit=500             # загрузить N оставшихся источников
sail artisan habr:retry-failed                  # failed → pending
sail artisan habr:count --since=YYYY-MM-DD      # точное число опубликованных с даты
```

Архиву нужны воркеры RabbitMQ и scheduler (они умирают при рестарте контейнера):

```bash
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120   # запустить ×4
docker compose exec -d laravel.test php artisan schedule:work
```

Фронтенд (внутри контейнера `frontend-dev`):

```bash
docker exec -w /app dev-frontend-dev-1 npm test          # юнит-тесты (Vitest)
docker exec -w /app dev-frontend-dev-1 npx vue-tsc --noEmit   # typecheck
docker exec -w /app dev-frontend-dev-1 sh -c \
  "CHROMIUM_PATH=/usr/bin/chromium E2E_BASE_URL=http://localhost:9000 npx playwright test"  # e2e (15 спеков)
```

## Ключевая структура каталогов

```
app/
├── Enums/          # PublicationType/Status, Difficulty, Label, VoteSubject…
├── Http/
│   ├── Controllers/Api/   # контроллеры (Auth, Publication, Vote, Feed…)
│   ├── Requests/          # валидация (StorePublicationRequest…)
│   ├── Resources/         # API-ресурсы (PublicationResource…)
│   └── Policies/          # права автора на публикацию/комментарий
├── Logging/          # TelegramHandler — канал уведомлений об ошибках
├── Models/           # 11 моделей
└── Services/         # VoteService, PublicationQueryService
routes/api.php        # 38 эндпоинтов
database/
├── factories/        # фабрики всех моделей (+состояния: published, sandbox…)
├── migrations/       # схема PostgreSQL
└── seeders/          # демо-данные: хабы, компании, посты, комментарии, голоса
tests/Feature/        # Pest-тесты по группам эндпоинтов
frontend/             # Quasar SPA (см. frontend/README.md)
├── src/{pages,components,stores,composables,boot,types}
├── e2e/              # Playwright-спеки
├── Dockerfile        # многостадийный prod-образ (node build → статический сервер)
└── server.mjs        # статический сервер без зависимостей с SPA-fallback
docker/
├── dev/frontend.compose.yml   # dev-контейнеры (vite + e2e-браузер)
└── prod/                      # Dockerfile, nginx template, php.ini, supervisord
```

## Продакшен

Деплой через `docker-compose.prod.yml`:

| Сервис | Роль |
|---|---|
| `nginx` | TLS-вход; роутит `/api` + `/up` → PHP-FPM, ACME-челленджи → volume webroot, всё остальное → фронтенд |
| `app` | Laravel (PHP-FPM + supervisor); Telegram-канал логов ошибок; ежедневная проверка сертификата |
| `frontend` | Node.js отдаёт собранную SPA с history-mode fallback |
| `postgres`, `redis` | хранилища данных |
| `certbot` | цикл продления (webroot, shortlived 7-дневные сертификаты) |

Процесс деплоя и руководство по эксплуатации: [`docs/deployment_ru.md`](docs/deployment_ru.md).