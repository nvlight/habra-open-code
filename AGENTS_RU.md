# AGENTS_RU.md

## Обзор проекта

Полнофункциональный клон ключевых сущностей habr.com (публикации, хабы, компании, социальная активность): **Laravel 13** JSON API + **Quasar / Vue 3** SPA.

## Технологический стек

**Бэкенд**
- **PHP**: 8.3+ / Laravel 13.17
- **БД**: PostgreSQL (dev через Sail, prod v16); тесты идут против базы pgsql `testing` с `RefreshDatabase`
- **Auth**: Laravel Sanctum (Bearer-токены)
- **Тесты**: Pest 5 (`tests/Feature`, RefreshDatabase включён глобально в `tests/Pest.php`)
- **Статический анализ**: Larastan 3, уровень 5 — должен оставаться на 0 ошибок
- **Стиль кода**: Laravel Pint

**Фронтенд** (`frontend/`)
- Quasar 2 SPA, TypeScript strict, Vue Router 5 (history mode), Pinia 4, axios
- Юнит-тесты: Vitest + @vue/test-utils (jsdom), конфиг в `vitest.config.ts`
- E2E: Playwright (`frontend/e2e/`) — работает против живого dev-окружения; chromium берётся из системного пакета через `CHROMIUM_PATH=/usr/bin/chromium`

## Доменная модель

11 моделей (`app/Models/`): `User`, `Company`, `Industry`, `Hub`, `Publication` (одна таблица для article/post/news), `Tag`, `Comment` (дерево через `parent_id`), `Vote` (morph `voteable`: Publication|Comment|User-karma), `Bookmark`, `Subscription` (morph `subscribable`: User|Hub|Company), `Badge`.

Енамы в `app/Enums/`, кастятся в моделях: `PublicationType`, `PublicationStatus` (draft/sandbox/published), `Difficulty`, `PublicationLabel`, `VoteSubject`, `SubscribableType`.

Полная документация: `docs/domain_ru.md` (ER-диаграмма, бизнес-правила), `docs/api_ru.md` (все эндпоинты), `docs/habr-archive_ru.md` (пайплайн архива habr.com).

## Запуск

Бэкенд — все artisan/vendor-команды идут через **Sail** (на хосте нет PHP):

```bash
sail artisan migrate:fresh --seed   # пересобрать БД с демо-данными
sail bin pest                       # тесты
sail bin pint --dirty               # стиль кода
sail bin phpstan analyse            # статический анализ
```

Очередь для архива habr — в dev нет supervisor; воркеры и scheduler — процессы `docker compose exec -d`, и они **умирают при любом рестарте контейнера/перезагрузке** (полный ранбук — `docs/habr-archive_ru.md`). После `docker compose up -d` (или после отключения света) поднимите их заново:

```bash
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120   # запустить ×4
docker compose exec -d laravel.test php artisan schedule:work                                  # ровно один
```

Connection в `queue:work` передаётся **позиционно** (`rabbitmq`), опции `--connection` нет. Перезапуск RabbitMQ опустошает очередь, но строки `pending` в БД переживают это — возобновление через `POST /api/admin/habr/fetch` (сбрасывает флаг отмены и курсор).

Фронтенд — Node на хосте не нужен, всё работает в контейнерах:

```bash
docker compose -f docker/dev/frontend.compose.yml up -d     # vite dev server → http://localhost:9000
docker exec -w /app dev-frontend-dev-1 npm test             # юнит-тесты
docker exec -w /app dev-frontend-dev-1 npx vue-tsc --noEmit # typecheck
docker exec -w /app dev-frontend-dev-1 sh -c \
  "CHROMIUM_PATH=/usr/bin/chromium E2E_BASE_URL=http://localhost:9000 npx playwright test"
```

Dev-контейнер входит в сеть Sail и проксирует `/api` на `http://laravel.test` (один origin — без CORS).

## Конвенции разработки

### Бэкенд

- PHP 8.3; отступ 4 пробела, LF, финальный перенос строки
- В моделях — PHP 8 атрибуты `#[Fillable([...])]` / `#[Hidden([...])]` вместо свойств
- Каждая модель имеет `@property`-docblock со всеми колонками и корректными типами (енамы, Carbon, bool) — обязательно для вывода атрибутов на уровне 5 Larastan
- У отношений явные типы возврата (`HasMany`, `BelongsTo`, `MorphMany`…)
- Валидация в FormRequest, ответы через JsonResources (`app/Http/Resources/`)
- Авторизация через Policies (`PublicationPolicy`, `CommentPolicy`); базовый Controller использует `AuthorizesRequests`
- Бизнес-логика, задевающая счётчики, живёт в сервисах (`app/Services/VoteService.php`, `PublicationQueryService.php`)
- Денормализованные счётчики (`rating`, `comments_count`, `bookmarks_count`, `subscribers_count`) пересчитываются на запись, клиенту не доверяются никогда

### Фронтенд

- TypeScript strict; импорты через алиас `@/` (конвенция скаффолда, не наследие Quasar `src/`)
- **Правило API-обёртки**: каждый списочный эндпоинт отдаёт `{ data: [...], links, meta }`; одиночные ресурсы обёрнуты `{ data: { … } }`. Всегда разворачивай перед использованием и защищайся `Array.isArray(...)` — см. любую страницу с `usePublicationFeed()`
- Общая логика фида — через composable `usePublicationFeed()`; состояние подписок — в `stores/subscriptions.ts`
- Интерактивные элементы получают явные `data-testid` — на них завязаны Playwright-спеки
- Новые API-эндпоинты: сначала проверь фактическую форму ответа через curl **до** написания фронтенд-кода

