# Habra API

Backend-клон основных сущностей [habr.com](https://habr.com) — статей, хабов, компаний и соцактивности — в виде JSON API на **Laravel 13**.

## Документация

| Документ | Содержимое |
|---|---|
| [`docs/domain.md`](docs/domain.md) | Доменная модель: сущности, ER-диаграмма, enum'ы, бизнес-правила |
| [`docs/api.md`](docs/api.md) | Полный справочник всех эндпоинтов с примерами |

## Стек

- PHP 8.3+, Laravel 13.17
- PostgreSQL (dev — через Sail; prod — v16)
- Laravel Sanctum — Bearer-токены для API
- Pest 5 — тесты; Larastan 3 (level 5) — статанализ; Pint — стиль кода

## Сущности

`User` · `Company` · `Industry` · `Hub` · `Publication` (статья/пост/новость) · `Tag` · `Comment` (дерево) · `Vote` (morph: публикации/комментарии/карма) · `Bookmark` · `Subscription` (morph: пользователи/хабы/компании) · `Badge`

Подробно о связях и правилах голосования — [docs/domain.md](docs/domain.md).

## Быстрый старт

Требуется Docker (Laravel Sail).

```bash
composer install                 # зависимости + vendor/bin/sail
sail up -d                       # контейнеры (app, pgsql, redis…)
sail artisan key:generate        # ключ приложения (если ещё нет)
sail artisan migrate:fresh --seed
```

API доступен на `http://localhost/api`. Проверка:

```bash
curl http://localhost/api/publications?per_page=2
```

Демо-аккаунт администратора после сидинга:

```
login:  admin
email:  admin@habr.test
пароль: password
```

Все сидовые пользователи используют пароль `password`.

## Команды

```bash
sail artisan migrate:fresh --seed   # пересоздать БД с демо-данными
sail bin pest                       # тесты (49 feature-тестов)
sail bin pint --dirty               # стиль кода
sail bin phpstan analyse            # статанализ (level 5)

composer dev                        # локальный сервер без Docker
```

## Структура ключевых каталогов

```
app/
├── Enums/          # PublicationType/Status, Difficulty, Label, VoteSubject…
├── Http/
│   ├── Controllers/Api/   # 11 контроллеров (Auth, Publication, Vote, Feed…)
│   ├── Requests/          # валидация (StorePublicationRequest…)
│   ├── Resources/         # API-ресурсы (PublicationResource…)
│   └── Policies/          # права автора на публикацию/комментарий
├── Models/         # 11 моделей
└── Services/       # VoteService, PublicationQueryService
routes/api.php      # 38 эндпоинтов
database/
├── factories/      # фабрики всех моделей (+states: published, sandbox…)
├── migrations/     # схема PostgreSQL
└── seeders/        # демо-данные: хабы, компании, статьи, комментарии, голоса
tests/Feature/      # Pest-тесты по группам эндпоинтов
```