## Известные грабли

### Бэкенд

- **fresh() после create()**: свежая `create()`-модель НЕ несёт дефолты колонок БД (`rating` остаётся `null` в ответах). Возвращай `$model->fresh([...])` — но это теряет `wasRecentlyCreated`, поэтому JsonResource вернёт 200 вместо 201; добавляй `->response()->setStatusCode(201)`.
- **Публичные маршруты и auth**: без middleware `auth:sanctum` `$request->user()` всегда null, даже с валидным Bearer-токеном. Используй `$request->user('sanctum')`, когда публичному маршруту нужна необязательная идентификация (например, видимость драфта).
- **Вложенные отношения в ресурсах**: `whenLoaded()` выкидывает ключи, которые не были eager-loaded. Подгружай авторов на каждом уровне вложенности (`replies.author`, `replies.replies.author`, …), иначе вложенный JSON молча теряет поля.
- **Именование pivot-таблиц**: BelongsToMany без явной таблицы выводит имя по алфавиту — `hub_publication`, а не `publication_hub`. Держи миграции консистентными.
- **Порядок сортировки**: `scopePublished()` уже добавляет `orderByDesc(published_at)`. Для сортировки по рейтингу используй `$query->reorder()->orderByDesc('rating')` — цепочка `orderBy` сама по себе станет вторичной сортировкой.
- **Сравнения Stringable**: `$request->string('sort') === 'best'` всегда false (объект против строки). Сначала приведи: `(string) $request->string('sort', 'new')`.
- **Тесты и auth**: в рамках одного тест-метода `RequestGuard` Sanctum мемоизирует пользователя между запросами. После отзыва токена зови `$this->app->make('auth')->forgetGuards()` перед проверкой 401.
- **Mass assignment**: колонки счётчиков не филлабл; используй `forceFill()` при проставлении их в тестах/сидах.
- **Флаг регистрации живёт в кэше**: `registration:disable/enable` хранит флаг через `Cache::forever`. Запуск `cache:clear` / `optimize:clear` молча включает регистрацию обратно — повтори команду после очистки кэша.
- **Legacy docker builder и нечитаемые файлы контекста**: без плагина buildx legacy-билдер прерывает всю сборку, если в контексте лежат нечитаемые файлы (root-овые `letsencrypt/archive/*.pem` после продления). `.dockerignore` в корне репо держит `docker/prod/letsencrypt`, `.env`, `.git` и `vendor` вне контекста — не удаляй эти строки, в том числе потому что иначе `COPY . .` запечёт секреты в образы.
- **`.env` vs config-кэш**: если в контейнере есть `bootstrap/cache/config.php`, правки `.env` игнорируются, пока файл не удалён и не выполнен `config:clear`. Проверяй через `php artisan config:show`.
- Faker `unique()->word()` быстро переполняется в сидерах — дедуплицируй теги через `firstOrCreate(['name' => fake()->word()])`.

### Фронтенд

- **vue-router 4+**: кастомные регэкспы в путях (`:id(\d+)`) не поддерживаются — используй простые параметры.
- **Алиасы скаффолда Quasar**: сгенерированный код импортирует через `@/…`; смесь `src/`/голых путей ломает разрешение Vite.
- **Prod-сборка строже dev**: Vite dev терпит дубли атрибутов и неразрешённые импорты до открытия страницы, но `quasar build` (Rolldown) роняет весь бандл. Гоняй prod-сборку или хотя бы `vue-tsc` перед пушем.
- **Коллизии палитры Quasar**: `.text-secondary` и т.п. генерируются из палитры (`$secondary` здесь зелёный). Свои семантические классы не должны переиспользовать имена палитры — в проекте есть `.text-dim`, `.text-link`, `.panel-card`.
- **Сборка Dockerfile**: `npm ci` обязан идти с `--ignore-scripts` — postinstall `quasar prepare` падает раньше, чем `quasar.config.ts` попадает в слой.
- **Playwright на Alpine**: ставь системный chromium (`apk add chromium`) и указывай его через `CHROMIUM_PATH`; не тяни много-гигабайтный официальный образ.

## Тестирование

### Бэкенд

- Feature-тесты по группам эндпоинтов в `tests/Feature/` (Auth, Publication, Comment, Vote, Bookmark, Subscription, Feed)
- У фабрик есть состояния: `published()`, `sandbox()`, `draft()`, `news()`, `post()`, `translation()`, `corporate()`
- Перед коммитом: `sail bin pest && sail bin pint --dirty && sail bin phpstan analyse`

### Фронтенд

- Юнит-тесты рядом с кодом (`src/**/*.test.ts`): сторы тестируются с замоканным axios (`vi.mock('@/boot/axios')`), компоненты через `@vue/test-utils`
- E2E-спеки в `frontend/e2e/` и работают против реального dev-стэка (Sail-бэкенд + vite); вход через засеянных `admin/password`
- Перед коммитом: `npm test && npx vue-tsc --noEmit` + команда playwright выше